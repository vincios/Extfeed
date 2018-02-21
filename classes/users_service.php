<?php
class EXTFEED_CLASS_UsersService
{
    private static $classInstance;

    private $userService;
    private $userManager;
    private $utils;

    public function __construct()
    {
        $this->userService = BOL_UserService::getInstance();
        $this->userManager = EXTFEED_CLASS_UserManager::getInstance();
        $this->utils = EXTFEED_CLASS_Utils::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_UsersService
     */
    public static function getInstance()
    {
        if( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }


    public function getUserProfileInfo( $userId )
    {
        /** @var BOL_User $user */
        $user = $this->userService->findUserById($userId);

        if( $user === null )
        {
            return $this->utils->errorMessage('No user find with id '.$userId);
        }

        if($userId == $this->userManager->getUserId())
        {
            return $this->getProfileInfo($user);
        }

        if( !$this->userManager->isAuthorized('base', 'view_profile') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('base', 'view_profile', array('userId' => $this->userManager->getUserId()));
            return $this->utils->errorMessage(EXTFEED_CTRL_Api::JSON_MESSAGE_USER_UNAUTHORIZED . "Reason: " . $status['msg']);
        }

        $privacyEventParams = array(
            'action' => 'base_view_profile',
            'ownerId' => $user->id,
            'viewerId' => $this->userManager->getUserId()
        );

        try
        {
            OW::getEventManager()->getInstance()->call('privacy_check_permission', $privacyEventParams);
        }
        catch ( RedirectException $ex )
        {
            return $this->utils->errorMessage("privacy error");
        }

        if( !$this->userService->isApproved($user->id) )
        {
            return $this->utils->errorMessage(OW::getLanguage()->text("base", "pending_approval"));
        }

        if( $this->userService->isSuspended($user->id) )
        {
            return $this->utils->errorMessage("user suspended");
        }

        return $this->getProfileInfo($user);
    }



    private function getProfileInfo( $user )
    {
        $viewerId = $this->userManager->getUserId();

        $personalProfile = $user->id === $viewerId;
        $isAdmin = OW::getUser()->isAdmin() || $this->userManager->isAuthorized('base');

        $questions = $this->userService->getUserViewQuestions($user->id, $isAdmin);

        $data = $questions['data'][$user->id];
        $labels = $questions['labels'];

        foreach ( $data as $dataItem => $value )
        {
            if( is_array($value) && count($value) == 1)
            {
                $data[$dataItem] = implode(", ", $value);
            }
        }
        $data['avatar'] = BOL_AvatarService::getInstance()->getAvatarUrl($user->id);

        return array(
            'isPersonal' => $personalProfile,
            'data' => $data,
            'labels' => $labels
        );
    }

}