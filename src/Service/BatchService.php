<?php

namespace Drupal\configuration_batch_export\Service;

use Drupal\configuration_batch_export\Service\HelperService;

class BatchService {

    protected $helperService;

    public function __construct(HelperService $helperService) {
        $this->helperService = $helperService;
    }

    public function batch_start($configs_per_chunk) {    
        $configNames = $this->helperService->getConfigNames();
        
        $chunks = array_chunk($configNames, $configs_per_chunk);

        $operations = [];
        foreach($chunks as $chunk) {
            $operations[] = [
                '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_process_chunk',
                [$chunk]
            ];
        }

        $operations[] = [
            '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_process_archive',
            []
        ];

        $batch = [
            'title' => t('Exporting configuration'),
            'operations' => $operations,
            'finished' => '\Drupal\configuration_batch_export\Service\BatchService::batch_operation_finished',
        ];

        batch_set($batch);
    }

    public static function batch_operation_process_chunk($chunk, &$context) {
        foreach($chunk as $configName) {
            $configData = \Drupal::configFactory()->get($configName)->getRawData();
            $ymlData = \Symfony\Component\Yaml\Yaml::dump($configData, 10, 2);

            $context['results']['configYmlFiles'][$configName] = $ymlData;
        }
    }

    public static function batch_operation_process_archive(&$context) {
        $archiveName = \Drupal::service('configuration_batch_export.helper')->getArchiveName();
        $zip = \Drupal::service('configuration_batch_export.helper')->createArchive($archiveName);

        foreach ($context['results']['configYmlFiles'] as $configName => $ymlData) {
            $zip->addFromString($configName . '.yml', $ymlData);
        }

        $zip->close();

        $context['results']['zipArchivePath'] = \Drupal::service('configuration_batch_export.helper')->getArchiveRealPath($archiveName);
    }

    public static function batch_operation_finished($success, $results, $operations) {
        $helperService = \Drupal::service('configuration_batch_export.helper');
        $messenger = \Drupal::messenger();
        if ($success) {
            $messenger->addMessage(t('Batch export of the entire configuration finished.'));
            
            $zipArchivePath = $results['zipArchivePath'];

            $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($zipArchivePath);
            $response->setContentDisposition(
                \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                basename($zipArchivePath)
            );

            $response->send();

            unlink($zipArchivePath);
        } else {
            $messenger->addMessage(t('An unknown error ocurred while exporting the website config.'), 'error');
        }
    }
}