<?php

class Fenderton_S3_Model_S3_Product_Image extends Mage_Catalog_Model_Product_Image
{
    const CACHE_PREFIX = 'aws_s3_';
    const CACHE_GROUP = 'catalog_product_media_image_local_cache';

    /**
     * Checks if cache file is uploaded to amazon s3
     *
     * @return bool
     */
    public function isCached()
    {
        return $this->isImageAvailable($this->getBaseNewFile());
    }

    /**
     * Checks if cache file is in local cache
     *
     * @return bool
     */
    public function isImageAvailable($path)
    {
        // Build Cache Key
        $cacheKey = self::CACHE_PREFIX . md5($path);
        $cache = Mage::app()->getCacheInstance();

        // Check local cache for availability
        $localCache = $cache->load($cacheKey);
        if($localCache) return true;

        // If availability is not cached locally check and cache
        $objectAvailable = $this->getS3()->isObjectAvailable($path);
        if($objectAvailable) $cache->save($objectAvailable,$cacheKey,array(self::CACHE_GROUP),60*60);
        return $objectAvailable;
    
    }
    

    /**
     * Method overwritten to check amazon s3 bucket contents
     *
     * @param string $file
     * @return Fenderton_S3_Model_S3_Product_Image
     * @throws Exception
     */
    public function setBaseFile($file)
    {
        /* @var Fenderton_S3_Model_S3_Product_Media_Config $config */
        $config  = Mage::getSingleton('catalog/product_media_config');
        $fileUrl = null;
        $this->_isBaseFilePlaceholder = false;

        if (($file) && (0 !== strpos($file, '/', 0))) {
            $file = '/' . $file;
        }
        $baseDir = $config->getBaseMediaPath();

        if ('/no_selection' == $file) {
            $file = null;
        }
        if ($file) {
            $fileUrl = $config->getBaseMediaUrl() . $file;
        }

        $objectExists = $this->isImageAvailable($config->getBaseMediaUrlAddition() . $file);
        if ($file) {
            if ((!$objectExists) || !$this->_checkMemory($fileUrl)) {
                $file = null;
            }
        }

        if (!$file) {
            // check if placeholder defined in config
            $isConfigPlaceholder = Mage::getStoreConfig("catalog/placeholder/{$this->getDestinationSubdir()}_placeholder");
            $configPlaceholder   = '/placeholder/' . $isConfigPlaceholder;
            if ($isConfigPlaceholder && $this->_fileExists($baseDir . $configPlaceholder)) {
                $file = $configPlaceholder;
            }
            else {
                // replace file with skin or default skin placeholder
                $skinBaseDir     = Mage::getDesign()->getSkinBaseDir();
                $skinPlaceholder = "/images/catalog/product/placeholder/{$this->getDestinationSubdir()}.jpg";
                $file = $skinPlaceholder;
                if (file_exists($skinBaseDir . $file)) {
                    $baseDir = $skinBaseDir;
                }
                else {
                    $baseDir = Mage::getDesign()->getSkinBaseDir(array('_theme' => 'default'));
                    if (!file_exists($baseDir . $file)) {
                        $baseDir = Mage::getDesign()->getSkinBaseDir(array('_theme' => 'default', '_package' => 'base'));
                    }
                }
            }
            $this->_isBaseFilePlaceholder = true;
        }

        $baseFile = $baseDir . $file;

        if (!$file) {
            throw new Exception(Mage::helper('catalog')->__('Image file was not found.'));
        }
        if (!$this->_isBaseFilePlaceholder) {
            if (!$objectExists) {
                throw new Exception(Mage::helper('catalog')->__('Image file was not found.'));
            }
            $baseFile = $fileUrl;
        } else if (!file_exists($baseFile)) {
            throw new Exception(Mage::helper('catalog')->__('Image file was not found.'));
        }

        $this->_baseFile = $baseFile;

        // build new filename (most important params)
        $path = array(
            $config->getBaseMediaPath(),
            'cache',
            Mage::app()->getStore()->getId(),
            $path[] = $this->getDestinationSubdir()
        );
        if((!empty($this->_width)) || (!empty($this->_height))) {
            $path[] = "{$this->_width}x{$this->_height}";
        }

        // add misk params as a hash
        $miscParams = array(
            ($this->_keepAspectRatio  ? '' : 'non') . 'proportional',
            ($this->_keepFrame        ? '' : 'no')  . 'frame',
            ($this->_keepTransparency ? '' : 'no')  . 'transparency',
            ($this->_constrainOnly ? 'do' : 'not')  . 'constrainonly',
            $this->_rgbToString($this->_backgroundColor),
            'angle' . $this->_angle,
            'quality' . $this->_quality
        );

        // if has watermark add watermark params to hash
        if ($this->getWatermarkFile()) {
            $miscParams[] = $this->getWatermarkFile();
            $miscParams[] = $this->getWatermarkImageOpacity();
            $miscParams[] = $this->getWatermarkPosition();
            $miscParams[] = $this->getWatermarkWidth();
            $miscParams[] = $this->getWatermarkHeigth();
        }

        if($this->getCdnVersion())
                $miscParams[] = $this->getCdnVersion();

        $path[] = md5(implode('_', $miscParams));

        // append prepared filename
        $this->_newFile = implode('/', $path) . $file; // the $file contains heading slash
        
        return $this;
    }

    /**
     * Grabs CDN Verision from config, used for versioning the media for CDN
     *
     * @return string
     */
    protected function getCdnVersion(){
        if(!Mage::getConfig()->getNode('global/cdn_version')) return false;
        $node = Mage::getConfig()->getNode('global/cdn_version')->asArray();
        return (string)$node['major'] . "." . (string)$node['minor'];  
    }

    /**
     * Generates url to catalog product image file
     * Urls are generated to amazon s3
     *
     * @return string
     */
    public function getUrl()
    {
        $path = $this->getBaseNewFile();

        return $this->getS3()->getAmazonBucketUrl() . '/' . $path;
    }

    /**
     * Returns path to cached file
     *
     * @return string
     */
    protected function getBaseNewFile()
    {
        $baseDir = Mage::getBaseDir('media');
        $path    = str_replace($baseDir . DS, 'media'.DS, $this->_newFile);
        $path    = str_replace(DS, '/', $path);

        return $path;
    }

    /**
     * Saved thumbnail file is sent to amazon s3
     *
     * @return Fenderton_S3_Model_S3_Product_Image
     */
    public function saveFile()
    {
        $filename = $this->getNewFile();
        $this->getImageProcessor()->save($filename);
        $this->getS3()->putFile($filename, $this->getBaseNewFile());
        @unlink($filename);

        return $this;
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
     * Change Varien_Image to accept url image files
     *
     * @return Fenderton_S3_Helper_Image
     */
    public function getImageProcessor()
    {
        if (!$this->_processor) {
            $this->_processor = new Fenderton_S3_Helper_Image($this->getBaseFile());
        }
        $this->_processor->keepAspectRatio($this->_keepAspectRatio);
        $this->_processor->keepFrame($this->_keepFrame);
        $this->_processor->keepTransparency($this->_keepTransparency);
        $this->_processor->constrainOnly($this->_constrainOnly);
        $this->_processor->backgroundColor($this->_backgroundColor);

        if (Mage::helper('core')->isModuleEnabled('CD_Flatsome')
            && Mage::helper('flatsome/imagick')->isEnabled()
        ) {
            $this->_processor->quality(Mage::helper('flatsome/imagick')->getQuality());
        } else {
            $this->_processor->quality($this->_quality);
        }

        return $this->_processor;
    }

    /**
     * Clearing catalog image cache from amazon s3
     */
    public function clearCache()
    {
        /* @var Fenderton_S3_Model_S3_Product_Media_Config $config */
        $config = Mage::getSingleton('catalog/product_media_config');
        $prefix =  $config->getBaseMediaUrlAddition().'/cache/';

        $this->getS3()->removeObjects($prefix);
    }

}