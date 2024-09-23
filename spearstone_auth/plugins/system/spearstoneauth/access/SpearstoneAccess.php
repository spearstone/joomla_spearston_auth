<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.spearstoneauth
 * @version     1.0.0
 * @license     MIT License
 * @author      Lance Douglas
 * @copyright   (c) 2024 Spearstone, Inc.
 * @support     support+spearstoneauth@spearstone.partners
 * @created     2024-09-21
 */

namespace Joomla\CMS\Access;

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use League\OAuth2\Client\Provider\GenericProvider;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Cache\CacheControllerFactory;

class SpearstoneAccess extends Access
{
    public static function getGroupsByUser($userId, $recursive = true)
    {
        $storeId = $userId . ':' . (int) $recursive;

        if (!isset(self::$groupsByUser[$storeId])) {
            // Get the extension mode from configuration
            $params = ComponentHelper::getParams('com_spearstoneauth');
            $extensionMode = $params->get('extension_mode', 'secondary');

            $applyExtension = false;

            // Determine if we should apply the extension logic based on the mode
            if ($extensionMode === 'disabled') {
                $applyExtension = false;
            } elseif ($extensionMode === 'secondary' && $userId == 0) {
                $applyExtension = true;
            } elseif ($extensionMode === 'primary') {
                $applyExtension = true;
            }

            if ($applyExtension) {
                // Attempt to get groups from IDP token
                $groups = self::getGroupsFromToken();

                if (!empty($groups)) {
                    self::$groupsByUser[$storeId] = $groups;
                    return self::$groupsByUser[$storeId];
                }
            }

            // Fall back to parent method
            self::$groupsByUser[$storeId] = parent::getGroupsByUser($userId, $recursive);
        }

        return self::$groupsByUser[$storeId];
    }

    protected static function getGroupsFromToken()
    {
        $app = Factory::getApplication();
        $session = Factory::getSession();

        // Get the ID token from the session
        $idToken = $session->get('oidc_id_token');

        if (!$idToken) {
            // No ID token in session, attempt to obtain it
            $idToken = self::authenticateWithOIDC();

            if (!$idToken) {
                return [];
            }
        }

        // Validate and decode the ID token
        try {
            $params = ComponentHelper::getParams('com_spearstoneauth');
            $clientId = $params->get('client_id');
            $publicKey = $params->get('public_key'); // Should be the public key in PEM format

            $decodedToken = self::decodeJwt($idToken, $publicKey);

            // Get roles/claims from the ID token
            $roles = $decodedToken->roles ?? [];

            // Map roles to Joomla group IDs
            $groupMap = $params->get('group_map', []);
            $groupMapAssoc = [];
            foreach ($groupMap as $mapping) {
                $groupMapAssoc[$mapping->role] = (int) $mapping->group_id;
            }

            $groups = [];
            foreach ($roles as $role) {
                if (isset($groupMapAssoc[$role])) {
                    $groups[] = $groupMapAssoc[$role];
                }
            }

            return array_unique($groups);
        } catch (\Exception $e) {
            // Handle token validation errors
            return [];
        }
    }

    protected static function authenticateWithOIDC()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $session = Factory::getSession();

        // Check for authorization code in the URL
        $code = $input->get('code', '', 'string');
        $state = $input->get('state', '', 'string');

        $params = ComponentHelper::getParams('com_spearstoneauth');

        // If we have a code and state, exchange it for tokens
        if ($code && $state) {
            // Verify state
            $storedState = $session->get('oidc_state');
            if ($state !== $storedState) {
                return false;
            }

            // Exchange code for tokens
            try {
                $provider = self::getOidcProvider();
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $code,
                ]);

                // Store ID token in session
                $idToken = $accessToken->getValues()['id_token'] ?? null;
                if ($idToken) {
                    $session->set('oidc_id_token', $idToken);
                    return $idToken;
                }
            } catch (\Exception $e) {
                // Handle token exchange errors
                return false;
            }
        } else {
            // Initiate OIDC authorization request
            $provider = self::getOidcProvider();
            $authorizationUrl = $provider->getAuthorizationUrl();
            $state = $provider->getState();

            // Store state in session
            $session->set('oidc_state', $state);

            // Redirect to authorization URL
            $app->redirect($authorizationUrl);
            $app->close();
        }

        return false;
    }

    protected static function getOidcProvider()
    {
        $params = ComponentHelper::getParams('com_spearstoneauth');

        $provider = new GenericProvider([
            'clientId'                => $params->get('client_id'),
            'clientSecret'            => $params->get('client_secret'),
            'redirectUri'             => $params->get('redirect_uri'),
            'urlAuthorize'            => $params->get('auth_endpoint'),
            'urlAccessToken'          => $params->get('token_endpoint'),
            'urlResourceOwnerDetails' => $params->get('userinfo_endpoint', ''),
            'scopes'                  => explode(' ', $params->get('scopes', 'openid profile email')),
        ]);

        return $provider;
    }

    protected static function decodeJwt($jwt, $publicKey)
    {
        // Use a JWT library to decode and verify the JWT
        $params = ComponentHelper::getParams('com_spearstoneauth');
        $keySource = $params->get('key_source', 'pem');

        if ($keySource === 'pem') {
            $decoded = \Firebase\JWT\JWT::decode($jwt, \Firebase\JWT\Key\key($publicKey, 'RS256'));
        } elseif ($keySource === 'jwks') {
            $decoded = self::decodeJwtWithJwks($jwt);
        } else {
            throw new \Exception('Invalid key source configuration.');
        }

        return $decoded;
    }

    protected static function decodeJwtWithJwks($jwt)
    {
        $params = ComponentHelper::getParams('com_spearstoneauth');
        $jwksUri = $params->get('jwks_uri');

        if (empty($jwksUri)) {
            throw new \Exception('JWKS URI is not configured.');
        }

        $jwks = self::getCachedJwks($jwksUri);

        // Decode the JWT header to get the kid
        $tokenParts = explode('.', $jwt);
        $header = json_decode(base64_decode($tokenParts[0]));

        if (!isset($header->kid)) {
            throw new \Exception('No kid found in token.');
        }

        // Find the key with the matching kid
        $key = null;
        foreach ($jwks['keys'] as $jwk) {
            if ($jwk['kid'] === $header->kid) {
                $key = $jwk;
                break;
            }
        }

        if (!$key) {
            throw new \Exception('Unable to find appropriate key for token verification.');
        }

        // Build the public key
        $publicKey = self::jwkToPem($key);

        // Decode and verify the token
        $decoded = \Firebase\JWT\JWT::decode($jwt, \Firebase\JWT\Key\key($publicKey, $key['alg']));

        return $decoded;
    }

    protected static function getCachedJwks($jwksUri)
    {
        $cache = CacheControllerFactory::getCacheController('callback', ['defaultgroup' => 'spearstoneauth']);

        // Cache the JWKS for 6 hours (21600 seconds)
        $jwks = $cache->get(
            function () use ($jwksUri) {
                $client = new \GuzzleHttp\Client();
                $response = $client->get($jwksUri);
                $body = $response->getBody();
                $jwks = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error decoding JWKS: ' . json_last_error_msg());
                }

                return $jwks;
            },
            [$jwksUri],
            md5($jwksUri),
            21600 // Cache lifetime in seconds
        );

        return $jwks;
    }

    protected static function jwkToPem($jwk)
    {
        if ($jwk['kty'] !== 'RSA') {
            throw new \Exception('Only RSA keys are supported.');
        }

        $n = self::base64urlDecode($jwk['n']);
        $e = self::base64urlDecode($jwk['e']);
        $publicKey = self::convertRSA($n, $e);

        return $publicKey;
    }

    protected static function base64urlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'));

        return $decoded;
    }

    protected static function convertRSA($n, $e)
    {
        $components = [
            'modulus' => $n,
            'publicExponent' => $e,
        ];

        $rsa = \phpseclib3\Crypt\RSA::loadPublicKey(
            [
                'n' => $components['modulus'],
                'e' => $components['publicExponent'],
            ]
        );

        return $rsa->savePublicKey('PKCS8');
    }
}