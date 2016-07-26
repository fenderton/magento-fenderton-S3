<?php
/**
 * Asset observer
 */

class Fenderton_S3_Model_Asset_Observer
{
    /**
     * S3 Helper
     * @return Fenderton_S3_Helper_S3
     */
    protected function getS3()
    {
        return Mage::helper('fendertons3/s3');
    }

    /**
     * Check if integration is enabled
     * @return int
     */
    protected function isEnabled()
    {
        return (int)Mage::getStoreConfig('fendertons3/assets/enabled');
    }

    /**
     * Event: asset_file_minified
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function uploadAssetFile(Varien_Event_Observer $observer)
    {
        if ($this->isEnabled()) {
            $file = $observer->getFile();
            $basePath = rtrim(Mage::getBaseDir(), '/') . '/';
            $path = str_replace($basePath, '', $file);
            $this->getS3()->putFile($file, $path);
            $this->getS3()->putCompressedFile($file, $path . '.gz');
        }
    }
}