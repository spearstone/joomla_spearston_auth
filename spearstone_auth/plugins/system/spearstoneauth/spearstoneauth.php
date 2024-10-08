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

defined('_JEXEC') or die;

require_once __DIR__ . '/vendor/autoload.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Access\Access as CoreAccess;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Environment\Browser;

class PlgSystemSpearstoneauth extends CMSPlugin
{
    protected $app;

    public function onAfterInitialise()
    {
        // Only proceed in the frontend
        if (!$this->app->isClient('site')) {
            return;
        }

        // Check if the extension is enabled
        $params = ComponentHelper::getParams('com_spearstoneauth');
        $extensionMode = $params->get('extension_mode', 'secondary');

        if ($extensionMode === 'disabled') {
            // Do not register the custom Access class
            return;
        }

        // Register the custom Access class
        $container = Factory::getContainer();

        $container->set(
            CoreAccess::class,
            function () {
                return new \Joomla\CMS\Access\SpearstoneAccess;
            }
        );

        // Alias the class
        $container->alias(
            'Joomla\CMS\Access\Access',
            'Joomla\CMS\Access\SpearstoneAccess'
        );
    }

    public function onAfterRoute()
    {
        // Only proceed in the frontend
        if (!$this->app->isClient('site')) {
            return;
        }

        $session = Factory::getSession();

        // Check if we have already handled an authentication error
        if ($session->get('spearstoneauth_error_handled', false)) {
            // Clear the flag for future requests
            $session->clear('spearstoneauth_error_handled');
            return;
        }

        // Check if we are already processing authentication
        if ($session->get('spearstoneauth_handling', false)) {
            return;
        }

        // Get the input
        $input = $this->app->input;

        // Check for OAuth error parameters
        $error = $input->get('error', null, 'raw');
        $errorDescription = $input->get('error_description', null, 'raw');

        if ($error) {
            // Handle the OAuth error
            $this->handleAuthError($error, $errorDescription);
            // After handling the error, return to prevent further processing
            return;
        }

        // Get the current user
        $user = Factory::getUser();

        // Get extension mode
        $params = ComponentHelper::getParams('com_spearstoneauth');
        $extensionMode = $params->get('extension_mode', 'secondary');

        // Determine if the extension should be active
        if ($extensionMode === 'disabled') {
            // Do nothing
            return;
        }

        // Check if user is authenticated or not
        if (!$user->guest) {
            // User is authenticated, no action needed
            return;
        }

        // User is a guest, check if accessing a protected resource
        $isProtectedResource = $this->isProtectedResource();

        if ($isProtectedResource) {
            // Set session variable to prevent recursive handling
            $session->set('spearstoneauth_handling', true);

            // Redirect to IDP login
            $this->redirectToIdP();

            // After redirecting, return to prevent further processing
            return;
        }
    }

    protected function handleAuthError($error, $errorDescription)
    {
        $session = Factory::getSession();

        // Sanitize the error parameters
        $error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        $errorDescription = htmlspecialchars($errorDescription, ENT_QUOTES, 'UTF-8');

        // Build the error message
        $errorMessage = "Authentication Error: $error - $errorDescription";

        // Enqueue the error message
        $this->app->enqueueMessage($errorMessage, 'error');

        // Clear the authentication handling flag
        $session->clear('spearstoneauth_handling');

        // Set the error handled flag
        $session->set('spearstoneauth_error_handled', true);

        // Clean the output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Redirect to a safe page
        $homeUrl = Route::_('index.php', false);

        // Redirect and close the application
        $this->app->redirect($homeUrl);
        $this->app->close();

        // Ensure no further code is executed
        exit;
    }

    protected function isProtectedResource()
    {
        $user = Factory::getUser();

        // Get the current view levels the user can access
        $userViewLevels = $user->getAuthorisedViewLevels();

        // Get the active menu item
        $menu = $this->app->getMenu();
        $active = $menu->getActive();

        if ($active) {
            $requiredViewLevel = $active->access;

            if (!in_array($requiredViewLevel, $userViewLevels)) {
                // User does not have access to this menu item
                return true;
            }
        }

        // Additionally, check the current component and view
        $option = $this->app->input->getCmd('option');
        $view = $this->app->input->getCmd('view');
        $id = $this->app->input->getInt('id');

        // Load the content item and check access level
        if ($option === 'com_content' && $view === 'article' && $id) {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('access')
                ->from('#__content')
                ->where('id = :id')
                ->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $access = (int) $db->loadResult();

            if (!in_array($access, $userViewLevels)) {
                // User does not have access to this content item
                return true;
            }
        }

        // Add other checks if necessary

        return false;
    }

    protected function redirectToIdP()
    {
        $session = Factory::getSession();
        $params = ComponentHelper::getParams('com_spearstoneauth');

        // Build the authorization URL
        $provider = $this->getOidcProvider($params);

        $authorizationUrl = $provider->getAuthorizationUrl();
        $state = $provider->getState();

        // Store state in session
        $session->set('oidc_state', $state);

        // Redirect to authorization URL
        $this->app->redirect($authorizationUrl);
        $this->app->close();
    }

    protected function getOidcProvider($params)
    {
        return new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId'                => $params->get('client_id'),
            'clientSecret'            => $params->get('client_secret'),
            'redirectUri'             => $params->get('redirect_uri'),
            'urlAuthorize'            => $params->get('auth_endpoint'),
            'urlAccessToken'          => $params->get('token_endpoint'),
            'urlResourceOwnerDetails' => $params->get('userinfo_endpoint', ''),
            'scopes'                  => explode(' ', $params->get('scopes', 'openid profile email')),
        ]);
    }
}
