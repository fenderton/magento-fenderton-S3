<?php

class Fenderton_S3_Model_Cms_Wysiwyg_Images_Storage_Collection extends Varien_Data_Collection_Filesystem
{
    protected $amazonS3Service;

    public function getS3()
    {
        if ($this->amazonS3Service === null) {
            $this->amazonS3Service = Mage::helper('fendertons3/s3');
        }

        return $this->amazonS3Service;
    }

    /**
     * Target directory setter. Adds directory to be scanned
     *
     * @param string $value
     * @return Varien_Data_Collection_Filesystem
     */
    public function addTargetDir($value)
    {
        $this->_targetDirs[$value] = (string)$value;

        return $this;
    }

    /**
     * Get files from specified directory recursively (if needed)
     *
     * @param string|array $dir
     */
    protected function _collectRecursive($dir)
    {
        $collectedResult = array();
        if (!is_array($dir)) {
            $dir = array($dir);
        }
        foreach ($dir as $folder) {
            if ($nodes = $this->getS3()->listFolder($folder)) {
                foreach ($nodes as $node) {
                    $collectedResult[] = $node;
                }
            }
        }
        if (empty($collectedResult)) {
            return;
        }

        foreach ($collectedResult as $item) {
            if (isset($item['basename'])) {
                if ((isset($item['is_dir']) && $item['is_dir'] === true) && ($item['basename'] != Fenderton_S3_Model_Cms_Wysiwyg_Images_Storage::THUMBS_DIRECTORY_NAME) && (!$this->_allowedDirsMask || preg_match($this->_allowedDirsMask, $item['basename']))) {
                    if ($this->_collectDirs) {
                        if ($this->_dirsFirst) {
                            $this->_collectedDirs[] = $item;
                        }
                        else {
                            $this->_collectedFiles[] = $item;
                        }
                    }
                }
                elseif ($this->_collectFiles && (isset($item['is_file']) && $item['is_file'] === true)
                    && (!$this->_allowedFilesMask || preg_match($this->_allowedFilesMask, $item['basename']))
                    && (!$this->_disallowedFilesMask || !preg_match($this->_disallowedFilesMask, $item['basename']))) {
                    $this->_collectedFiles[] = $item;
                }
            }
        }
    }

    /**
     * Generate item row basing on the filename
     *
     * @param string $filename
     * @return array
     */
    protected function _generateRow($filename)
    {
        $data = array(
            'filename' => $filename['basename'],
            'basename' => $filename['basename']
        );

        if (isset($filename['objectname'])) {
            $data['filename'] = $filename['objectname'];
            $data['objectname'] = $filename['objectname'];
        }

        if (isset($filename['mtime'])) {
            $data['mtime'] = $filename['mtime'];
        }

        return $data;
    }
}
