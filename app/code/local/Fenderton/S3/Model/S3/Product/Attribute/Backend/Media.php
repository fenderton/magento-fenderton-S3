<?php

class Fenderton_S3_Model_S3_Product_Attribute_Backend_Media extends Mage_Catalog_Model_Product_Attribute_Backend_Media
{
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
     * Move S3 bucket tmp object to its final location
     * core method override
     *
     * @param string $file
     * @return string
     */
    protected function _moveImageFromTmp($file)
    {
        if (strrpos($file, '.tmp') == strlen($file)-4) {
            $file = substr($file, 0, strlen($file)-4);
        }

        /* @var Fenderton_S3_Model_S3_Product_Media_Config $config */
        $config   = $this->_getConfig();
        $destFile = $this->_getUniqueFileName($file, '/');
        $src      = $config->getTmpMediaShortUrl($file);
        $dest     = $config->getMediaShortUrl($destFile);

        $this->getS3()->moveObject($src, $dest);

        return $destFile;
    }

    /**
     * Copy s3 bucket object
     * core method override
     *
     * @param string $file
     *
     * @return string
     *
     * @throws Mage_Core_Exception
     */
    protected function _copyImage($file)
    {
        /* @var Fenderton_S3_Model_S3_Product_Media_Config $config */
        $config = $this->_getConfig();
        try {
            $destFile = $this->_getUniqueFileName($file, '/');

            if (!$this->getS3()->isObjectAvailable($config->getMediaShortUrl($file))) {
                throw new Exception();
            }

            $src  = $config->getTmpMediaShortUrl($file);
            $dest = $config->getMediaShortUrl($destFile);

            if (!$this->getS3()->copyObject($src, $dest)) {
                throw new Exception();
            }
        } catch (Exception $e) {
            $file = $config->getMediaShortUrl($file);
            Mage::throwException(
                Mage::helper('catalog')->__('Failed to copy file %s. Please, delete media with non-existing images and try again.', $file)
            );
        }

        return $destFile;
    }

    /**
     * Generates uniqe object name
     *
     * @param string $file
     * @param string $dirsep
     *
     * @return string
     */
    protected function _getUniqueFileName($file, $dirsep)
    {
        $destFileName = $this->getNewFileName($destFile = $this->_getConfig()->getMediaShortUrl($file));

        return dirname($file) . $dirsep . $destFileName;
    }

    /**
     * Method override to enable amazon s3 upload in magento api create calls
     *
     * Shame that whole method must be overriden
     */
    public function addImage(Mage_Catalog_Model_Product $product, $file, $mediaAttribute = null, $move = false, $exclude = true)
    {
        $file = realpath($file);

        if (!$file || !file_exists($file)) {
            Mage::throwException(Mage::helper('catalog')->__('Image does not exist.'));
        }

        Mage::dispatchEvent('catalog_product_media_add_image', array('product' => $product, 'image' => $file));

        $pathinfo = pathinfo($file);
        $imgExtensions = array('jpg','jpeg','gif','png');
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $imgExtensions)) {
            Mage::throwException(Mage::helper('catalog')->__('Invalid image file type.'));
        }

        $fileName       = Mage_Core_Model_File_Uploader::getCorrectFileName($pathinfo['basename']);
        $dispretionPath = Mage_Core_Model_File_Uploader::getDispretionPath($fileName);
        $dispretionPath = str_replace(DS, '/', $dispretionPath);
        $fileName       = $dispretionPath . '/' . $fileName;
        $fileName       = $this->_getNotDuplicatedFilename($fileName, $dispretionPath);

        // amazon s3 upload
        try {
            $object = $this->_getConfig()->getTmpMediaShortUrl($fileName);
            $this->getS3()->putFile($file, $object);

            if ($move) {
                @unlink($file);
            }
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('catalog')->__('Failed to move file: %s', $e->getMessage()));
        }

        $fileName = str_replace(DS, '/', $fileName);

        $attrCode = $this->getAttribute()->getAttributeCode();
        $mediaGalleryData = $product->getData($attrCode);
        $position = 0;
        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = array(
                'images' => array()
            );
        }

        foreach ($mediaGalleryData['images'] as &$image) {
            if (isset($image['position']) && $image['position'] > $position) {
                $position = $image['position'];
            }
        }

        $position++;
        $mediaGalleryData['images'][] = array(
            'file'     => $fileName,
            'position' => $position,
            'label'    => '',
            'disabled' => (int) $exclude
        );

        $product->setData($attrCode, $mediaGalleryData);

        if (!is_null($mediaAttribute)) {
            $this->setMediaAttribute($product, $mediaAttribute, $fileName);
        }

        return $fileName;
    }

    /**
     * Method override to enable amazon s3 upload in magento api update calls
     * These method is used
     *
     * Shame that whole method must be overriden
     */
    public function updateImage(Mage_Catalog_Model_Product $product, $file, $data)
    {
        $config = $this->_getConfig();
        $path   = $config->getMediaPath($file);

        if (file_exists($path)) {
            $this->getS3()->putFile($path, $config->getMediaShortUrl($file));
            @unlink($path);
        }

        $fieldsMap = array(
            'label'    => 'label',
            'position' => 'position',
            'disabled' => 'disabled',
            'exclude'  => 'disabled'
        );

        $attrCode = $this->getAttribute()->getAttributeCode();

        $mediaGalleryData = $product->getData($attrCode);

        if (!isset($mediaGalleryData['images']) || !is_array($mediaGalleryData['images'])) {
            return $this;
        }

        foreach ($mediaGalleryData['images'] as &$image) {
            if ($image['file'] == $file) {
                foreach ($fieldsMap as $mappedField=>$realField) {
                    if (isset($data[$mappedField])) {
                        $image[$realField] = $data[$mappedField];
                    }
                }
            }
        }

        $product->setData($attrCode, $mediaGalleryData);
        return $this;
    }

    /**
     * Generating unique object names for magento media api calls
     *
     * @param string $fileName
     * @param string $dispretionPath
     *
     * @return string
     */
    protected function _getNotDuplicatedFilename($fileName, $dispretionPath)
    {
        $config = $this->_getConfig();
        $fileMediaName = $dispretionPath . '/'
            . $this->getNewFileName($config->getMediaShortUrl($fileName));
        $fileTmpMediaName = $dispretionPath . '/'
            . $this->getNewFileName($config->getTmpMediaShortUrl($fileName));

        if ($fileMediaName != $fileTmpMediaName) {
            if ($fileMediaName != $fileName) {
                return $this->_getNotDuplicatedFileName($fileMediaName, $dispretionPath);
            } elseif ($fileTmpMediaName != $fileName) {
                return $this->_getNotDuplicatedFilename($fileTmpMediaName, $dispretionPath);
            }
        }

        return $fileMediaName;
    }

    /**
     * Generating unique object names
     *
     * @param $file
     * @return string
     */
    protected function getNewFileName($file)
    {
        $fileInfo = pathinfo($file);
        if ($this->getS3()->isObjectAvailable($file)) {
            $index = 1;
            $baseName = $fileInfo['filename'] . '.' . $fileInfo['extension'];
            while($this->getS3()->isObjectAvailable($fileInfo['dirname'] . '/' . $baseName)) {
                $baseName = $fileInfo['filename']. '_' . $index . '.' . $fileInfo['extension'];
                $index ++;
            }
            $destFileName = $baseName;
        } else {
            $destFileName = $fileInfo['basename'];
        }

        return $destFileName;
    }


    public function afterSave($object)
    {
        if ($object->getIsDuplicate() == true) {
            $this->duplicate($object);
            return;
        }

        $attrCode = $this->getAttribute()->getAttributeCode();
        $value = $object->getData($attrCode);
        if (!is_array($value) || !isset($value['images']) || $object->isLockedAttribute($attrCode)) {
            return;
        }

        $storeId = $object->getStoreId();

        $storeIds = $object->getStoreIds();
        $storeIds[] = Mage_Core_Model_App::ADMIN_STORE_ID;

        // remove current storeId
        $storeIds = array_flip($storeIds);
        unset($storeIds[$storeId]);
        $storeIds = array_keys($storeIds);

        $images = Mage::getResourceModel('catalog/product')
            ->getAssignedImages($object, $storeIds);

        $picturesInOtherStores = array();
        foreach ($images as $image) {
            $picturesInOtherStores[$image['filepath']] = true;
        }

        $toDelete = array();
        $filesToValueIds = array();
        foreach ($value['images'] as &$image) {
            if(!empty($image['removed'])) {
                if(isset($image['value_id']) && $storeId == Mage_Core_Model_App::ADMIN_STORE_ID) {
                    $toDelete[] = $image['value_id'];
                    continue;
                }

                if(isset($image['value_id']) && !isset($picturesInOtherStores[$image['file']])) {
                    $toDelete[] = $image['value_id'];
                    continue;
                }

                Mage::throwException('Cannot Remove Image if Active in another Store.');
            }

            if(!isset($image['value_id'])) {
                $data = array();
                $data['entity_id']      = $object->getId();
                $data['attribute_id']   = $this->getAttribute()->getId();
                $data['value']          = $image['file'];
                $image['value_id']      = $this->_getResource()->insertGallery($data);
            }

            $this->_getResource()->deleteGalleryValueInStore($image['value_id'], $object->getStoreId());

            // Add per store labels, position, disabled
            $data = array();
            $data['value_id'] = $image['value_id'];
            $data['label']    = $image['label'];
            $data['position'] = (int) $image['position'];
            $data['disabled'] = (int) $image['disabled'];
            $data['store_id'] = (int) $object->getStoreId();

            $this->_getResource()->insertGalleryValueInStore($data);
        }

        $this->_getResource()->deleteGallery($toDelete);
    }

}