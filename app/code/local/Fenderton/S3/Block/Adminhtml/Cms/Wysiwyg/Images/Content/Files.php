<?php

/**
 * Directory contents block for Wysiwyg Images
 */
class Fenderton_S3_Block_Adminhtml_Cms_Wysiwyg_Images_Content_Files extends Mage_Adminhtml_Block_Cms_Wysiwyg_Images_Content_Files
{
    /**
     * Files collection object
     *
     * @var Varien_Data_Collection_Filesystem
     */
    protected $_filesCollection;

    /**
     * Prepared Files collection for current directory
     *
     * @return Varien_Data_Collection_Filesystem
     */
    public function getFiles()
    {
        if (!$this->_filesCollection) {
            $this->_filesCollection = Mage::getSingleton('fendertons3/cms_wysiwyg_images_storage')->getFilesCollection(
                Mage::helper('fendertons3/cms_wysiwyg_images')->getCurrentPath(),
                $this->_getMediaType()
            );
        }

        return $this->_filesCollection;
    }
}
