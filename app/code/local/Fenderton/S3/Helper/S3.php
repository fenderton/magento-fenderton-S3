<?php

class Fenderton_S3_Helper_S3 extends Mage_Core_Helper_Abstract
{
    /**
     * Amazon S3 admin config prefix
     */
    const CONFIG_PREFIX = 'fendertons3/s3/';

    /**
     * @var Zend_Service_Amazon_S3
     */
    protected $service;


    /**
     * Retrieves configuration value for given key
     *
     * @param string $key
     * @return mixed
     */
    public function getConfig($key)
    {
        return Mage::getStoreConfig(self::CONFIG_PREFIX . $key);
    }

    /**
     * Name of the secure cdn base url
     * @return string
     */
    public function getSecureURL()
    {
        $retval = $this->getConfig('cdn_secure_base_url');

        return $retval;
    }

    /**
     * Name of the UnSecure cdn base url
     * @return string
     */
    public function getUnSecureURL()
    {
        $retval = $this->getConfig('cdn_unsecure_base_url');

        return $retval;
    }

    /**
     * Generates url for amazon bucket
     * @param bool $skipCdn
     * @return string
     */
    public function getAmazonBucketUrl($skipCdn = false)
    {
        if(Mage::app()->getStore()->isCurrentlySecure()){

            $baseUrl = "s3.amazonaws.com";

            if($this->getSecureURL() && !$skipCdn)
                return sprintf('https://%s', $this->getSecureURL());

            return sprintf('https://%s/%s', $baseUrl, $this->getBucketName());

        }else{
            if($this->getUnSecureURL() && !$skipCdn){
                return sprintf('http://%s', $this->getUnSecureURL());
            }
            return sprintf('http://%s.s3.amazonaws.com', $this->getBucketName());
        }
    }

    /**
     * Lazy load Amazon S3 Service
     *
     * @return Zend_Service_Amazon_S3
     */
    public function getService()
    {
        if ($this->service === null) {
            $key     = $this->getConfig('awskey');
            $secret  = $this->getConfig('awssecret');

            $service = new Zend_Service_Amazon_S3($key, $secret);

            $this->service = $service;
        }

        return $this->service;
    }

    /**
     * Name of amazon s3 bucket
     * @return string
     */
    public function getBucketName()
    {
        return $this->getConfig('awsbucket');
    }

    /**
     * Returns content of object
     *
     * @param string $object
     *
     * @return false|string
     */
    public function getObject($object)
    {
        return $this->getService()->getObject($this->getObjectFullName($object));
    }

    /**
     * Checks if given object name exists on amazon s3
     *
     * @param string $object
     * @return bool
     */
    public function isObjectAvailable($object)
    {
        return $this->getService()->isObjectAvailable($this->getObjectFullName($object));
    }

    /**
     * Setting default acl to public read for newly uploaded files
     * These header is needed when we want to use public amazon s3 urls
     *
     * @param array $meta
     * @return array
     */
    protected function generateUploadMeta($meta = null)
    {
        $default = array(
            Zend_Service_Amazon_S3::S3_ACL_HEADER => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ,
            'Cache-Control' => 'max-age=691200, public, must-revalidate, proxy-revalidate'
        );
        if (is_array($meta)) {
            $meta = array_merge($default, $meta);
        } else {
            $meta = $default;
        }

        return $meta;
    }

    /**
     * Put contents into bucket
     *
     * @param string $object name of object
     * @param string $data content of object
     * @param array $meta
     *
     * @return bool
     */
    public function putObject($object, $data, $meta = null)
    {
        return $this->getService()->putObject($this->getObjectFullName($object), $data, $this->generateUploadMeta($meta));
    }

    /**
     * Put file into bucket
     *
     * @param string $path to file [/absolute/path/to/file.jpg]
     * @param string $object name of object [path/to/file.jpg] that is the location on s3 bucket.
     * @param array $meta
     *
     * @return bool
     */
    public function putFile($path, $object, $meta=null)
    {
        $ret = null;
        try {
            $ret = $this->getService()->putFile($path, $this->getObjectFullName($object), $this->generateUploadMeta($meta));
        } catch (Exception $e) {
            Mage::log( $e->getMessage() , null, 'debug_S3_upload.log');
        }
        return $ret;
    }

    /**
     * Put file into bucket
     *
     * @param string $path to file [/absolute/path/to/file.jpg]
     * @param string $object name of object [path/to/file.jpg] that is the location on s3 bucket.
     * @param array $meta
     *
     * @return bool
     */
    public function putCompressedFile($path, $object, $meta=null)
    {
        $ret = null;
        try {
            $ret = $this->getService()->putFile($path, $this->getObjectFullName($object), $this->generateUploadMeta($meta), true);
        } catch (Exception $e) {
            Mage::log($e->getMessage() , null, 'debug_S3_upload.log');
        }
        return $ret;
    }

    /**
     * Remove object from bucket
     *
     * @param string $object
     *
     * @return bool
     */
    public function removeObject($object)
    {
        return $this->getService()->removeObject($this->getObjectFullName($object));
    }

    /**
     * Move object in S3 bucket
     * Longer version of move (get,put,remove) [could be optimized]
     *
     * @param string $objectSrc
     * @param string $objectDest
     *
     * @return bool
     */
    public function moveObject($objectSrc, $objectDest)
    {
        $content = $this->getObject($objectSrc);
        if (false === $content) {
            return false;
        }

        $result = $this->putObject($objectDest, $content);
        $this->removeObject($objectSrc);

        return $result;
    }

    /**
     * Copy object in S3 bucket
     * Longer version of move (get,put) [could be optimized]
     *
     * @param string $objectSrc
     * @param string $objectDest
     *
     * @return bool
     */
    public function copyObject($objectSrc, $objectDest)
    {
        $content = $this->getObject($objectSrc);
        if (false === $content) {
            return false;
        }
        $result = $this->putObject($objectDest, $content);

        return $result;
    }

    /**
     * Retrieves list of objects with given prefix
     *
     * @param string $prefix
     *
     * @return array
     */
    public function getObjectsByBucket($prefix = null)
    {
        $params = array();
        if (null !== $prefix) {
            $params['prefix'] = $prefix;
        }

        return $this->getService()->getObjectsByBucket($this->getBucketName(), $params);
    }

    /**
     * Removes objects with given prefix
     *
     * @param string $prefix
     */
    public function removeObjects($prefix = null)
    {
        $objects = $this->getObjectsByBucket($prefix);

        foreach($objects as $object)
        {
            $this->removeObject($object);
        }
    }

    /**
     * Generates full name of object
     * Adding bucket name before object name
     *
     * @param string $object
     *
     * @return string
     */
    protected function getObjectFullName($object)
    {
        return $this->getBucketName() . '/' . $object;
    }

    /**
     * Generates media object name (relative path to file as S3 filename)
     *
     * @param  string $mediaObjectPath
     * @return string
     */
    public function getMediaObjectName($mediaObjectPath)
    {
        return trim(str_replace(dirname(Mage::getConfig()->getOptions()->getMediaDir()), '', $mediaObjectPath), '/\\');
    }

    /**
     * Strips media path from object name (relative path to file as S3 filename)
     *
     * @param  string $mediaObjectPath
     * @return string
     */
    public function stripMediaObjectName($mediaObjectPath)
    {
        $appMediaDir = explode('/',Mage::getConfig()->getOptions()->getMediaDir());
        $mediaDir = array_pop($appMediaDir);
        return trim(str_replace($mediaDir, '', $mediaObjectPath), '/\\');
    }

    /**
     * @param  string $mediaObjectPath
     * @return string
     */
    public function getMediaObjectFullName($mediaObjectPath)
    {
        return $this->getObjectFullName($this->getMediaObjectName($mediaObjectPath));
    }

    /**
     * @param  string $filepath
     * @return bool
     */
    public function putMediaFile($filepath, $objectname)
    {
        $objectname = $this->getObjectFullName($objectname);
        $meta = $this->generateUploadMeta();

        return $this->getService()->putFile($filepath, $objectname, $meta);
    }

    /**
     * @param  string $filepath
     * @return bool
     */
    public function removeMediaFile($filepath)
    {
        $object = $this->getMediaObjectFullName($filepath);

        return $this->getService()->removeObject($object);
    }

    /**
     * @param  string $folder
     * @return array|bool
     */
    public function listFolder($folder, $all = null)
    {

        $prefix = trim($this->getMediaObjectName($folder), '/\\') . '/';
        $params = array('delimiter' => '/', 'prefix' => $prefix);
        if ($all === true) {
            unset($params['delimiter']);
        }

        $response = $this->getService()->_makeRequest('GET', $this->getBucketName(), $params);

        if ($response->getStatus() != 200) {
            return false;
        }

        $xml = new SimpleXMLElement($response->getBody());

        $objects = array();
        $delimiter = (string) $xml->Delimiter;

        if (isset($xml->Contents)) { // Files
            foreach ($xml->Contents as $contents) {
                $key = (string) $contents->Key;
                $basename = substr($key, strrpos($key, $delimiter) + 1);
                $mtime = (string) $contents->LastModified;

                $objects[] = array('is_file' => true, 'basename' => $basename, 'objectname' => $key, 'mtime' => $mtime);
            }
        }

        if (isset($xml->CommonPrefixes)) { // Dirs
            foreach ($xml->CommonPrefixes as $commonPrefixes) {
                $dir = (string) $commonPrefixes->Prefix;
                $basename = str_replace($prefix, '', $dir);

                if (!in_array($basename, array('/', '\\'))) {
                    $objects[] = array('is_dir' => true, 'basename' => rtrim($basename, '/\\'));
                }
            }
        }

        return $objects;
    }

    /**
     * @param  string $folder
     * @return bool
     */
    public function createFolder($folder)
    {
        $object = $this->getMediaObjectFullName($folder) . '/';

        return $this->getService()->putObject($object, null);
    }

    /**
     * @param string $folder
     */
    public function removeFolder($folder)
    {
        $prefix = $this->getMediaObjectName($folder);
        $objects = $this->getService()->getObjectsByBucket($this->getBucketName(), array('prefix' => $prefix));

        foreach ($objects as $object) {
            $this->removeObject($object);
        }
    }

    /**
     * @param  string $objectname
     * @return string
     */
    public function getObjectUrl($objectname)
    {
        return $this->getAmazonBucketUrl() . '/' . $objectname;
    }
}
