<?php

class EXTFEED_CLASS_ExtractorsManager
{
    private static $classInstance;

    private $postExtractors;

    public function __construct()
    {
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_ExtractorsManager
     */
    public static function getInstance()
    {
        if ( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    public function addExtractor( $formatName, $extractorClass )
    {
        $this->postExtractors[$formatName] = $extractorClass;
    }

    public function getExtractor( $format )
    {
        if( key_exists($format, $this->postExtractors) )
        {
            return OW::getClassInstance($this->postExtractors[$format]);
        }

        $event = new OW_Event(
            "extfeed.on_extractor_search",
            array('format' => $format)
        );

        OW::getEventManager()->trigger($event);
        $data = $event->getData();

        if( !empty($data) && isset($data['class']) )
        {
            $ex = OW::getClassInstance($data['class']);

            if( $data['format'] === $format && $ex instanceof EXTFEED_CLASS_PostExtractor )
            {
                $this->addExtractor($data['format'], $data['class']);
                return OW::getClassInstance($this->postExtractors[$format]);
            }
        }

        return null;
    }
}