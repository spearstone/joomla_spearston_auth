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

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Factory;

class SpearstoneauthModelConfiguration extends AdminModel
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
        $data = (array) Factory::getApplication()->getUserState(
            'com_spearstoneauth.edit.configuration.data',
            array()
        );

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    public function getItem($pk = null)
    {
        // Get the configuration from the component parameters
        $params = $this->getComponentParams();

        return $params->toArray();
    }

    public function save($data)
    {
        $component = \Joomla\CMS\Component\ComponentHelper::getComponent('com_spearstoneauth');
        $table = $this->getTable();

        $table->load($component->id);
        $table->bind(array('params' => $data));

        if (!$table->check() || !$table->store()) {
            $this->setError($table->getError());
            return false;
        }

        return true;
    }

    protected function getComponentParams()
    {
        return \Joomla\CMS\Component\ComponentHelper::getParams('com_spearstoneauth');
    }
}
