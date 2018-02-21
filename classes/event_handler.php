<?php

class EXTFEED_CLASS_EventHandler
{
    /**
     * Singleton instance.
     *
     * @var EXTFEED_CLASS_EventHandler
     */
    private static $classInstance;

    const DATALET_FORMAT = 'datalet';

    /**
     * Returns an instance of class (singleton pattern implementation).
     *
     * @return EXTFEED_CLASS_EventHandler
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    private function __construct()
    {
    }

    public function findDatalet( OW_Event $event )
    {
        if( !OW::getPluginManager()->isPluginActive("ode") )
        {
            return;
        }

        $eventData = $event->getParams();

        /** @var NEWSFEED_CLASS_Action $action */
        $action = $eventData['action'];

        $datalet = ODE_BOL_Service::getInstance()->getDataletByPostId($action->getEntity()->id, $action->getPluginKey());


        if( !empty($datalet) )
        {
            $action->setFormat(self::DATALET_FORMAT);
        }
        $eventData['action'] = $action;

        $event->setData($eventData);
    }

    public function addDataletOnComment( OW_Event $e )
    {
        if( !OW::getPluginManager()->isPluginActive("ode") )
        {
            return;
        }

        $params = $e->getParams();
        $data = $e->getData();

        $commentId = $params['id'];

        $dataletPost = ODE_BOL_Service::getInstance()->getDataletByPostId($commentId, 'comment');

        if( !empty($dataletPost) )
        {
            /** @var ODE_BOL_Datalet $dataletPost */
            $datalet = ODE_BOL_Service::getInstance()->getDataletById($dataletPost['dataletId']);
            $dataletId = $datalet->getId();

            $dataletUrl = OW::getRouter()->urlForRoute("spodshowcase.share_datalet", array('datalet_id'=>$dataletId));


            $ode_dir = OW::getPluginManager()->getPlugin('ode')->getDirName();
            $url_img = OW_URL_HOME . 'ow_plugins/' . $ode_dir . '/datalet_images/datalet_' . $dataletId . '.png';

            $attachment = array(
                'type'=> 'datalet',
                'dataletId' => $dataletId,
                'previewImage' => $url_img,
                'dataletUrl' => $dataletUrl
            );

            $data['attachment'] = $attachment;

            $e->setData($data);
        }
    }

    public function addDataletExtractor( OW_Event $event )
    {
        $param = $event->getParams();

        if( $param['format'] === self::DATALET_FORMAT )
        {
            $event->setData(array(
                'format' => self::DATALET_FORMAT,
                'class' => 'EXTFEED_EXTRACTOR_Datalet'
            ));
        }
    }

    public function genericInit()
    {
        OW::getEventManager()->bind("extfeed.before_post_extraction", array($this, 'findDatalet'));
        OW::getEventManager()->bind("extfeed.collect_comment_attachment", array($this, 'addDataletOnComment'));
        OW::getEventManager()->bind("extfeed.on_extractor_search", array($this, 'addDataletExtractor'));
    }
}