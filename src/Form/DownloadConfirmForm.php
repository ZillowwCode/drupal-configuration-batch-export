<?php

namespace Drupal\configuration_batch_export\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form to confirm the download of the archive.
 */
class DownloadConfirmForm extends ConfirmFormBase {
    /**
    * {@inheritdoc}
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
        return parent::buildForm($form, $form_state);
    }

    /**
    * {@inheritdoc}
    */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $helperService = \Drupal::service('configuration_batch_export.helper');
        $exportFolderPath = $helperService->getTempFolderPath();

        $files = scandir($exportFolderPath);
        $files = array_diff($files, ['.', '..']);

        $messenger = \Drupal::messenger();
        $logger = \Drupal::logger('configuration_batch_export');

        if (count($files) == 0) {
            $messenger->addError(t('No archive found. Please export your configuration again.'));
            return;
        } else if (count($files) > 1) {
            $messenger->addError(t('An error ocurred while processing your archive. Please, remove all the the archives present in the folder @folder and export your configuration again.', ['@folder' => $exportFolderPath]));
            $logger->error(t('More than one archive found in the folder ' . $exportFolderPath . '. Please, remove all the archives and export your configuration again.'));
            return;
        } else {
            $archiveName = $files[2];
            
            $archivePath = $helperService->getArchiveRealPath($archiveName);
            $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($archivePath);

            $response->setContentDisposition(
                \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $archiveName
            );

            $response->send();

            $logger->info(t('Archive "' . $archiveName . '" downloaded successfully.'));

            unlink($archivePath);
        }
    }

    /**
    * {@inheritdoc}
    */
    public function getFormId() : string {
        return "configuration_batch_export_download_confirm_form";
    }

    /**
    * {@inheritdoc}
    */
    public function getCancelUrl() {
        return new Url('configuration_batch_export.export');
    }

    /**
    * {@inheritdoc}
    */
    public function getQuestion() {
        return $this->t('Download your archive');
    }

    /**
    * {@inheritdoc}
    */
    public function getDescription() {
        return $this->t('Your configuration has been exported successfully. Do you want to download the archive?');
    }
}