<?php

namespace Axepta\Controller;

use Axepta\Axepta;
use Axepta\Form\ConfigurationForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    public function configure(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'Axepta', AccessManager::UPDATE)) {
            return $response;
        }

        // Create the Form from the request
        $configurationForm = $this->createForm(ConfigurationForm::getName());

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($configurationForm, "POST");

            // Get the form field values
            $data = $form->getData();

            foreach ($data as $name => $value) {
                if (is_array($value)) {
                    $value = implode(';', $value);
                }

                Axepta::setConfigValue($name, $value);
            }

            // Log configuration modification
            $this->adminLogAppend(
                "axepta.configuration.message",
                AccessManager::UPDATE,
                "Axepta configuration updated"
            );

            // Redirect to the success URL,
            if ($request->get('save_mode') === 'stay') {
                // If we have to stay on the same page, redisplay the configuration page/
                $route = '/admin/module/Axepta';
            } else {
                // If we have to close the page, go back to the module back-office page.
                $route = '/admin/modules';
            }

            return $this->generateRedirect(URL::getInstance()->absoluteUrl($route));

            // An exit is performed after redirect.+
        } catch (FormValidationException $ex) {
            // Form cannot be validated. Create the error message using
            // the BaseAdminController helper method.
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        // At this point, the form has errors, and should be redisplayed. We do not redirect,
        // just redisplay the same template.
        // Set up the Form error context, to make error information available in the template.
        $this->setupFormErrorContext(
            Translator::getInstance()->trans("Axepta configuration", [], Axepta::DOMAIN_NAME),
            $error_msg,
            $configurationForm,
            $ex
        );

        // Do not redirect at this point, or the error context will be lost.
        // Just redisplay the current template.
        return $this->render('module-configure', array('module_code' => 'Payline'));
    }
}