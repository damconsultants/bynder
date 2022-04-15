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
namespace DamConsultants\Bynder\Controller\BynderIndex;

use DamConsultants\Bynder\Helper\Data;
/**
 * Class Index
 *
 * @package DamConsultants\Bynder\Controller\BynderIndex
 */
class Index extends \Magento\Framework\App\Action\Action
{
    public $clientId = "";
    public $clientSecret = "";
    public $bynderDomain = "";
    public $permanent_token = "";

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Data $bynderData
    ) {
        $this->b_datahelper = $bynderData;
        return parent::__construct($context);
    }

    public function execute()
    {
        $res_array = array(
            "status" => 0,
            "data" => 0,
            "message" => "something went wrong please try again. |
            please logout and login again"
        );
        $img_data_post = $this->getRequest()->getPost("img_data");
        $dir_path_post = $this->getRequest()->getPost("dir_path");
        if ($this->getRequest()->isAjax()) {
            if (isset($img_data_post) && count($img_data_post) > 0) {
                if (isset($dir_path_post) && !empty($dir_path_post)) {
                    $img_dir = BP . '/pub/media/' . $dir_path_post;
                    if (!is_dir($img_dir)) {
                        mkdir($img_dir, 0755, true);
                    }
                    $cookie_array = $img_data_post;
                    foreach ($cookie_array as $item) {
                        $item_url = trim($item);
                        if (!empty($item_url)) {
                            $basename = basename($item_url);
                            $file_name = explode("?", $basename);
                            $file_name = $file_name[0];
                            $file_name = str_replace("%20", " ", $file_name);
                            $img_url = $img_dir . "/" . $file_name;
                            file_put_contents(
                                $img_url,
                                file_get_contents($item_url)
                            );
                        }
                    }
                    $res_array["status"] = 1;
                    $res_array["message"] = "successfull ";
                } else {
                    $res_array["message"] = "Something went wrong.
                    Please reload the page and try again.";
                }
            } else {
                $res_array["message"] = "Sorry,
                you not selected any item ?.
                Please select item and try again";
            }
        }
        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }

    public function loadcredential()
    {
        $this->b_datahelper->getLoadCredential();
    }
}
