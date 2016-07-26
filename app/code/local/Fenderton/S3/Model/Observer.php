<?php

class Fenderton_S3_Model_Observer
{
    public function adminSystemConfigChangedFendertonS3(Varien_Event_Observer $observer)
    {
        // @todo get or put media to S3 based on setting (enabled|disabled)
    }
}
