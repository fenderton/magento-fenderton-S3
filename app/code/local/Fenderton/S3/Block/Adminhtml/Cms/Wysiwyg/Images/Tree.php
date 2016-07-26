<?php

class Fenderton_S3_Block_Adminhtml_Cms_Wysiwyg_Images_Tree extends Mage_Adminhtml_Block_Cms_Wysiwyg_Images_Tree
{
    /**
     * Json tree builder
     *
     * @return string
     */
    public function getTreeJson()
    {
        $helper = Mage::helper('fendertons3/cms_wysiwyg_images');
        $collection = Mage::registry('storage')->getDirsCollection($helper->getCurrentPath());

        $jsonArray = array();
        foreach ($collection as $item) {
            $jsonArray[] = array(
                'text'  => $helper->getShortFilename($item->getBasename(), 20),
                'id'    => $helper->convertPathToId($item->getFilename()),
                'cls'   => 'folder'
            );
        }

        return Zend_Json::encode($jsonArray);
    }

    /**
     * Return tree node full path based on current path
     *
     * @return string
     */
    public function getTreeCurrentPath()
    {
        $treePath = '/root';
        if ($path = Mage::registry('storage')->getSession()->getCurrentPath()) {
            $helper = Mage::helper('fendertons3/cms_wysiwyg_images');
            $path = str_replace($helper->getStorageRoot(), '', $path);
            $relative = '';
            foreach (explode(DS, $path) as $dirName) {
                if ($dirName) {
                    $relative .= DS . $dirName;
                    $treePath .= '/' . $helper->idEncode($relative);
                }
            }
        }
        return $treePath;
    }
}
