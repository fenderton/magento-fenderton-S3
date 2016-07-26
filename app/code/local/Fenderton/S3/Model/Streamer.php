<?php

class Fenderton_S3_Model_Streamer extends Mage_Core_Model_Abstract
{

    public $headers;

    /**
     * @param $path - relative path to S3 object
     * @output file from s3
     */
    public function checkFileFromS3($path)
    {
        $filePath = $this->getS3()->getAmazonBucketUrl(true);
        $completePath = $filePath . $path;

        try {
            $this->headers = get_headers($completePath, 1);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        if (strpos($this->headers[0], '200') !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param $path
     * @return string file contents
     */
    public function getS3FileContents($path)
    {
        $filePath = $this->getS3()->getAmazonBucketUrl(true);
        $completePath = $filePath . $path;

        $ctx = stream_context_create(
            array('http' =>
                array(
                    'timeout' => 1, // Seconds
                    'follow_location' => 0
                )
            )
        );

        return file_get_contents($completePath, false, $ctx);
    }

    /**
     * @return Fenderton_S3_Helper_S3
     */
    public function getS3()
    {
        return Mage::helper('fendertons3/s3');
    }
}