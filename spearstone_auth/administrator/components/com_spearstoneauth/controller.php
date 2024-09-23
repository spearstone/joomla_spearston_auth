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

use Joomla\CMS\MVC\Controller\BaseController;

class SpearstoneauthController extends BaseController
{
    protected $default_view = 'configuration';

    public function getModel($name = 'Configuration', $prefix = 'SpearstoneauthModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }
}