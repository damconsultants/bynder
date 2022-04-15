<?php

namespace DamConsultants\Bynder\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CmsPageSaveAfterObserver implements ObserverInterface
{
   protected $helper;
   protected $cmsHelper;
   public function __construct(
      \DamConsultants\Bynder\Helper\Data $dataHelper,
      \Magento\Cms\Helper\Page $cmsHelper
   ) {
      $this->_datahelper = $dataHelper;
      $this->cmsHelper = $cmsHelper;
   }
   public function execute(Observer $observer)
   {

      $page = $observer->getObject();


      $arr = array(
         $page->getData('content')
      );

      $url_data = [];

      $str = implode(" ", $arr);
      $image_arr = explode(" ", $str);
      foreach ($image_arr as $a) {
         preg_match('@src="([^"]+)"@', $a, $match);
         $src = array_pop($match);
         $img_arr = explode('?', $src);
         $url_data[] = $img_arr[0];
      }

      if (!empty($url_data)) {
         $cmspage = array_filter($url_data);
         $pageId = $page->getData('page_id');
         $CMSPageURL = $this->cmsHelper->getPageUrl($pageId);
         $api_call = $this->_datahelper->check_bynder();
         $api_response = json_decode($api_call, true);
         if ($api_response['status'] == 1) {
            $assets = $this->_datahelper->bynder_data_cms_page($CMSPageURL, $cmspage);
         }
      }
   }
}
