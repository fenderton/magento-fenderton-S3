<?php

/**
 * Fenderton S3
 *
 * @category   Fenderton
 * @package    Fenderton_S3
 */
class Fenderton_S3_IndexController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        if (!$this->getRequest()->getParam('file')) {
            $this->_forward('defaultNoRoute');
        } else {
            $streamer = Mage::getModel('fendertons3/streamer');
            if ($streamer->checkFileFromS3($this->getRequest()->getParam('file'))) {
                // set headers and content;
                foreach ($streamer->headers as $key => $value) {
                    $this->getResponse()->setHeader($key, $value);
                }
                $this->getResponse()->setBody($streamer->getS3FileContents($this->getRequest()->getParam('file')));
            } else {
                $this->_forward('defaultNoRoute');
            }
        }
    }
}

