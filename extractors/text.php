<?php

class EXTFEED_EXTRACTOR_Text extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        return array('status'=>$this->findStatus($data));
    }
}