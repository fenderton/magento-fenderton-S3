<?php

class Fenderton_S3_Model_S3_Observer
{
    /**
     * Event after image was uploaded for product gallery
     *
     * @param $event
     *
     * @return mixed
     */
    public function mediaGalleryUploaded($event)
    {
        $result = $event->getResult();
        if (!isset($result['error']) || ($result['error'] != 0)) {
            return;
        }
        /* @var Fenderton_S3_Model_S3_Product_Media_Config $config */
        $config = Mage::getSingleton('catalog/product_media_config');
        $file = $result['path'] . $result['file'];
        $object = $config->getTmpMediaShortUrl(str_replace(DS, '/', $result['file']));

        try {
            $this->getS3()->putFile($file, $object);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'debug_S3_upload.log');
        }
        @unlink($file);
    }

    /**
     * Amazon S3 helper
     *
     * @return Fenderton_S3_Helper_S3
     */
    public function getS3()
    {
        return Mage::helper('fendertons3/s3');
    }

    /**
     * Remove images from gallery marked as "removed"
     */
    public function mediaGalleryRemove(Varien_Event_Observer $observer)
    {
        $product = $observer->getProduct();
        $gallery = $product->getMediaGallery();
        $config = Mage::getModel('catalog/product_media_config');

        foreach ($gallery['images'] as $image) {
            if (isset($image['removed']) && $image['removed']) {
                $object = $config->getBaseMediaUrlAddition() . $image['file'];
                try {
                    $this->getS3()->removeObject($object);
                } catch (Exception $e) {
                    Mage::log($e->getMessage(), null, 'S3.log');
                }
            }
        }
    }

    /**
     * Removing all images from media gallery (during removing product)
     */
    public function removeMediaGallery(Varien_Event_Observer $observer)
    {
        $product = $observer->getProduct();
        $gallery = $product->getMediaGallery();
        $config = Mage::getModel('catalog/product_media_config');

        foreach ($gallery['images'] as $image) {
            $object = $config->getBaseMediaUrlAddition() . $image['file'];
            try {
                $this->getS3()->removeObject($object);
            } catch (Exception $e) {
                Mage::log($e->getMessage(), null, 'S3.log');
            }
        }
    }
}