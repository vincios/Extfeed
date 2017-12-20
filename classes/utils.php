<?php

class EXTFEED_CLASS_Utils
{
    private static $classInstance;


    public function __construct()
    {
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_Utils
     */
    public static function getInstance()
    {
        if( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    public function sanitizeUrl( $url )
    {
        $urlInfo = parse_url($url);

        if( empty($urlInfo['scheme']) )
        {
            $url = "http://" . $urlInfo['host'];
            $url = isset($urlInfo['path']) ? $url . $urlInfo['path'] : $url;
        }

        return $url;
    }
}