<?php

class Fenderton_S3_Model_Cms_Wysiwyg_Images_Storage extends Mage_Cms_Model_Wysiwyg_Images_Storage
{
    const THUMBS_DIRECTORY_NAME = '_thumbs';

    protected $amazonS3Service;

    /**
     * @return Fenderton_S3_Helper_S3
     */
    public function getS3()
    {
        if ($this->amazonS3Service === null) {
            $this->amazonS3Service = Mage::helper('fendertons3/s3');
        }

        return $this->amazonS3Service;
    }

    /**
     * Storage collection
     *
     * @param string $path Path to the directory
     * @return Varien_Data_Collection_Filesystem
     */
    public function getCollection($path = null)
    {
        $collection = Mage::getModel('fendertons3/cms_wysiwyg_images_storage_collection');

        if ($path !== null) {
            $collection->addTargetDir($path);
        }
        return $collection;
    }

    /**
     * Create new directory in storage
     *
     * @param string $name New directory name
     * @param string $path Parent directory path
     * @throws Mage_Core_Exception
     * @return array New directory info
     */
    public function createDirectory($name, $path)
    {
        $newPath = rtrim($path, '/\\') . DS . $name;

        try {
            $this->getS3()->createFolder($newPath);

            if (Mage::helper('core/file_storage_database')->checkDbUsage()) {
                $relativePath = Mage::helper('core/file_storage_database')->getMediaRelativePath($newPath);
                Mage::getModel('core/file_storage_directory_database')->createRecursive($relativePath);
            }

            $result = array(
                'name'          => $name,
                'short_name'    => $this->getHelper()->getShortFilename($name),
                'path'          => $newPath,
                'id'            => $this->getHelper()->convertPathToId($newPath)
            );
            return $result;
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('cms')->__('Cannot create new directory.'));
        }
    }

    /**
     * Recursively delete directory from storage
     *
     * @param string $path Target dir
     * @return void
     */
    public function deleteDirectory($path)
    {
        $objectPath = str_replace($this->getHelper()->getStorageRoot(), '', $path);
        $thumbPath = rtrim($this->getThumbnailRoot(), '/\\') . '/' . $objectPath;

        $this->getS3()->removeFolder($path);

        if ($thumbPath != $this->getThumbnailRoot()) {
            $this->getS3()->removeFolder($thumbPath);
        }
    }

    /**
     * Delete file (and its thumbnail if exists) from storage
     *
     * @param string $target File path to be deleted
     * @return Mage_Cms_Model_Wysiwyg_Images_Storage
     */
    public function deleteFile($target)
    {
        $this->getS3()->removeMediaFile($target);

        Mage::helper('core/file_storage_database')->deleteFile($target);

        $thumb = $this->getHelper()->getStorageRoot() . self::THUMBS_DIRECTORY_NAME . DS . $this->getS3()->getMediaObjectName($target);
        if ($thumb) {
            $this->getS3()->removeMediaFile($thumb);
        }

        return $this;
    }


    /**
     * Upload and resize new file
     *
     * @param string $targetPath Target directory
     * @param string $type Type of storage, e.g. image, media etc.
     * @throws Mage_Core_Exception
     * @return array File info Array
     */
    public function uploadFile($targetPath, $type = null)
    {
        $uploader = new Mage_Core_Model_File_Uploader('image');
        if ($allowed = $this->getAllowedExtensions($type)) {
            $uploader->setAllowedExtensions($allowed);
        }
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $result = $uploader->save($targetPath);

        if (!$result) {
            Mage::throwException( Mage::helper('cms')->__('Cannot upload file.') );
        }
        $originFile = rtrim($result['path'], '/\\') . DS . $result['file'];
        // create thumbnail
        $thumbFile = $this->resizeFile($originFile, true);

        $result['cookie'] = array(
            'name'     => session_name(),
            'value'    => $this->getSession()->getSessionId(),
            'lifetime' => $this->getSession()->getCookieLifetime(),
            'path'     => $this->getSession()->getCookiePath(),
            'domain'   => $this->getSession()->getCookieDomain()
        );

        try {
            $this->getS3()->putMediaFile($originFile, $this->getHelper()->getStorageObjectName($originFile));

            if ($thumbFile) {
                $this->getS3()->putMediaFile($thumbFile, $this->getHelper()->getStorageObjectName($thumbFile));
            }
        } catch (Exception $e) {
            $result = false;
        }

        unlink($originFile);
        unlink($thumbFile);

        return $result;
    }

    /**
     * Thumbnail URL getter
     *
     * @param  string $filePath original file path
     * @param  boolean $checkFile OPTIONAL is it necessary to check file availability
     * @return string | false
     */
    public function getThumbnailUrl($filePath, $checkFile = false)
    {
        $filePath = ltrim($filePath, '/\\');
        $file = $this->getThumbsPath() . DS . substr($filePath, strpos($filePath, '/') + 1);

        return $this->getS3()->getObjectUrl($this->getS3()->getMediaObjectName($file));
    }

    /**
     * Return thumbnails directory path for file/current directory
     *
     * @param string|boolean $filePath Path to the file
     * @return string
     */
    public function getThumbsPath($filePath = false)
    {
        $mediaRootDir = Mage::getConfig()->getOptions()->getMediaDir();
        $thumbnailDir = $this->getThumbnailRoot();

        if ($filePath && strpos($filePath, $mediaRootDir) === 0) {
            $thumbnailDir .= dirname(substr($filePath, strlen($mediaRootDir)));
        }

        return $thumbnailDir;
    }

    /**
     * Media Storage Helper getter
     *
     * @return Fenderton_S3_Helper_Cms_Wysiwyg_Images
     */
    public function getHelper()
    {
        return Mage::helper('fendertons3/cms_wysiwyg_images');
    }

    /**
     * Thumbnail root directory getter
     *
     * @return string
     */
    public function getThumbnailRoot()
    {
        return $this->getHelper()->getStorageRoot() . self::THUMBS_DIRECTORY_NAME;
    }
}
