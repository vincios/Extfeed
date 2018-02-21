<?php

abstract class EXTFEED_CLASS_PostExtractor
{

    private $configs;

    /**
     * NEWSFEED_CLASS_PostExtractor constructor.
     */
    public function __construct()
    {
        $this->configs = OW::getConfig()->getValues("newsfeed");
    }

    /**
     *
     * Extract the current action's content data and return them in a way that can be exposed to external clients. <br>
     * The action's content is stored in <i>$data['content']</i> in a json decoded array that follow exactly the
     * format its is stored in database. <br>
     * More precisely, for the default formats, content is stored in <i>$data['content']['var']</i>,
     * other plugins may follow this convention or use a custom way to store it for their custom formats.<br>
     * This method must return an array that will be json encoded.
     * @param $data array Contains all the Action's information as they have been extracted by {@see EXTFEED_CLASS_PostExtractor::extractPost()}
     * @return array
     */
    public abstract function extractContent ( $data );

    /**
     * @param NEWSFEED_CLASS_Action $action
     * @param null $extraInfo
     * @return array
     * @internal param NEWSFEED_CLASS_Action $action
     */
    public function extractPost($action, $extraInfo = null )
    {
        $default = array(
            'content' => null,
            'context' => null,
            'features' => array("comments", "likes"),
            'contextActionMenu' => array(),
            'string' => null,
            'line' => null,
            'dataExtras' => null,
            'lastActivityRespond' => null
        );

        $out = array(
            'id'=> $action->getId(),
            'entityId' => $action->getEntity()->id,
            'entityType' => $action->getEntity()->type,
            'pluginKey' => $action->getPluginKey(),
            'time' => $action->getCreateTime(),
            'format' => $action->getFormat(),
            'userId' => $action->getUserId(),
        );


        $creatorIdList = $action->getCreatorIdList();
        $out['userIds'] = empty($creatorIdList) ? array($out['userId']) : $creatorIdList;

        $out = array_merge($out, $default);

        $data = $action->getData();

        foreach ( $data as $key => $value )
        {
            if ( !array_key_exists($key, $out) )
            {
                $out['dataExtras'][$key] = $value;
            }
            else
            {
                $out[$key] = $value;
            }
        }

        $actionStringEncoded = $out['string'];
        $actionLineEncoded = $out['line'];

        $activitiesArray = array();
        $lastActivity = null;
        $activityBolList = $action->getActivityList();

        foreach ( $activityBolList as $a )
        {
            /* @var $a NEWSFEED_BOL_Activity */
            $activitiesArray[$a->id] = array(
                'activityType' => $a->activityType,
                'activityId' => $a->activityId,
                'id' => $a->id,
                'data' => json_decode($a->data, true),
                'timeStamp' => $a->timeStamp,
                'privacy' => $a->privacy,
                'userId' => $a->userId,
                'visibility' =>$a->visibility
            );

            //we cannot use the action's getLastActivity() method because sometime this method returns a "system activity" (create o subscribe) even if there is another suitable activity (like 'comment' or 'like') on the action
            if ( $lastActivity === null && !in_array($a -> activityType, NEWSFEED_BOL_Service::getInstance()->SYSTEM_ACTIVITIES) )
            {
                $lastActivity = $activitiesArray[$a -> id];
                break;
            }
        }

        if ( $lastActivity != null )
        {

            $out['lastActivityRespond'] = array();

            $out['lastActivityRespond']['userId'] = $lastActivity['userId'];
            $out['userIds'] = empty($lastActivity['userIds'])
                ? array_merge($out['userIds'], array($lastActivity['userId']))
                : array_merge($out['userIds'], $lastActivity['userIds']);
            $out['userIds'] = array_unique($out['userIds']); //array_merge in previous lines could make duplicates so here we remove them

            //$out['time'] = empty($lastActivity['data']['timestamp']) ? $lastActivity['timeStamp'] : $lastActivity['data']['timestamp'];

            foreach ( $lastActivity['data'] as $key => $value )
            {
                if( $key == 'content' )
                {
                    $out[$key] = array_merge($out[$key], $value);
                }
                if( !in_array($key, array("content", "string", "line")) )
                {
                    $out['dataExtras'][$key] = $value;
                }
            }

            if ( isset($lastActivity['data']['string']) )
            {
                $activityStringEncoded = $lastActivity['data']['string'];
                $out['lastActivityRespond']['string'] = $this->decodeString($activityStringEncoded);
            }

            if( isset($lastActivity['data']['line']) )
            {
                $activityLineEncoded = $lastActivity['data']['line'];
                $out['lastActivityRespond']['line'] = $this->decodeString($activityLineEncoded);
            }
        }

        if( $actionStringEncoded != null )
        {
            $out['string'] = $this->decodeString($actionStringEncoded);
        }

        if( $actionLineEncoded != null )
        {
            $out['line'] = $this->decodeString($actionLineEncoded);
        }

        $out['usersInfo'] = BOL_AvatarService::getInstance()->getDataForUserAvatars($out['userIds'],true, false,true, true);
        $out['features'] = $this->extendFeatures($out, $extraInfo);
        $out['content'] = $this->extractContent($out);

        //Operations for triggering feed.on_item_render event. This event add some information like contextMenu buttons
        $feedList = array();
        $sameFeed = false;

        foreach ( $action->getFeedList() as $feed )
        {
            /** @var $feed NEWSFEED_BOL_ActionFeed*/
            if ( $feed->feedType == $extraInfo['feedType'] && $feed->feedId == $extraInfo['feedId'] )
            {
                $sameFeed = true;
            }

            $feedList[] = array(
                'feedType'=>$feed->feedType,
                'feedId'=>$feed->feedId
            );
        }

        $eventParams = array(
            'action' => array(
                'id' => $action->getId(),
                'entityType' => $action->getEntity()->type,
                'entityId' => $action->getEntity()->id,
                'pluginKey' => $action->getPluginKey(),
                'createTime' => $action->getCreateTime(),
                'userId' => $action->getUserId(), // backward compatibility with desktop version
                "userIds" => $out['userIds'],
                'format' => $action->getFormat(),
                'data' => $action->getData(),
                "feeds" => $feedList,
                "onOriginalFeed" => $sameFeed
            ),

            'activity' => $activitiesArray,
            'createActivity' => $action->getCreateActivity(),
            'lastActivity' => $lastActivity,
            'feedType' =>$extraInfo['feedType'],
            'feedId' => $extraInfo['feedId'],
            'feedAutoId' => null, //placeholder: we haven't a feedAutoId
            'autoId' => null //placeholder: we haven't an autoId
        );

        $eventData = array_merge($out, $out['dataExtras']);
        $eventData['content']['vars'] = array();
        $eventData['action'] = array(
            'userId' => $action->getUserId(),
            "userIds" => $creatorIdList,
            'createTime' => $action->getCreateTime()
        );

        $eventData['contextMenu'] = array();

        $event = new OW_Event('feed.on_item_render', $eventParams, $eventData);
        OW::getEventManager()->trigger($event);

        $out = $this->mergeEventData($out, $event->getData());
        //return $eventData;
        return $out;
    }


    private function decodeString($encodedString)
    {
        if( !is_array($encodedString) )
        {
            return $encodedString;
        }

        if( !array_key_exists("key", $encodedString) )
        {
            return;
        }

        $keyArray = explode("+", $encodedString['key']);
        $prefix = $keyArray[0];
        $key = $keyArray[1];

        $vars = isset($encodedString['vars']) ? $encodedString['vars'] : null;

        return OW::getLanguage()->text($prefix, $key, $vars);
    }

    protected function decodeRoutingInfo($routeParams)
    {
        if ( !isset($routeParams) )
        {
            return;
        }

        if ( !is_array($routeParams) )
        {
            return $routeParams;
        }

        return OW::getRouter()->urlForRoute( $routeParams['routeName'], $routeParams['vars'] );
    }


    protected function findStatus($data)
    {
        $dataContent = $data['content']['vars'];

        if ( isset($dataContent) && isset($dataContent['status']) )
        {
            return $dataContent['status'];
        }
        elseif ( isset($data['dataExtras']['status']) )
        {
            return $data['dataExtras']['status'];
        }
        else if ( isset($data['dataExtras']['data']['status']) )
        {
            return $data['dataExtras']['data']['status'];
        }

        return null;
    }

    protected function extendFeatures($data, $extraInfo)
    {
        $features = $data['features'];
        $featuresArray = array();

        $featuresOut = array();

        foreach ($features as $key=>$feature) //we do this because in item_list.php code analysis seems that in some case $features' elements can be arrays (with 'pluginKey', 'entityId' and 'entityType' fields) instead of strings
        {
            if ( is_string($feature) )
            {
                $featuresArray[$feature] = array();
            }
            else
            {
                $featuresArray[$key] = $feature;
            }
        }

        if ( $this->configs['allow_comments'] && array_key_exists("comments", $featuresArray) )
        {
            $commentsFeatureData = $featuresArray['comments'];

            //if feature is an array (instead of a string - see above) it contains all the data we need. If not we take them from the $data array
            $entityType = isset($commentsFeatureData['entityType']) ? $commentsFeatureData['entityType'] : $data['entityType'];
            $authGroup = isset($commentsFeatureData['pluginKey']) ? $commentsFeatureData['pluginKey'] : $data['pluginKey'];
            $entityId = isset($commentsFeatureData['entityId']) ? $commentsFeatureData['entityId'] : $data['entityId'];

            //we check if exist, for our action's pluginKey, an authorization rule for the action 'add_comment'...
            $authAction = BOL_AuthorizationService::getInstance()->findAction($authGroup, "add_comment", true);

            if ( $authAction === null )
            {
                $authGroup = "newsfeed"; //...if not we use the generic authorization rule for the plugin 'newsfeed'
            }

            $isAllowed = EXTFEED_CLASS_UserManager::getInstance()->isAuthorized($authGroup, "add_comment");
            $count = BOL_CommentService::getInstance()->findCommentCount($entityType, $entityId);

            $featuresOut['comments'] = array(
                'allow'=>$isAllowed,
                'count'=>$count
            );
        }

        if ( $this-> configs['allow_likes'] && array_key_exists("likes", $featuresArray) )
        {
            $likesFeatureData = $featuresArray['likes'];
            $entityType = isset($likesFeatureData['entityType']) ? $likesFeatureData['entityType'] : $data['entityType'];
            $entityId = isset($likesFeatureData['entityId']) ? $likesFeatureData['entityId'] : $data['entityId'];

            if( isset($extraInfo['likes']) )
            {
                $likes = isset($extraInfo['likes'][$entityType][$entityId])
                    ? $extraInfo['likes'][$entityType][$entityId]
                    : array();
            }
            else
            {
                $likes = NEWSFEED_BOL_Service::getInstance()->findEntityLikes($entityType, $entityId);
            }

            $selfLike = false;

            /**@var NEWSFEED_BOL_Like $like*/
            foreach ($likes as $like)
            {
                if( $like->userId == EXTFEED_CLASS_UserManager::getInstance()->getUserId() )
                {
                    $selfLike = true;
                    break;
                }
            }

            $isAllowed = EXTFEED_CLASS_UserManager::getInstance()->isAuthenticated();

            $featuresOut['likes'] = array(
                'count'=>count($likes),
                'selfLike'=>$selfLike,
                'allow'=>$isAllowed
            );
        }

        return $featuresOut;
    }

    private function mergeEventData($out, $eventData)
    {
        $out['toolbar'] = isset($eventData['toolbar']) ? $eventData['toolbar'] : null;
        $context = $eventData['context'];
        $out['context'] = $context;


        //TODO: add context id
        /*if( $context !== null && isset($context['url']) )
        {
            $url = $context['url'];
            $userNameStartIndex = strrpos($url, "/") + 1;
            $userName = substr($url, $userNameStartIndex);

            $user = BOL_UserService::getInstance()->findByUsername($userName);
            if( $user !== null )
            {
                $out['context']['userId'] = $user->getId();
                $out['userIds'][] = $user->getId();
                $userData = BOL_AvatarService::getInstance()->getDataForUserAvatars(array($user->getId()), true, false, true, true);
                $out['usersInfo'] = $out['usersInfo'] + $userData; //the + operator merge two arrays
            }
        }*/

        if( isset($eventData['content']['vars']['userList']) )
        {
            $userList = $eventData['content']['vars']['userList'];

            $out['content']['userList'] = array(
                'label' => $this->decodeString($userList['label']),
                'ids' => $userList['ids']
            );
        }

        foreach ( $eventData['contextMenu'] as $menuEntry )
        {
            if( $menuEntry['label'] === OW::getLanguage()->text("base", "flag") )
            {
                $out['contextActionMenu'][] = array(
                    'label' => $menuEntry['label'],
                    'actionType' => 'flag_content',
                    'params' => array(
                        'etype' => $menuEntry['attributes']['data-etype'],
                        'eid' => $menuEntry['attributes']['data-eid'],
                        'reason' => null
                    )
                );
            }
            else if ( $menuEntry['label'] === OW::getLanguage()->text("newsfeed", "feed_delete_item_label") )
            {
                $out['contextActionMenu'][] = array(
                    'label' => $menuEntry['label'],
                    'actionType' => 'delete_post',
                    'params' => array(
                        'actionId' => $out['id']
                    )
                );
            }
        }

        return $out;
    }
}