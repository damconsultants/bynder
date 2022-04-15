<?php
/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 *  DamConsultants_Bynder
 */
namespace DamConsultants\Bynder\Controller\Redirecturl;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Magento\Framework\App\Action\Action;
/**
 * Class Index
 *
 * @package DamConsultants\Bynder\Controller\Redirecturl
 */
class Index extends Action
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \DamConsultants\Bynder\Helper\Data $bynderData
    ) {
        $this->b_datahelper = $bynderData;
        $this->redirecturi = "bynder/redirecturl";
        return parent::__construct($context);
    }
    public function execute()
    {
        $res_array = array(
            "status" => 0,
            "html" => "",
            "data" => "",
            "message" => "something went wrong. please re-login & try again",
            "_POST" => $this->getRequest()->getPost(),//$_POST,
            "_GET" => $this->getRequest()->getParams(),//$_GET,
            "getcwd" => getcwd()
        );

        $res_array["status"] = 1;
        $res_array["message"] = "redirecturl";
        $res_array["getbaseurl"] = $this->getbaseurl();
        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }
    public function getbaseurl()
    {
        $this->b_datahelper->getbaseurl();
    }
    public function redirecturi()
    {
        return "redirecturi";
    }
}
