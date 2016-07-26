<?php

class Fenderton_S3_Helper_Cms_Wysiwyg_Images extends Mage_Cms_Helper_Wysiwyg_Images
{
    /**
     * Images Storage base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return Mage::getBaseUrl('media') . '/';
    }

    /**
     * Images Storage root directory
     *
     * @return string
     */
    public function getStorageRoot()
    {
        return Mage::getConfig()->getOptions()->getMediaDir() . DS . Mage_Cms_Model_Wysiwyg_Config::IMAGE_DIRECTORY . DS;
    }

    /**
     * Images Storage root directory
     *
     * @return string
     */
    public function getMediaRoot()
    {
        return dirname(Mage::getConfig()->getOptions()->getMediaDir()) . DS;
    }

    /**
     * Return path of the current selected directory or root directory for startup
     * Try to create target directory if it doesn't exist
     *
     * @throws Mage_Core_Exception
     * @return string
     */
    public function getCurrentPath()
    {
        if (!$this->_currentPath) {
            $currentPath = $this->getStorageRoot();
            $path = $this->_getRequest()->getParam($this->getTreeNodeName());
            $path = ($path == 'root') ? false : $path;

            if ($path) {
                $currentPath = $this->convertIdToPath($path);
            }

            $io = new Varien_Io_File();
            if (!$io->isWriteable($currentPath) && !$io->mkdir($currentPath)) {
                $message = Mage::helper('cms')->__('The directory %s is not writable by server.',$currentPath);
                Mage::throwException($message);
            }

            $this->_currentPath = $currentPath;
        }

        return $this->_currentPath;
    }

    /**
     * Return URL based on current selected directory or root directory for startup
     *
     * @return string
     */
    public function getCurrentUrl()
    {
        if (!$this->_currentUrl) {

            $path = $this->getStorageObjectName($this->getCurrentPath());
            $path = trim($path, DS);

            $this->_currentUrl = $this->convertPathToUrl($path) . '/';
        }

        return $this->_currentUrl;
    }

    /**
     * Prepare Image insertion declaration for Wysiwyg or textarea(as_is mode)
     *
     * @param string $filename Filename transferred via Ajax
     * @param bool $renderAsTag Leave image HTML as is or transform it to controller directive
     * @return string
     */
    public function getImageHtmlDeclaration($filename, $renderAsTag = false)
    {
        $objectname = $this->getCurrentUrl() . $filename;

        $fileurl = Mage::helper('fendertons3/s3')->getObjectUrl($objectname);
        $directivepath = Mage::helper('fendertons3/s3')->stripMediaObjectName($objectname);
        $directive = sprintf('{{media url="%s"}}', $directivepath);

        if ($renderAsTag) {
            $html = sprintf('<img src="%s" alt="" />', $this->isUsingStaticUrlsAllowed() ? $fileurl : $directive);
        } else {
            if ($this->isUsingStaticUrlsAllowed()) {
                $html = $fileurl; // $mediaPath;
            } else {
                $directive = Mage::helper('core')->urlEncode($directive);
                $html = Mage::helper('adminhtml')->getUrl('*/cms_wysiwyg/directive', array('___directive' => $directive));
            }
        }
        return $html;
    }

    /**
     * Storage model singleton
     *
     * @return Mage_Cms_Model_Page_Wysiwyg_Images_Storage
     */
    public function getStorage()
    {
        return Mage::getSingleton('fendertons3/cms_wysiwyg_images_storage');
    }

    /**
     * @param  string $filename
     * @return mixed
     */
    public function getStorageObjectName($filename)
    {
        return str_replace($this->getMediaRoot(), '', $filename);
    }
}
