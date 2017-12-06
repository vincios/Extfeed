<?php
class EXTFEED_EXTRACTOR_ImageContent extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        $dataContent = $data['content']['vars'];
        $outContent = array();

        foreach ( $dataContent as $key=>$value )
        {
            if ( $key == "url" )
            {
                if( is_array($value) )
                {
                    $outContent['urlEncoded'] = $value;
                    $outContent['url'] = $this->decodeRoutingInfo($value);
                }
                else
                {
                    $outContent['url'] = $value;
                }
            }
            else
            {
                $outContent[$key] = $value;
            }
        }

        return $outContent;
    }
}