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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class SpearstoneauthControllerConfiguration extends BaseController
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        // Register tasks
        $this->registerTask('apply', 'save');
        $this->registerTask('save', 'save');
        $this->registerTask('cancel', 'cancel');
    }

    public function save()
    {
        // Check for request forgeries.
        $this->checkToken();

        // Get the data from the POST request.
        $data = $this->input->get('jform', array(), 'array');

        // Get the model
        $model = $this->getModel('Configuration');

        // Validate the data
        $form = $model->getForm($data, false);
        if (!$form) {
            throw new Exception($model->getError(), 500);
        }

        // Validate the posted data.
        $validData = $model->validate($form, $data);

        // Check for errors.
        if ($validData === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0; $i < min(count($errors), 3); $i++) {
                if ($errors[$i] instanceof Exception) {
                    $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage($errors[$i], 'warning');
                }
            }

            // Redirect back to the edit screen.
            $this->setRedirect(JRoute::_('index.php?option=com_spearstoneauth&view=configuration', false));
            return false;
        }

        // Attempt to save the data.
        if ($model->save($validData)) {
            $this->setMessage(Text::_('COM_SPEARSTONEAUTH_CONFIGURATION_SAVED'));
        } else {
            $this->setMessage(Text::_('COM_SPEARSTONEAUTH_CONFIGURATION_SAVE_FAILED'), 'error');
        }

        // Determine where to redirect
        $task = $this->getTask();
        if ($task === 'apply') {
            $this->setRedirect(JRoute::_('index.php?option=com_spearstoneauth&view=configuration', false));
        } else {
            $this->setRedirect(JRoute::_('index.php?option=com_spearstoneauth', false));
        }
    }

    public function cancel()
    {
        $this->setRedirect(JRoute::_('index.php?option=com_spearstoneauth', false));
    }
}
