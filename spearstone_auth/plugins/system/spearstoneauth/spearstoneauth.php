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
}