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

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

class SpearstoneauthModelConfiguration extends FormModel
{
    protected $text_prefix = 'COM_SPEARSTONEAUTH';

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm(
            'com_spearstoneauth.configuration',
            'config',
            array('control' => 'jform', 'load_data' => $loadData)
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = $this->getItem();
        return $data;
    }

    public function getItem($pk = null)
    {
        // Get the component params
        $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_spearstoneauth');
        return $params->toArray();
    }

    public function save($data)
    {
        // Load the component's row from #__extensions
        $component = \Joomla\CMS\Component\ComponentHelper::getComponent('com_spearstoneauth');
        $id = $component->id;

        // Get the JTable extension instance
        $table = Table::getInstance('extension');

        if (!$table->load($id)) {
            $this->setError('Failed to load component for saving configuration.');
            return false;
        }

        // Merge existing params with new data
        $params = json_decode($table->params, true);
        if (empty($params)) {
            $params = array();
        }

        $params = array_merge($params, $data);

        // Save the params back to the table
        $table->params = json_encode($params);

        // Save the table
        if (!$table->check() || !$table->store()) {
            $this->setError($table->getError());
            return false;
        }

        return true;
    }

    //override to ensure the extension table is used instead of a 'configuration' table that doesn't exist
    public function getTable($type = 'Extension', $prefix = 'JTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
    }
}
