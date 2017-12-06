<?php

class EXTFEED_EXTRACTOR_Empty extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        $content = array();

        /*if( $data['line'] != null )
        {
            $content['status'] = $data['line'];
        }*/
        return $content;
    }
}