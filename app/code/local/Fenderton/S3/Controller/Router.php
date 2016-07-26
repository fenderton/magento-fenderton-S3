<?php

class Fenderton_S3_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
    /**
     * Initialize Controller Router
     *
     * @param Varien_Event_Observer $observer
     */
    public function initControllerRouters($observer)
    {
        /* @var $front Mage_Core_Controller_Varien_Front */
        $front = $observer->getEvent()->getFront();

        $front->addRouter('sitemaps', $this);
    }

    /**
     * Validate and Match Cms Page and modify request
     *
     * @param Zend_Controller_Request_Http $request
     * @return bool
     */
    public function match(Zend_Controller_Request_Http $request)
    {
        $urlKey = $request->getPathInfo();
        if (!$urlKey) {
            return false;
        }
        if (!preg_match('#.*\.xml#',$urlKey)){
            return false;
        }
        if (!$this->isSitemapRequest($urlKey)){
            return false;
        }

        $request->setModuleName('sitemaps')
            ->setControllerName('index')
            ->setActionName('index')
            ->setParam('file', $urlKey);
        $request->setAlias(
            Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
            $urlKey
        );

        return true;
    }

    protected function isSitemapRequest($urlKey){
        $parts = explode('/', $urlKey);
        $fileName = array_pop($parts);
        $path = implode('/',$parts).'/';

        $sitemapCollection = Mage::getModel('sitemap/sitemap')->getResourceCollection();
        $sitemap = $sitemapCollection->addFieldToFilter('sitemap_filename', $fileName)
            ->addFieldToFilter('sitemap_path', $path)
            ->getFirstItem();

        return !$sitemap->isObjectNew();
    }
}
