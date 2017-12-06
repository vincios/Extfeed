<?php
/**
 * Created by PhpStorm.
 * User: vinnun
 * Date: 25/10/2017
 * Time: 16:42
 */
OW::getAutoloader()->addPackagePointer("EXTFEED_EXTRACTOR", OW_DIR_PLUGIN."extfeed".DS."extractors".DS);

EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("", "EXTFEED_EXTRACTOR_Empty");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("text","EXTFEED_EXTRACTOR_Text");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("empty", "EXTFEED_EXTRACTOR_Empty");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("content", "EXTFEED_EXTRACTOR_ImageContent");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("image_content", "EXTFEED_EXTRACTOR_ImageContent");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("image_list", "EXTFEED_EXTRACTOR_ImageList");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("image", "EXTFEED_EXTRACTOR_Image");
EXTFEED_CLASS_ExtractorsManager::getInstance()->addExtractor("video","EXTFEED_EXTRACTOR_Video");

EXTFEED_CLASS_EventHandler::getInstance()->genericInit();