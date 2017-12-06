<?php
class EXTFEED_EXTRACTOR_ImageList extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        $outContent = array('status'=>$this->findStatus($data));

        $photoIds = $data['dataExtras']['photoIdList'];

        /**
         * @var PHOTO_BOL_PhotoService $photoService
         */
        $photoService = PHOTO_BOL_PhotoService::getInstance();

        $photoList = $photoService->findPhotoListByIdList($photoIds, 1, 5);
        $photoOutList = array();

        foreach ($photoList as $photo){
            $photoDimension = json_decode($photo['dimension'], true);

            //$photoUrl = $photo['url'];
            $photoPreviewUrl = $photo['url'];
            $photoOutList[] = array(
                'photoId'=>$photo['id'],
                'photoTitle'=>$photo['description'],
                'photoPreviewDimensions'=>$photoDimension[PHOTO_BOL_PhotoService::TYPE_PREVIEW],
                //'photoUrl'=>$photoUrl,
                'photoPreviewUrl'=>$photoPreviewUrl
            );
        }
        $outContent['count'] = count($photoIds);
        $outContent['idList'] = $photoIds;
        $outContent['list'] = $photoOutList;
        return $outContent;
    }
}