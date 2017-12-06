<?php
class EXTFEED_EXTRACTOR_ImageListNew extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        $outContent = array('status'=>$this->findStatus($data));

        $photoIds = $data['dataExtras']['photoIdList'];
        $photoList = $data['content']['vars']['list'];

        $photoOutList = array();

        //Fetch all photos info from DB is an expensive task, so for photo_list we use only the information provided in $data array
        //and for the dimension we use the default size of photo preview defined by PhotoService
        $photoDimension = array(PHOTO_BOL_PhotoService::DIM_PREVIEW_WIDTH, PHOTO_BOL_PhotoService::DIM_PREVIEW_HEIGHT);
        foreach ($photoList as $photo){

            //$photoUrl = $photo['url'];
            $photoOutList[] = array(
                'photoId'=>$photo['url']['vars']['id'],
                'photoTitle'=>$photo['title'],
                'photoPreviewDimensions'=>$photoDimension,
                //'photoUrl'=>$photoUrl,
                'photoPreviewUrl'=>$photo['image']
            );
        }
        $outContent['count'] = count($photoIds);
        $outContent['idList'] = $photoIds;
        $outContent['list'] = $photoOutList;
        return $outContent;
    }
}