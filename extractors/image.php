<?php

class EXTFEED_EXTRACTOR_Image extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        $dataContent = $data['content'];

        if( OW::getPluginManager()->isPluginActive("photo") && !array_key_exists("attachmentId", $data['dataExtras']) )
        {
            $photoId = $data['dataExtras']['photoIdList'][0];
            /**
             * @var PHOTO_BOL_PhotoService $photoService
             */
            $photoService = PHOTO_BOL_PhotoService::getInstance();
            $photo = $photoService->findPhotoById($photoId);
            $photoPreviewUrl = $dataContent['vars']['image'];

            $photoPreviewDimension = json_decode($photo->getDimension(), true);

            $outContent = array(
                'status' => $this->findStatus($data),
                'photoId' => $photoId,
                'photoDescription' => $photo->description,
                'photoPreviewDimensions' => $photoPreviewDimension[PHOTO_BOL_PhotoService::TYPE_PREVIEW],
                'photoPreviewUrl' => $photoPreviewUrl
            );
        }
        else //if photo plugin is not active, images are stored as attachment
        {
            $imageUrl = $dataContent['vars']['image'];
            $status = $this->findStatus($data);
            list($width, $height) = getimagesize($imageUrl);

            $width = isset($width) ? $width : 0;
            $height = isset($height) ? $height : 0;

            $outContent = array(
                'status' => $status,
                'photoId' => $data['dataExtras']['attachmentId'],
                'photoDescription' => $status,
                'photoPreviewDimensions' => array($width, $height),
                'photoPreviewUrl' => $imageUrl
            );
        }
        return $outContent;
    }
}