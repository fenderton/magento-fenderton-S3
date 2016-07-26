<?php

class Fenderton_S3_Helper_Image extends Varien_Image
{
    /**
     * Method overwritten to accept http images
     *
     * @throws Exception
     */
    public function open()
    {
        $this->_getAdapter()->checkDependencies();

        $isUrl = (0 === strpos($this->_fileName, 'http://')) || (0 === strpos($this->_fileName, 'https://'));
        if(!$isUrl && !file_exists($this->_fileName)) {
            throw new Exception("File '{$this->_fileName}' does not exists.");
        }
        if ($isUrl) {
            @$info = getimagesize($this->_fileName);
            if (false === $info) {
                throw new Exception("File '{$this->_fileName}' does not exists.");
            }
        }

        $this->_getAdapter()->open($this->_fileName);
    }

}