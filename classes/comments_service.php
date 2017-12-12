<?php

class EXTFEED_CLASS_CommentsService
{
    private static $classInstance;

    private $bolService;
    private $userService;
    private $avatarService;

    public function __construct()
    {
        $this->bolService = NEWSFEED_BOL_Service::getInstance();
        $this->userService = BOL_UserService::getInstance();
        $this->avatarService = BOL_AvatarService::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_CommentsService
     */
    public static function getInstance()
    {
        if ( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }


    public function getUserId()
    {
        $user_id = null;
        if (!OW::getUser()->isAuthenticated())
        {
            try
            {
                $user_id = ODE_CLASS_Tools::getInstance()->getUserFromJWT($_REQUEST['jwt']);
            }
            catch (Exception $e)
            {
                echo json_encode(array("status"  => "ko", "error_message" => $e->getMessage()));
                exit;
            }
        }else{
            $user_id = OW::getUser()->getId();
        }

        return $user_id;
    }

    /**
     * Get comments related to the feed item identified by $entityType and $entityId, according tho the pagination limits
     * If $page and $count are null then get all comments
     * @param $entityType
     * @param $entityId
     * @param null $page the page to retrieve (0 is first page)
     * @param null $count number of elements per page
     * @return array
     */
    public function getComments( $entityType, $entityId, $page = null, $count = null )
    {
        if($page !== null && $count !== null)
        {
            $page = $page + 1; //for findCommentList page starts at 1
            $comments = BOL_CommentService::getInstance()->findCommentList($entityType, $entityId, $page, $count);
        }
        else
        {
            $comments = BOL_CommentService::getInstance()->findFullCommentList($entityType, $entityId);
        }

        $out = array();

        /**@var $comment BOL_Comment*/
        foreach ( $comments as $comment )
        {
            $out[] = $this->generateCommentInfo($comment);
        }

        return $out;
    }

    public function addComment( $entityParams, $text, $userId, $attachment = null)
    {
        $entityType = $entityParams['entityType'];
        $entityId = $entityParams['entityId'];
        $pluginKey = $entityParams['pluginKey'];

        $attachmentInfo = null;

        if($attachment !== null)
        {
            $type = explode("/", $attachment['type']);

            if($type[0] == "image")
            {
                try
                {
                    $uid = BOL_CommentService::getInstance()->generateAttachmentUid($entityType, $entityId);
                    $attachDto = BOL_AttachmentService::getInstance()->processPhotoAttachment($pluginKey, $attachment, $uid);
                    OW::getEventManager()->call('base.attachment_save_image', array('uid' => $uid, 'pluginKey' => $pluginKey));

                    $attachmentInfo = array(
                        'uid' => $uid,
                        'pluginKey' => $pluginKey,
                        'url' => $attachDto['url'],
                        'href' => $attachDto['url'],
                        'type' => 'photo'
                    );
                }
                catch (InvalidArgumentException $e)
                {
                    return array(
                        EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                        EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => $e->getMessage()
                    );
                }
            }
        }

        $attachmentInfoJson = $attachmentInfo === null ? null : json_encode($attachmentInfo);

        $comment = BOL_CommentService::getInstance()->addComment($entityType, $entityId, $pluginKey, $userId, $text, $attachmentInfoJson);

        $event = new OW_Event("base_add_comment",
            array(
                'entityType' => $entityType,
                'entityId' => $entityId,
                'pluginKey' => $pluginKey,
                'userId' => $userId,
                'commentId' => $comment->getId(),
                'attachment' => $attachmentInfo
            ));

        OW::getEventManager()->trigger($event);

        BOL_AuthorizationService::getInstance()->trackAction($pluginKey, 'add_comment');

        return $this->generateCommentInfo($comment);
    }

    /**
     * @param $commentId
     * @param $entityParams array Must contain this entity information: 'entityType', 'entityId', 'pluginKey', 'ownerId' (author's userId).
     * @return array
     */
    public function deleteComment( $commentId, $entityParams )
    {
        $commentService = BOL_CommentService::getInstance();
        /* @var $comment BOL_Comment */
        $comment = $commentService->findComment($commentId);

        if ( $comment === null )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => "Comment not found"
            );
        }

        /* @var $commentEntity BOL_CommentEntity */
        $commentEntity = $commentService->findCommentEntityById($comment->getCommentEntityId());

        if ( $commentEntity === null )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => "Comment not found"
            );
        }

        $isModerator = OW::getUser()->isAuthorized($entityParams['pluginKey']);
        $isOwnerAuthorized = (OW::getUser()->isAuthenticated() && $entityParams['ownerId'] !== null && (int) $entityParams['ownerId'] === (int) OW::getUser()->getId());
        $commentOwner = ( (int) $this->getUserId() === (int) $comment->getUserId() );

        if ( !$isModerator && !$isOwnerAuthorized && !$commentOwner )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => EXTFEED_CTRL_Api::JSON_MESSAGE_USER_UNAUTHORIZED
            );
        }

        $attach = $comment->getAttachment();
        if( $attach !== null )
        {
            $tmp = json_decode($attach, true);

            if($tmp['uid'] !== null && $tmp['pluginKey'] !== null)
            {
                BOL_AttachmentService::getInstance()->deleteAttachmentByBundle($tmp['pluginKey'], $tmp['uid']);
            }
        }

        $commentService->deleteComment($comment->getId());
        $commentCount = $commentService->findCommentCount($commentEntity->getEntityType(), $commentEntity->getEntityId());

        if ( $commentCount === 0 )
        {
            $commentService->deleteCommentEntity($commentEntity->getId());
        }

        $event = new OW_Event('base_delete_comment', array(
            'entityType' => $commentEntity->getEntityType(),
            'entityId' => $commentEntity->getEntityId(),
            'userId' => $comment->getUserId(),
            'commentId' => $comment->getId()
        ));

        OW::getEventManager()->trigger($event);

        return array(
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true
        );
    }

    private function generateCommentInfo( BOL_Comment $comment )
    {
        $userId = $comment->getUserId();
        $userName = $this->userService->getDisplayName($userId);
        $avatar = $this->avatarService->getAvatarUrl($userId);

        $commentEntity = BOL_CommentService::getInstance()->findCommentEntityById($comment->getCommentEntityId());

        $action = $this->bolService->findAction($commentEntity->getEntityType(), $commentEntity->getEntityId());
        $createActivities = $this->bolService->findActivity(NEWSFEED_BOL_Service::SYSTEM_ACTIVITY_CREATE . ':' . $action->id);

        if( $avatar === null )
        {
            $avatar = $this->avatarService->getDefaultAvatarUrl();
        }


        $isModerator = OW::getUser()->isAuthorized($action->pluginKey);
        $commentOwner = ( (int) $this->getUserId() === (int) $comment->getUserId() );
        $isOwnerAuthorized = false;

        /** @var NEWSFEED_BOL_Activity $cActivity */
        foreach ( $createActivities as $cActivity )
        {
            if( OW::getUser()->isAuthenticated() && $cActivity->userId !== null && (int) $cActivity->userId === (int) OW::getUser()->getId() )
            {
                $isOwnerAuthorized = true;
                break;
            }
        }

        $contextActionMenu = array();

        $canDelete = $isModerator || $commentOwner || $isOwnerAuthorized;
        $canFlag = $this->getUserId() != $comment->getUserId();

        if ( $canDelete )
        {
            $contextActionMenu[] = array(
                'label' => OW::getLanguage()->text('base', 'contex_action_comment_delete_label'),
                'actionType' => 'delete_comment',
                'params' => array(
                    'commentId' => $comment->getId(),
                    'etype' => $action->entityType,
                    'eid' => $action->entityId,
                    'pkey' => $action->pluginKey,
                    'ownerId' => reset($createActivities)->userId
                )
            );
        }
        if ( $canFlag )
        {
            $contextActionMenu[] = array(
                'label' => OW::getLanguage()->text('base', 'flag'),
                'actionType' => 'flag_content',
                'params' => array(
                    'etype' => 'comment',
                    'eid' => $comment->id,
                    'reason' => null
                )
            );
        }

        $event = new OW_Event("extfeed.collect_comment_attachment",
            array(
                'id' => $comment->getId(),
            ),
            array(
                'commentEntity' => $comment
            )
        );

        $event = OW::getEventManager()->trigger($event);
        $eventData = $event->getData();

        $attachment = isset($eventData['attachment']) ? json_encode($eventData['attachment']) : $comment->getAttachment();

        $comment = array(
            'id' => $comment->getId(),
            'commentEntityId' => $comment->getCommentEntityId(),
            'userId' => $userId,
            'userDisplayName' => $userName,
            'avatarUrl' => $avatar,
            'createStamp' => $comment->getCreateStamp(),
            'message' => $comment->getMessage(),
            'attachment' => $attachment,
            'contextActionMenu' => $contextActionMenu
        );

        return $comment;
    }

}