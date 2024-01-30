<?php

namespace Drupal\configuration_batch_export\Service;

use Drupal\configuration_batch_export\Service\HelperService;

class BatchService {

    protected $helperService;

    public function __construct(HelperService $helperService) {
        $this->helperService = $helperService;
    }

    public function batch_start($configs_per_chunk) {
        $archiveName = $this->helperService->getArchiveName();
        $zip = $this->helperService->createArchive($archiveName);
    
        $configNames = $this->helperService->getConfigNames();
        
        $chunks = array_chunk($configNames, $configs_per_chunk);

        $operations = [];
        foreach($chunks as $chunk) {
            $operations[] = [
                '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_process_chunk',
                [$chunk, $archiveName]
            ];
        }

        $operations[] = [
            '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_close_zip_file',
            []
        ];

        $batch = [
            'title' => t('Exporting configuration'),
            'operations' => $operations,
            'finished' => '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_finished',
        ];

        batch_set($batch);
    }

    public static function batch_operation_process_chunk($chunk, $archiveName, &$context) {
        foreach($chunk as $configName) {
            $configData = \Drupal::configFactory()->get($configName)->getRawData();
            $ymlData = \Symfony\Component\Yaml\Yaml::dump($configData, 10, 2);
    
            $zip = \Drupal::service('configuration_batch_export.helper')->getArchive();
            $zip->addFromString($configName . '.yml', $ymlData);
    
            $context['results']['zip'] = $zip;
            $context['results']['archiveName'] = $archiveName;
        }
    }

    public static function batch_operation_close_zip_file(&$context) {
        $zip = $context['results']['zip'];
        $zip->close();
    }

    public static function batch_operation_finished($success, $results, $operations) {
        $helperService = \Drupal::service('configuration_batch_export.helper');
        $messenger = \Drupal::messenger();
        if ($success) {
            $messenger->addMessage(t('Batch export of the entire configuration finished.'));

            // header('Content-Type: application/zip');
            // header('Content-Disposition: attachment; filename="' . $results['archiveName'] . '"');
            // header('Content-Length: ' . filesize($helperService->getArchiveRealPath()));
            // readfile($helperService->getArchiveRealPath());
            // unlink($helperService->getArchiveRealPath());
            
            $zipArchivePath = $helperService->getArchiveRealPath();

            $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($zipArchivePath);
            $response->setContentDisposition(
                \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                basename($zipArchivePath)
            );

            batch_set([
                'title' => t('Deleting temporary file'),
                'operations' => [
                    ['\Drupal\configuration_batch_export\Service\BatchService::batch_operation_delete_temp_file', [$zipArchivePath]],
                ],
                'finished' => '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_delete_temp_file_finished',
            ]);

            $response->send();
        } else {
            $messenger->addMessage(t('An unknown error ocurred while exporting the website config.'), 'error');
        }
    }

    public static function batch_operation_delete_temp_file($zipFilePath, &$context) {
        // Delete the temporary file.
        unlink($zipFilePath);
    }
}