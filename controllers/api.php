<?php

class EXTFEED_CTRL_Api extends OW_ActionController
{

    /**@var EXTFEED_CLASS_NewsfeedService */
    protected $newsfeedService;

    /** @var EXTFEED_CLASS_CommentsService */
    protected $commentsService;

    /** @var EXTFEED_CLASS_PhotoService */
    protected $photoService;

    protected $usersService;

    const JSON_RESULT_FIELD = "result";

    protected $userManager;
    const JSON_MESSAGE_FIELD = "message";
    const JSON_MESSAGE_MISSING_PARAMETER = "Missing required parameters";
    const JSON_MESSAGE_USER_NOT_AUTHENTICATED = "User must be authenticated";
    const JSON_MESSAGE_USER_UNAUTHORIZED = "User not authorized to do this action";

    public function __construct()
    {
        $this->newsfeedService = EXTFEED_CLASS_NewsfeedService::getInstance();
        $this->commentsService = EXTFEED_CLASS_CommentsService::getInstance();
        $this->photoService = EXTFEED_CLASS_PhotoService::getInstance();
        $this->usersService = EXTFEED_CLASS_UsersService::getInstance();
        $this->userManager = EXTFEED_CLASS_UserManager::getInstance();
    }

    private function setHeaders()
    {
        header('Content-Type:application/json');
    }


    private function isAuthenticated()
    {
        return $this->userManager->isAuthenticated();
    }

    private function messageError( $message )
    {
        return json_encode(
            array(
                self::JSON_RESULT_FIELD => false,
                self::JSON_MESSAGE_FIELD => $message
            )
        );
    }


    public function getAuthorization()
    {

        $this->setHeaders();
        if( !$this->paramsExists(array('ft', 'fi')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }


        $feedType = $_REQUEST['ft'];
        $feedId = $_REQUEST['fi'];

        $auth = $this->newsfeedService->viewerAuthorized($feedType, $feedId);

        echo json_encode($auth);
        $this->userManager->logout();
        exit();
    }

    public function getFeed()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('ft', 'fi', 'offset', 'count')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $userId = $this->userManager->getUserId();

        $feedType = $_REQUEST['ft'];
        $feedId = (int)$_REQUEST['fi'];

        $isMy = $feedType === "my";
        $sameId = $feedId == $userId;

        if( $isMy && !$sameId )
        {
            echo $this->messageError("You cannot see this feed");
            exit();
        }

        $params = array(
            'feedId' => $_REQUEST['fi'],
            'feedType' => $_REQUEST['ft'],
            'offset' => $_REQUEST['offset'],
            'displayCount' => $_REQUEST['count'],
            'startTime' => time(),
            'formats' => null
        );


        $out = $this->newsfeedService->getFeedActionsList($params);


        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function getItem()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('ft', 'fi', 'etype', 'eid')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit();
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $feedParams = array(
            'feedType' => $_REQUEST['ft'],
            'feedId' => $_REQUEST['fi']
        );

        $itemParams = array(
            'entityType' => $_REQUEST['etype'],
            'entityId' => $_REQUEST['eid']
        );

        $out = $this->newsfeedService->getFeedItem($feedParams, $itemParams);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }


    public function addLike()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('etype', 'eid')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $entityType = $_REQUEST['etype'];
        $entityId = $_REQUEST['eid'];
        $userId = isset($_REQUEST['userId']) ? $_REQUEST['userId'] : $this->userManager->getUserId();

        $out = $this->newsfeedService->like($entityType, $entityId, $userId);


        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function removeLike()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('etype', 'eid')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $entityType = $_REQUEST['etype'];
        $entityId = $_REQUEST['eid'];
        $userId = isset($_REQUEST['userId']) ? $_REQUEST['userId'] : $this->userManager->getUserId();

        $out = $this->newsfeedService->unlike($entityType, $entityId, $userId);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function statusUpdate()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('ftype', 'fid', 'message')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit();
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $attachment = null;
        if($_FILES)
        {
            $attachment = $_FILES['attachment'];
        }
        if( isset($_REQUEST['attachment']) ) {
            $attachment = $_REQUEST['attachment'];
        }

        $userId = $this->userManager->getUserId();
        $feedType = $_REQUEST['ftype'];
        $feedId = $_REQUEST['fid'];
        $message = $_REQUEST['message'];
        $visibility = isset($_REQUEST['visibility']) ? $_REQUEST['visibility'] : '';

        $out = $this->newsfeedService->addStatus($feedType, $feedId, $message, $userId, $visibility, $attachment);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function comments()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('etype', 'eid')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $entityType = $_REQUEST['etype'];
        $entityId = $_REQUEST['eid'];
        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : null;
        $count = isset($_REQUEST['count']) ? $_REQUEST['count'] : null;

        $out = $this->commentsService->getComments($entityType, $entityId, $page, $count);

        echo json_encode($out);
        $this->userManager->logout();
        exit;
    }

    public function addComment()
    {
        $this->setHeaders();

        if( !$this->paramsExists(array('etype', 'eid', 'pkey', 'message', 'ownerId')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit();
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        if( !$this->userManager->isAuthorized($_REQUEST['pkey'], 'add_comment') )
        {
            echo $this->messageError("User not authorized");
            exit();
        }

        $userId = $this->userManager->getUserId();
        $attachment = null;
        if($_FILES)
        {
            $attachment = $_FILES['attachment'];
        }

        $params = array(
            'entityType' => $_REQUEST['etype'],
            'entityId' => $_REQUEST['eid'],
            'pluginKey' => $_REQUEST['pkey']
        );

        if( BOL_UserService::getInstance()->isBlocked($userId, $_REQUEST['ownerId']))
        {
            echo $this->messageError("User blocked by owner");
        }

        $out = $this->commentsService->addComment($params, $_REQUEST['message'], $userId, $attachment);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function deleteComment()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('commentId', 'etype', 'eid', 'pkey', 'ownerId')) )
        {
            $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $commentId = $_REQUEST['commentId'];
        $eParams = array(
            'entityType' => $_REQUEST['etype'],
            'enitityId' => $_REQUEST['eid'],
            'pluginKey' => $_REQUEST['pkey'],
            'ownerId' => $_REQUEST['ownerId']
        );

        echo json_encode($this->commentsService->deleteComment($commentId, $eParams));
        $this->userManager->logout();
        exit();
    }

    public function deletePost()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('actionId')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
        }

        $actionId = $_REQUEST['actionId'];
        $out = $this->newsfeedService->deleteFeedItem($actionId);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function flagContent()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('etype', 'eid', 'reason')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $entityType = $_REQUEST['etype'];
        $entityId = $_REQUEST['eid'];
        $userId = isset($_REQUEST['userId']) ? $_REQUEST['userId'] : $this->userManager->getUserId();
        $reason = $_REQUEST['reason'];

        if( !in_array($reason, array('spam', 'offence', 'illegal')) )
        {
            echo $this->messageError("Reason must be one of 'spam', 'offence' or 'illegal'");
            exit();
        }

        $out = $this->newsfeedService->flagContent($entityType, $entityId, $userId, $reason);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function likesList()
    {
        $this->setHeaders();
        if( !$this->paramsExists(array('etype', 'eid')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit();
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $entityType = $_REQUEST['etype'];
        $entityId = $_REQUEST['eid'];

        $out = $this->newsfeedService->getLikeList($entityType, $entityId);

        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    public function getLinkContent()
    {
        $this->setHeaders();

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }


        $url = $_REQUEST['url'];

        $out = $this->newsfeedService->analyzeLink($url);

        if( empty($out) )
        {
            $out['type'] = 'empty';
        }
        if( isset($out['allImages']) )
        {
            $images = $out['allImages'];
            $out['allImages'] = EXTFEED_CLASS_Utils::getInstance()->sanitizeUrl($images);
        }
        echo json_encode($out);
        $this->userManager->logout();
        exit();
    }

    private function paramsExists( $params )
    {

        foreach ( $params as $value )
        {
            if( !isset($_REQUEST[$value]) )
            {
                return false;
            }
        }

        return true;
    }

    public function getUserProfileInfo()
    {
        $this->setHeaders();
        if ( !$this->paramsExists(array('userId')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $this->userManager->login();
        if( !$this->isAuthenticated() )
        {
            echo $this->messageError(self::JSON_MESSAGE_USER_NOT_AUTHENTICATED);
            exit();
        }

        $userId = $_REQUEST['userId'];
        $out = $this->usersService->getUserProfileInfo($userId);
        echo json_encode($out);
        exit();
    }

    public function photosInfo()
    {
        $this->setHeaders();
        if ( !$this->paramsExists(array('ids')) )
        {
            echo $this->messageError(self::JSON_MESSAGE_MISSING_PARAMETER);
            exit;
        }

        $ids = $_REQUEST['ids'];
        $out = $this->photoService->getPhotoInfo($ids);
        echo json_encode($out);
        exit;
    }


}