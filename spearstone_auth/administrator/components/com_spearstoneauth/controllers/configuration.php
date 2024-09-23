<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_spearstoneauth
 * @version     1.0.0
 * @license     MIT License
 * @author      Lance Douglas
 * @copyright   (c) 2024 Spearstone, Inc.
 * @support     support+spearstoneauth@spearstone.partners
 * @created     2024-09-21
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

class SpearstoneauthControllerConfiguration extends FormController
{
    // Override the parent constructor if necessary
    public function __construct($config = array())
    {
        parent::__construct($config);

        // Set the redirect on successful save
        $this->registerTask('apply', 'save');
    }

    protected function allowSave($data = array(), $key = 'id')
    {
        // Allow save without any special permission checks
        return true;
    }
}