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

namespace DamConsultants\Bynder\Controller\Index;

use DamConsultants\Bynder\Helper\Data;

class Index extends \Magento\Framework\App\Action\Action
{

    /**
     * @var $bynderDomain
     */
    public $bynderDomain = "";

    /**
     * @var $permanent_token
     */
    public $permanent_token = "";

    /**
     * @var $by_redirecturl
     */
    public $by_redirecturl;

    /**
     * Index
     * @param \Magento\Framework\App\Action\Context $context
     * @param Data $bynderData
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Data $bynderData
    ) {
        $this->b_datahelper = $bynderData;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $res_array = [
            "status" => 0,
            "data" => 0,
            "message" => "something went wrong please try again. |
            please logout and login again"
        ];
        $databaseId = $this->getRequest()->getPost("databaseId");
        $datasetType = $this->getRequest()->getPost("datasetType");
        $bdomain = $this->getRequest()->getPost("bdomain");
        if ($this->getRequest()->isAjax()) {
            if (isset($databaseId) && count($databaseId) > 0
                && isset($datasetType) && count($datasetType) > 0
                && isset($bdomain) && !empty($bdomain)
            ) {
                $og_media_ids = $databaseId;
                $dataset_types = $datasetType;
                $bdomain = (string) $bdomain;
                $bynder_auth = $this->loadcredential();
                if ($bynder_auth == 1) {
                    $bdomain_chk_cookies = str_replace("https://", "", $bdomain);
                    $bdomain_chk_config = str_replace(
                        "https://",
                        "",
                        $this->b_datahelper->getBynderDom()
                    );
                    if ($bdomain_chk_cookies == $bdomain_chk_config) {
                        $bynder_auth = [
                            "bynderDomain" => $bdomain_chk_config,
                            "redirectUri" => $this->b_datahelper->getRedirecturl(),
                            "token" => $this->b_datahelper->getPermanenToken(),
                            "og_media_ids" => $og_media_ids,
                            "dataset_types" => $dataset_types
                        ];
                        $api_response = $this->b_datahelper->getDerivativesImage($bynder_auth);
                        $api_response = json_decode($api_response, true);
                           
                        if (isset($api_response["status"]) && $api_response["status"] == 1) {
                            $res_array["status"] = $api_response["status"];
                            $res_array["data"] = $api_response["data"];
                            $res_array["message"] = $api_response["message"];
                            $res_array["bynder_auth"] = $bynder_auth;
                        } else {
                            $res_array["data"] = $api_response;
                            $res_array["message"] = $api_response["message"];
                        }
                    } else {
                        $res_array["message"]="Please Check Your Entered Bynder Domain | Please Check Your Credentials";
                    }
                } else {
                    $res_array["message"] = $bynder_auth;
                }
            } else {
                $res_array[
                    "message"
                    ]="Please check your credentials | session has expired. please logout and login again";
            }
        }
        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }
    /**
     * Useing Helper
     *
     * @return getLoadCredential
     */
    public function loadcredential()
    {
        return $this->b_datahelper->getLoadCredential();
    }
}
