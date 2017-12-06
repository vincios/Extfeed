<?php
class EXTFEED_EXTRACTOR_Image extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {

        $dataContent = $data['content'];
        $photoId = $data['dataExtras']['photoIdList'][0];

        /**
         * @var PHOTO_BOL_PhotoService $photoService
         */
        $photoService = PHOTO_BOL_PhotoService::getInstance();

        $photo = $photoService->findPhotoById($photoId);
        //$photoUrl = $photoService->getPhotoUrlByType($photoId, PHOTO_BOL_PhotoService::TYPE_ORIGINAL, $photo->hash, $photo->getDimension());
        //$photoPreviewUrl = $photoService->getPhotoUrlByType($photoId, PHOTO_BOL_PhotoService::TYPE_PREVIEW, $photo->hash, $photo->getDimension());
        $photoPreviewUrl = $dataContent['vars']['image'];

        $photoPreviewDimension = json_decode($photo->getDimension(), true);

        $outContent = array(
            'status'=> $this->findStatus($data),
            'photoId'=>$photoId,
            'photoTitle'=>$photo->description,
            'photoPreviewDimensions'=>$photoPreviewDimension[PHOTO_BOL_PhotoService::TYPE_PREVIEW],
            //'photoUrl'=>$photoUrl,
            'photoPreviewUrl'=>$photoPreviewUrl
        );

        return $outContent;
    }
}