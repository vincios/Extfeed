<?php

class EXTFEED_CLASS_UserManager
{
    private static $classInstance;

    public function __construct()
    {
    }

    /**
     * Returns class instance
     *
     * @return EXTFEED_CLASS_UserManager
     */
    public static function getInstance()
    {
        if( null === self::$classInstance )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    public function getUserId()
    {
        $user_id = null;
        if ( !OW::getUser()->isAuthenticated() )
        {
            try
            {
                $user_id = ODE_CLASS_Tools::getInstance()->getUserFromJWT($_REQUEST['jwt']);
            }
            catch ( Exception $e )
            {
                echo json_encode(array("status"  => "ko", "error_message" => $e->getMessage()));
                exit;
            }
        }else
        {
            $user_id = OW::getUser()->getId();
        }

        return $user_id;
    }

    public function isAuthenticated()
    {
        $userId = $this->getUserId();

        return $userId !== null;
    }

    public function isAuthorized( $groupName, $actionName = null, $extra = null )
    {
        $extra = array(
            'userId' => $this->getUserId()
        );

        return BOL_AuthorizationService::getInstance()->isActionAuthorized($groupName, $actionName, $extra);
    }
}