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

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

?>

<form action="<?php echo JRoute::_('index.php?option=com_spearstoneauth&task=configuration.save'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <?php echo $this->form->renderFieldset('general'); ?>
    <?php echo $this->form->renderFieldset('oidc'); ?>
    <?php echo $this->form->renderFieldset('key_configuration'); ?>
    <?php echo $this->form->renderFieldset('group_mapping'); ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>