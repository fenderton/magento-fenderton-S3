<?php

class Fenderton_S3_Model_Cms_Template_Filter extends Mage_Widget_Model_Template_Filter
{
    /**
     * Retrieve media file URL directive
     *
     * @param array $construction
     * @return string
     */
    public function mediaDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);

        return Mage::helper('fendertons3/s3')->getObjectUrl($params['url']);
    }
}
