<?php

class EXTFEED_EXTRACTOR_Video extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent( $data )
    {
        $content = $data['content']['vars'];
        $outContent = array(
            'status' => $this->findStatus($data),
            'title' => $content['title'],
            'description' => $content['description'],
            'image' => $content['image'],
            'embed' => $content['embed'],
            'src' => null
        );


        $dom = new DOMDocument();
        $dom->loadHTML($content['embed']);
        $frame = $dom->getElementsByTagName('iframe')->item(0);

        if( $frame !== null )
        {
            $outContent['src'] = $frame->getAttribute('src');
        }

        return $outContent;
    }
}