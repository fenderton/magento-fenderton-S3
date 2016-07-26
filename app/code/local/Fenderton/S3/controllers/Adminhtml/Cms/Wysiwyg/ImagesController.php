<?php

require_once(Mage::getModuleDir('controllers','Mage_Adminhtml').DS.'Cms/Wysiwyg/ImagesController.php');

class Fenderton_S3_Adminhtml_Cms_Wysiwyg_ImagesController extends Mage_Adminhtml_Cms_Wysiwyg_ImagesController
{
    /**
     * Register storage model and return it
     *
     * @return Fenderton_S3_Model_Cms_Wysiwyg_Images_Storage
     */
    public function getStorage()
    {
        if (!Mage::registry('storage')) {
            $storage = Mage::getModel('fendertons3/cms_wysiwyg_images_storage');
            Mage::register('storage', $storage);
        }

        return Mage::registry('storage');
    }

    public function treeJsonAction()
    {
        try {
            $this->_initAction();
            $this->getResponse()->setBody(
                $this->getLayout()->createBlock('fendertons3/adminhtml_cms_wysiwyg_images_tree')
                     ->getTreeJson()
            );

        } catch (Exception $e) {
            echo $e;
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array()));
        }
    }

    /**
     * Delete file from media storage
     *
     * @return void
     */
    public function deleteFilesAction()
    {
        try {
            if (!$this->getRequest()->isPost()) {
                throw new Exception ('Wrong request.');
            }
            $files = Mage::helper('core')->jsonDecode($this->getRequest()->getParam('files'));

            /** @var $helper Mage_Cms_Helper_Wysiwyg_Images */
            $helper = Mage::helper('fendertons3/cms_wysiwyg_images');
            $path = $this->getStorage()->getSession()->getCurrentPath();
            foreach ($files as $file) {

                $file = $helper->idDecode($file);
                $_filePath = $path . $file;

                if (strpos($_filePath, realpath($path)) === 0 &&
                    strpos($_filePath, realpath($helper->getStorageRoot())) === 0
                ) {
                    $this->getStorage()->deleteFile($_filePath);
                }
            }
        } catch (Exception $e) {
            $result = array('error' => true, 'message' => $e->getMessage());
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }
    }

    /**
     * Fire when select image
     */
    public function onInsertAction()
    {
        $helper = Mage::helper('fendertons3/cms_wysiwyg_images');
        $storeId = $this->getRequest()->getParam('store');

        $filename = $this->getRequest()->getParam('filename');
        $filename = $helper->idDecode($filename);
        $asIs = $this->getRequest()->getParam('as_is');

        Mage::helper('catalog')->setStoreId($storeId);
        $helper->setStoreId($storeId);

        $image = $helper->getImageHtmlDeclaration($filename, $asIs);
        $this->getResponse()->setBody($image);
    }

    /**
     * Save current path in session
     *
     * @return Fenderton_S3_Adminhtml_Cms_Wysiwyg_ImagesController
     */
    protected function _saveSessionCurrentPath()
    {
        $currentPath = Mage::helper('fendertons3/cms_wysiwyg_images')->getCurrentPath();

        $this->getStorage()
             ->getSession()
             ->setCurrentPath($currentPath);

        return $this;
    }
}
