<?php

class EXTFEED_CLASS_PhotoService
{
    private static $classInstance;

    private $userService;
    public function __construct()
    {
        $this->userService = BOL_UserService::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_PhotoService
     */
    public static function getInstance()
    {
        if( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    public function getPhotoInfo($photoIdList)
    {
        if ( !is_array($photoIdList) )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD=>false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD=>"Param ids must be an array"
            );
        }

        if ( !OW::getPluginManager()->isPluginActive("photo") )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD=>false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD=>"Plugin photo not active"
            );
        }

        $photoService = PHOTO_BOL_PhotoService::getInstance();

        $photos = $photoService->findPhotoListByIdList($photoIdList, 1, count($photoIdList));
        $out = array();


        foreach ( $photos as $photo )
        {
            $photoId = $photo['id'];
            /** @var $photoAlbum PHOTO_BOL_PhotoAlbum */
            $photoAlbum = PHOTO_BOL_PhotoAlbumService::getInstance()->findAlbumById($photo['albumId']);
            $out[] = array(
                'id' => $photoId,
                'description' => $photo['description'],
                'time' => $photo['addDatetime'],
                'userDisplayName' => $this->userService->getDisplayName($photo['userId']),
                'userId' => $photo['userId'],
                'albumName' => $photoAlbum->name,
                'albumId' => $photoAlbum->getId(),
                'url' => $photoService->getPhotoUrlByType($photoId, PHOTO_BOL_PhotoService::TYPE_ORIGINAL, $photo['hash'], $photo['dimension'])
            );
        }

        return $out;
    }
}
