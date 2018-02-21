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

    /**
     * Put 'http://' in front of urls with no scheme. If param is an array, recursively sanitize each element
     *
     * @param $url string|array the url to sanitize or an array to sanitize recursively
     * @return array|string sanitized url (or array) or the input value (if isn't a valid url)
     */
    public function sanitizeUrl( $url )
    {
        if( is_array($url) )
        {
            foreach ( $url as $key => $value )
            {
                $url[$key] = $this->sanitizeUrl($value);
            }
            return $url;
        }

        $urlInfo = parse_url($url);

        if( !isset($urlInfo['host']) )
        {
            return $url;
        }
        if( empty($urlInfo['scheme']) )
        {
            $url = "http://" . $urlInfo['host'];
            $url = isset($urlInfo['path']) ? $url . $urlInfo['path'] : $url;
        }

        return $url;
    }

    public function errorMessage($message)
    {
        return array(
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => $message
        );
    }
}