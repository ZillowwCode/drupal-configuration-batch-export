<?php
 
namespace Drupal\configuration_batch_export\Form;
 
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ExportForm extends FormBase {

    protected $batchService;
    protected $helperService;
    protected $DEFAULT_CONFIGS_PER_CHUNK;

    public function __construct() {
        $this->batchService = \Drupal::service('configuration_batch_export.batch');
        $this->helperService = \Drupal::service('configuration_batch_export.helper');
        $this->DEFAULT_CONFIGS_PER_CHUNK = 40;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'configuration_batch_export_form';
    }
 
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $username = NULL) {
        $form['configs_per_chunk'] = [
            '#type' => 'number',
            '#title' => $this->t('Number of configurations per chunk'),
            '#description' => $this->t('This defines the number of configuration files that will be processed in each batch operation. <br /> <em>(Default: ' . $this->DEFAULT_CONFIGS_PER_CHUNK . ', Min: 1, Max: 200)</em>'),
            '#default_value' => $this->DEFAULT_CONFIGS_PER_CHUNK,
            '#required' => TRUE,
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Export'),
        ];

        return $form;
    }
 
    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        $configs_per_chunk = $form_state->getValue('configs_per_chunk');
        
        if ($configs_per_chunk < 1 || $configs_per_chunk > 200) {
            $form_state->setErrorByName('configs_per_chunk', $this->t('The number of configurations per chunk must be between 1 and 200.'));
        }
    }
 
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $this->batchService->batch_start($form_state->getValue('configs_per_chunk'));
        $form_state->setRedirect('configuration_batch_export.download_archive');
    }
}