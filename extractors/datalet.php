<?php
class EXTFEED_EXTRACTOR_Datalet extends EXTFEED_CLASS_PostExtractor
{

    public function extractContent($data)
    {
        $dataletService = ODE_BOL_Service::getInstance();
        $dataletPost = $dataletService->getDataletByPostId($data['entityId'], $data['pluginKey']);

        /** @var ODE_BOL_Datalet $datalet */
        $datalet = $dataletService->getDataletById($dataletPost['dataletId']);

        $dataletId = $datalet->getId();

        $dataletUrl = OW::getRouter()->urlForRoute("spodshowcase.share_datalet", array('datalet_id'=>$dataletId));


        $ode_dir = OW::getPluginManager()->getPlugin('ode')->getDirName();
        $url_img = OW_URL_HOME . 'ow_plugins/' . $ode_dir . '/datalet_images/datalet_' . $dataletId . '.png';

        $content = array(
            'status'=> $this->findStatus($data),
            'dataletId' => $dataletId,
            'previewImage' => $url_img,
            'url' => $dataletUrl
        );

        return $content;
    }
}