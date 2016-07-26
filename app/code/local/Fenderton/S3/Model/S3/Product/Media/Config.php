<?php

/**
 * Overwritten methods adds amazon s3 value to paths/urls
 */
class Fenderton_S3_Model_S3_Product_Media_Config extends Mage_Catalog_Model_Product_Media_Config
{
    public function getBaseTmpMediaUrl()
    {
        return $this->getS3()->getAmazonBucketUrl() . '/' . $this->getBaseTmpMediaUrlAddition();
    }

    public function getBaseMediaUrl()
    {
        return $this->getS3()->getAmazonBucketUrl() . '/' . $this->getBaseMediaUrlAddition();
    }

    /**
     * @return Fenderton_S3_Helper_S3
     */
    public function getS3()
    {
        return Mage::helper('fendertons3/s3');
    }

    /**
     * renloe: overridding default method that only returned 'catalog/product' :(
     */
    public function getBaseMediaUrlAddition()
    {
        return 'media/catalog/product';
    }

    public function getBaseTmpMediaUrlAddition()
    {
        return 'media/tmp/catalog/product';
    }
}