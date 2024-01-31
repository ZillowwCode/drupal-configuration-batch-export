<?php

namespace Drupal\configuration_batch_export\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

class HelperService {

    protected $configFactory;
    protected $ARCHIVE_PATH;

    public function __construct(ConfigFactoryInterface $configFactory) {
        $this->configFactory = $configFactory;

        $this->ARCHIVE_PATH = 'private://config_export/';
    }

    public function prepareExportFolder() {
        if (!file_exists($this->ARCHIVE_PATH)) {
            mkdir($this->ARCHIVE_PATH, 0777, true);
        }
    }

    public function getArchiveName() {
        $siteName = $this->configFactory->get('system.site')->get('name');
        $siteName = str_replace(' ', '_', $siteName);
        $date = date('Y-m-d_H-i-s');

        return $siteName . '_' . $date . '.zip';
    }

    public function getArchiveRealPath($archiveName) {
        $realpath = \Drupal::service('file_system')->realpath($this->ARCHIVE_PATH);

        return $realpath . '/' . $archiveName;
    }

    public function getArchivePath() {
        return $this->ARCHIVE_PATH . '/' . $this->getArchiveName();
    }

    public function createArchive($archiveName = 'config.zip') {
        $this->prepareExportFolder();

        $realpath = \Drupal::service('file_system')->realpath($this->ARCHIVE_PATH);
        $zip = new \ZipArchive();
        $success = $zip->open($realpath . '/' . $archiveName, \ZipArchive::CREATE);
    
        if (!$success) {
            \Drupal::logger('configuration_batch_export')->error('Cannot create zip archive in ' . $this->ARCHIVE_PATH . '/' . $archiveName);
            throw new \Exception("Cannot create zip archive");
        }

        return $zip;
    }

    public function getArchive() {
        $realpath = \Drupal::service('file_system')->realpath($this->ARCHIVE_PATH);
        $zip = new \ZipArchive();
        $success = $zip->open($realpath . '/' . $this->getArchiveName(), \ZipArchive::CREATE);

        return $zip;
    }

    public function getConfigNames() {
        return $this->configFactory->listAll();
    }

    public function getConfigData($configName) {
        $config = $this->configFactory->getEditable($configName);
        $configData = $config->getRawData();

        return $configData;
    }
}