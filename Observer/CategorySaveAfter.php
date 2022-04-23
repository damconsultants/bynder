<?php

namespace DamConsultants\Bynder\Observer;

use Magento\Catalog\Model\Category;
use Magento\Framework\Event\ObserverInterface;

class Categorysaveafter implements ObserverInterface
{
    protected $helper;
    protected $cmsHelper;
    public function __construct(
        \DamConsultants\Bynder\Helper\Data $dataHelper
    ) {
        $this->_datahelper = $dataHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $category = $observer->getEvent()->getData('category');
        $url = $category->getUrlPath();
        $BaseUrl = $this->_datahelper->getbaseurl();
        $categoryUrl = $BaseUrl . $url . '.html';

        $arr = array(
            $category->getDescription()
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
            $category_description = array_filter($url_data);
            $api_call = $this->_datahelper->check_bynder();
            $api_response = json_decode($api_call,true);
            if (isset($api_response['status']) == 1) {
                $assets = $this->_datahelper->bynder_data_cms_page($categoryUrl, $category_description);
            }
        }
    }
}
