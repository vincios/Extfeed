<?php

class EXTFEED_CLASS_NewsfeedService
{
    private static $classInstance;

    private $bolService;
    private $userService;
    private $avatarService;
    private $userManager;

    public function __construct()
    {
        $this->bolService = NEWSFEED_BOL_Service::getInstance();
        $this->userService = BOL_UserService::getInstance();
        $this->avatarService = BOL_AvatarService::getInstance();
        $this->userManager = EXTFEED_CLASS_UserManager::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_NewsfeedService
     */
    public static function getInstance()
    {
        if ( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }


    /**
     * Return an array that contain all the actions that respects the parameters in $params
     * @param $params array Must contain following fields:
     *    <br>  - offset (int): the first element to obtain from the database (for pagination)
     *    <br>  - displayCount (int):  elements number to obtain from the database (for pagination)
     *    <br>  - formats (array): filter on actions' formats to obtain from the database (null for all kind of formats)
     *    <br>  - feedType (string): feed's type: my, site or personal feed type (user, groups, etc...)
     *    <br>  - feedId (int): id of the feed to obtain, in according with the feedType: if 'my' is the userId, else if 'site' is null, else if is a personal feed is the personal id (userId, groupId, etc.)
     *    <br>  - startTime (timestamp): timestamp when the request starts
     *    <br>  - displayType (string): 'action' to display only feed'sn actions or 'activity' to display also the activities related to the actions
     *
     *    <br> Only feedType and feedId are required. All other params are optional
     *
     * @return null|array a list of item feed with all related info
     */
    public function getFeedActionsList( $params )
    {

        $auth = $this->viewerAuthorized($params['feedType'], $params['feedId']);

        $canView = $auth['view']['result'];

        if( !$canView )
        {
            return $auth['view'];
        }

        $driver = $this->obtainFeedDriver($params);
        $actionsList = $driver->getActionList();

        $out = array();
        $entities = array();

        /** @var NEWSFEED_CLASS_Action $action */
        foreach ($actionsList as $action)
        {
            $entities[] = array(
                'entityId'=>$action->getEntity()->id,
                'entityType'=>$action->getEntity()->type,
            );
        }

        $extras = array(
            'likes' => $this->bolService->findLikesByEntityList($entities),
            'feedType' => $params['feedType'],
            'feedId' => $params['feedId']
        );


        foreach ( $actionsList as $action )
        {
            $param = array(
                'action'=>$action
            );

            $event = new OW_Event("extfeed.before_post_extraction", $param);
            $event = OW::getEventManager()->trigger($event);

            $data = $event->getData();
            //$actionOut = isset($data['actionOut']) ? $data['actionOut'] : $action;
            $action = $data['action'];

            /**@var EXTFEED_CLASS_PostExtractor $extractor */
            $extractor = EXTFEED_CLASS_ExtractorsManager::getInstance()->getExtractor($action->getFormat());

            if( $extractor !== null )
            {
                $out[] = $extractor->extractPost($action, $extras);
            }
        }
        return $out;
    }

    public function like( $entityType, $entityId, $userId )
    {

        if( $userId <= 0 )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => 'No user find with id '.$userId
            );
        }

        $like = $this->bolService->addLike($userId, $entityType, $entityId);

        $event = new OW_Event("feed.after_like_added",
            array(
                'entityType' => $like->entityType,
                'entityId' => $like->entityId,
                'userId' => $userId
            ),
            array(
                'likeId' => $like->id
            ));

        OW::getEventManager()->trigger($event);

        $likesNumber = $this->bolService->findEntityLikesCount($entityType, $entityId);

        return array(
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true,
            'likeCount' => $likesNumber
        );
    }

    public function unlike( $entityType, $entityId, $userId )
    {
        $this->bolService->removeLike($userId, $entityType, $entityId);

        $event = new OW_Event('feed.after_like_removed', array(
            'entityType' => $entityType,
            'entityId' => $entityId,
            'userId' => $userId
        ));

        OW::getEventManager()->trigger($event);
        $likesNumber = $this->bolService->findEntityLikesCount($entityType, $entityId);

        return array(
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true,
            'likeCount' => $likesNumber
        );
    }

    public function addStatus ( $feedType, $feedId, $status, $userId, $visibility = "", $attachment = null )
    {

        $aut = $this->viewerAuthorized($feedType, $feedId);

        if( $aut['write']['result'] == false ) {
            return $aut['write'];
        }

        $content = array();
        $attachmentId = null;

        if($attachment !== null && is_array($attachment) ) //Attachment is a file
        {
            $type = explode("/", $attachment['type']);

            if($type[0] == "image")
            {
                try
                {
                    $attach = BOL_AttachmentService::getInstance()->processPhotoAttachment("extfeed", $attachment);

                    $content['type'] = "photo";
                    $content['url'] = $content['href'] = $attach['url'];
                    $content['pluginKey'] = $attach['dto']->pluginKey;
                    $attachmentId = $attach['uid'];
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
        else if( $attachment !== null )
        {
            $content = json_decode($attachment, true);
        }

        $event = new OW_Event("feed.before_content_add", array(
            "feedType" =>$feedType,
            "feedId" => $feedId,
            "visibility" => $visibility,
            "userId" => $userId,
            "status" => $status,
            "type" => empty($content["type"]) ? "text" : $content["type"],
            "data" => $content
        ));

        OW::getEventManager()->trigger($event);

        $data = $event->getData();

        if( !empty($data) ) //if someone catch the event it also make the feed item and save its identifier in $data (or a string in case of error)
        {
            $item = empty($data["entityType"]) || empty($data["entityId"])
                ? null
                : array(
                    "entityType" => $data["entityType"],
                    "entityId" => $data["entityId"]
                );

            if( !empty($item) )
            {
                return $this->getFeedItem(
                    array('feedType' => $feedType, 'feedId' => $feedId),
                    $item
                );
            }

            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => $data['error']
            );
        }

        //if nobody catch the event we have to create the new feed item
        $status = UTIL_HtmlTag::autoLink($status);
        $item = $this->bolService->addStatus($userId, $feedType, $feedId, $visibility, $status,
            array(
                'content' => $content,
                'attachmentId' => $attachmentId
            ));

        return $this->getFeedItem(
            array('feedType' => $feedType, 'feedId' => $feedId),
            $item
        );
    }


    public function getFeedItem ( $feedParams, $entityParams )
    {
        $driver = $this->obtainFeedDriver($feedParams);
        $action = $driver->getAction($entityParams['entityType'], $entityParams['entityId']);

        if( $action === null )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => "Item not found"
            );
        }

        $event = new OW_Event(
            "extfeed.before_post_extraction",
            array('action'=>$action)
        );

        OW::getEventManager()->trigger($event);
        $data = $event->getData();
        $action = $data['action'];

        /**@var $extractor EXTFEED_CLASS_PostExtractor*/
        $extractor = EXTFEED_CLASS_ExtractorsManager::getInstance()->getExtractor($action->getFormat());
        if( $extractor !== null )
        {
            return $extractor->extractPost(
                $action,
                array_merge($feedParams, $entityParams)
            );
        }

        return array(
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
            EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => 'Invalid feed item'
        );
    }


    public function deleteFeedItem( $actionId )
    {
        $action = $this->bolService->findActionById($actionId);

        if( $action === null )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => "Item not found"
            );
        }

        $allowed = $this->userManager->isAuthorized("newsfeed"); //check if user is a moderator

        if( !$allowed ) //if not, check if user have created this action
        {
            $createActivities = $this->bolService->findActivity(NEWSFEED_BOL_Service::SYSTEM_ACTIVITY_CREATE . ':' . $action->id);

            /**@var $activity NEWSFEED_BOL_Activity */
            foreach ( $createActivities as $activity )
            {
                if( $activity->userId == $this->userManager->getUserId() )
                {
                    $allowed = true;
                    break;
                }
            }
        }

        if( $allowed )
        {
            $this->bolService->removeActionById($action->id);
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true
            );
        }
        else
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => EXTFEED_CTRL_Api::JSON_MESSAGE_USER_UNAUTHORIZED
            );
        }
    }

    public function flagContent( $entityType, $entityId, $userId, $reason )
    {
        if( !in_array($reason, array('spam', 'offence', 'illegal')) )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => "Reason must be one of 'spam', 'offence' or 'illegal'");
        }

        /* ContentService collect all the information about an entity*/
        $content = BOL_ContentService::getInstance()->getContent($entityType, $entityId);
        $ownerId = $content['userId'];

        if( $ownerId == $userId )
        {
            return array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => false,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => "You cannot flag your content"
            );
        }

        BOL_FlagService::getInstance()->addFlag($entityType, $entityId, $reason, $userId);

        return array(
            EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true
        );

    }

    public function getLikeList( $entityType, $entityId )
    {
        $likeList = $this->bolService->findEntityLikes($entityType, $entityId);

        $out = array();

        /** @var NEWSFEED_BOL_Like $like */
        foreach ( $likeList as $like )
        {
            $userId = $like->userId;
            $userName = $this->userService->getDisplayName($userId);
            $avatar = $this->avatarService->getAvatarUrl($userId);

            if( $avatar === null )
            {
                $avatar = $this->avatarService->getDefaultAvatarUrl();
            }

            $out[] = array(
                'timestamp' => $like->timeStamp,
                'userId' => $userId,
                'userDisplayName' => $userName,
                'avatarUrl' => $avatar,
            );
        }

        return $out;
    }

    public function analyzeLink( $url )
    {
        $url = EXTFEED_CLASS_Utils::getInstance()->sanitizeUrl($url);
        $url = $url = str_replace("'", '%27', $url);

        return UTIL_HttpResource::getOEmbed($url);
    }

    /**
     * Construct an appropriate driver for the feed.
     *
     * @param $params array Must contain at least <i>feedType</i> and <i>feedId</i> but may contain also other params for the driver like
     * offset, displayCount, formats, startTime. See {@see EXTFEED_CLASS_ExternalService::getFeedActionsList()} for a better $params explain.
     * @return NEWSFEED_CLASS_Driver|null
     */
    private function obtainFeedDriver ( $params )
    {
        if( !isset($params['feedType']) || !isset($params['feedId']) )
        {
            return null;
        }

        $driverClass = null;

        switch( $params['feedType'] )
        {
            case 'my':
                $driverClass = "NEWSFEED_CLASS_UserDriver"; break;
            case 'site':
                $driverClass = "NEWSFEED_CLASS_SiteDriver"; break;
            default:
                $driverClass = "NEWSFEED_CLASS_FeedDriver"; break;
        }

        if( !isset($params['startTime']) )
        {
            $params['startTime'] = time();
        }

        /**
         * @var $driver NEWSFEED_CLASS_Driver
         */
        $driver = OW::getClassInstance($driverClass);
        $driver->setup($params);

        return $driver;
    }


    /**
     * Check if the current logged user can view and write on the feed identified by params.
     * @param $feedType string identifier of the feed to check
     * @param $feedId int identifier of the feed to check
     * @return array Result of the check. Contains two items (named 'view' and 'write'). Each item have two field: 'result' (true if user is authorized to view/write on this feed, false otherwise)
     * and 'message' (error message if result is false, null otherwise)
     */
    public function viewerAuthorized( $feedType, $feedId )
    {

        $auth = array(
            'view' => array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => null
            ),
            'write' => array(
                EXTFEED_CTRL_Api::JSON_RESULT_FIELD => true,
                EXTFEED_CTRL_Api::JSON_MESSAGE_FIELD => null
            )
        );

        $viewerId = $this->userManager->getUserId();
        switch ( $feedType )
        {
            case "user":
                $userId = $feedId;
                $ownerMode = $userId == $viewerId;
                $modPermissions = $this->userManager->isAuthorized( 'newsfeed' );

                if( !$ownerMode && !$modPermissions )
                {
                    $privacyParams = array( 'action' => NEWSFEED_BOL_Service::PRIVACY_ACTION_VIEW_MY_FEED, 'ownerId' => $userId, 'viewerId' => $viewerId );
                    $event = new OW_Event( 'privacy_check_permission', $privacyParams );

                    try
                    {
                        OW::getEventManager()->trigger( $event );
                    }
                    catch
                    (RedirectException $e )
                    {
                        $auth['view']['result'] = false;
                        $data = $e->getData();
                        $auth['view']['message'] = isset($data['message']) ? $data['message'] : "No permissions";
                    }
                }
                $isBlocked = BOL_UserService::getInstance()->isBlocked($this->userManager->getUserId(), $userId);

                if( $this->userManager->isAuthorized('base', 'add_comment') )
                {
                    if( $isBlocked )
                    {
                        $auth['write']['result'] = false;
                        $auth['write']['message'] = OW::getLanguage()->text("base", "user_block_message");
                    }
                } else
                {
                    $auth['write']['result'] = false;
                    $auth['write']['message'] = "Not authorized";
                    $actionStatus = BOL_AuthorizationService::getInstance()->getActionStatus('base', 'add_comment', array('userId', $this->userManager->getUserId()));

                    if( $actionStatus["status"] == BOL_AuthorizationService::STATUS_PROMOTED )
                    {
                        $auth['write']['message'] = $actionStatus["msg"];
                    }
                }
                break;
            case "my" :
                if($viewerId != $feedId)
                {
                    $auth['view']['result'] = false;
                    $auth['view']['message'] = "You cannot see this feed";
                    $auth['write']['result'] = false;
                    $auth['write']['message'] = "You cannot write on this feed";
                    break;
                }

                if ( !$this->userManager->isAuthorized('newsfeed', 'allow_status_update') )
                {
                    $auth['write']['result'] = false;
                    $auth['write']['message'] = "You are not authorized to write";
                }
                break;
            case "site":
                $enabled = OW::getConfig()->getValue('newsfeed', 'index_status_enabled');

                if ( !$enabled || !$this->userManager->isAuthenticated() || !$this->userManager->isAuthorized('newsfeed', 'allow_status_update') )
                {
                    $auth['write']['result'] = false;
                    $auth['write']['message'] = "You are not authorized to write";
                }
                break;
        }

        return $auth;
    }
}