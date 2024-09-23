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
            // Redirect to IDP login
            $this->redirectToIdP();
        }
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
