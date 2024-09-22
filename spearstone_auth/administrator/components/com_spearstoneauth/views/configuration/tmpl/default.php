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

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

?>
<form action="<?php echo JRoute::_('index.php?option=com_spearstoneauth&view=configuration'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <fieldset>
        <legend><?php echo Text::_('COM_SPEARSTONEAUTH_FIELDSET_GENERAL'); ?></legend>
        <?php echo $this->form->renderFieldset('general'); ?>
    </fieldset>

    <fieldset>
        <legend><?php echo Text::_('COM_SPEARSTONEAUTH_FIELDSET_OIDC'); ?></legend>
        <?php echo $this->form->renderFieldset('oidc'); ?>
    </fieldset>

    <fieldset>
        <legend><?php echo Text::_('COM_SPEARSTONEAUTH_FIELDSET_GROUP_MAPPING'); ?></legend>
        <?php echo $this->form->renderFieldset('group_mapping'); ?>
    </fieldset>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>