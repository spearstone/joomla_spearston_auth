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

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class SpearstoneauthViewConfiguration extends HtmlView
{
    protected $form;

    public function display($tpl = null)
    {
        $this->form = $this->get('Form');

        if (count($errors = $this->get('Errors'))) {
            throw new Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title('Spearstone Auth Configuration', 'options');
        ToolbarHelper::apply('configuration.apply');
        ToolbarHelper::save('configuration.save');
        ToolbarHelper::cancel('configuration.cancel', 'JTOOLBAR_CLOSE');
    }
}