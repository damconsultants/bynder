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
namespace DamConsultants\Bynder\Controller\Updatedata;

ini_set('max_execution_time', 0);
ini_set('display_errors', 'Off');
error_reporting(0);

use Magento\Catalog\Api\ProductRepositoryInterface;
/**
 * Class Index
 *
 * @package DamConsultants\Bynder\Controller\Updatedata
 */
class Index extends \Magento\Framework\App\Action\Action
{
    protected $storeManager;
    protected $urlInterface;
    protected $scopeConfig;
    protected $resource;
    protected $productActionObject;
    private $productRepository;

    public function __construct(
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\App\Action\Context $context,
        \DamConsultants\Bynder\Helper\Data $Data_helper,
        \Magento\Catalog\Model\ProductRepository $productmodleRepository,
        \Magento\Catalog\Model\Product\Action $productActionObject,
        ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->cookieManager = $cookieManager;
        $this->productActionObject = $productActionObject;
        $this->productRepository = $productRepository;
        $this->_resource = $resource->getConnection();
        $this->_storeManager = $storeManager;
        $this->_urlInterface = $urlInterface;
        $this->scopeConfig = $scopeConfig;
        $this->data_helper = $Data_helper;
        $this->_product = $productmodleRepository;
        return parent::__construct($context);
    }

    public function execute()
    {
        $res_array = array(
            "status" => 0,
            "data" => 0,
            "message" => "Something went wrong. Please reload the page and try again.."
        );
        $tableName = $this->_resource->getTableName("bynder_data_product");
        $base_url = $this->_storeManager->getStore()->getBaseUrl();
        $entity_id = $this->getRequest()->getPost("entity_id");
        $up_images_data = $this->getRequest()->getPost('up_images');
        $up_document_data = $this->getRequest()->getPost('up_document');
        $up_videos_data = $this->getRequest()->getPost('up_videos');
        $fcookie = $this->cookieManager->getCookie('fcookie');
        if ($this->getRequest()->isAjax()) {
            if (isset($entity_id) && !empty($entity_id) && $entity_id != 0) {
                $productId = $this->getRequest()->getPost('entity_id');
                if (isset($up_images_data)) {
                    $diff_array = [];
                    $up_images = $this->getRequest()->getPost('up_images');

                    $productData = $this->_product->getById($productId);
                    $bynder_multi_img = $productData->getData('bynder_multi_img');

                    $product_url_key = $productData->getUrlKey();
                    $product_url = $base_url . $product_url_key . '.html';

                    $data = trim($bynder_multi_img);
                    $url_data = array();
                    if (!empty($data)) {
                        $ex = explode("\n", $data);
                        $ex = array_filter($ex);
                        foreach ($ex as $v) {
                            array_push($url_data, $v);
                        }
                    }

                    $request_image = $bynder_multi_img;
                    $request_image_url_data = array();
                    if (!empty($request_image)) {
                        $ex_image = explode("\n", $request_image);
                        $ex_image = array_filter($ex_image);
                        foreach ($ex_image as $v) {
                            array_push($request_image_url_data, $v);
                        }
                    }
                    $trimmed_array_product = array_map('trim', $url_data);
                    $diff_array = array_diff($trimmed_array_product, $request_image_url_data);

                    $assets_track = $this->data_helper->bynder_changemetadata_delete_assets($product_url, $diff_array);
                    $this->productActionObject->updateAttributes(
                        array($productId),
                        array('bynder_multi_img' => $up_images),
                        0
                    );
                }
                if (isset($up_document_data)) {
                    $up_document = $this->getRequest()->getPost('up_document');
                    $this->productActionObject->updateAttributes(
                        array($productId),
                        array('bynder_document' => $up_document),
                        0
                    );
                }
                if (isset($up_videos_data)) {
                    $up_videos = $this->getRequest()->getPost('up_videos');
                    $this->productActionObject->updateAttributes(
                        array($productId),
                        array('bynder_videos' => $up_videos),
                        0
                    );
                }
                $res_array["status"] = 1;
                $res_array["message"] = "edit product";
            } else {
                if (isset($fcookie) && !empty($fcookie)) {
                    $id = $fcookie;
                    $video_json = " ";
                    if (isset($up_videos_data) && !empty($up_videos_data)) {
                        $video_url = trim($up_videos_data);
                        if (!empty($video_url)) {
                            $ex = explode("\n", $video_url);
                            $ex = array_filter($ex);
                            $x = [];
                            foreach ($ex as $k => $v) {
                                array_push($x, $v);
                            }
                            $video_json = json_encode($x);
                            $video = ['video_url'=>$video_json];
                            $where = ['bynder_id=?'=>(int)$id];
                            $this->_resource->update($tableName, $video, $where);
                        }
                    }
                    $doc_json = " ";
                    if (isset($up_document_data) && !empty($up_document_data)) {
                        $doc_url = trim($up_document_data);
                        if (!empty($doc_url)) {
                            $ex = explode("\n", $doc_url);
                            $ex = array_filter($ex);
                            $x = [];
                            foreach ($ex as $k => $v) {
                                array_push($x, $v);
                            }
                            $doc_json = json_encode($x);
                            $doc = ['doc_json'=>$doc_json];
                            $where = ['bynder_id=?'=>(int)$id];
                            $this->_resource->update($tableName, $doc, $where);
                        }
                    }
                    $images_json = " ";
                    if (isset($up_images_data) && !empty($up_images_data)) {
                        $img_data = trim($up_images_data);
                        if (!empty($img_data)) {
                            $ex = explode("\n", $img_data);
                            $ex = array_filter($ex);
                            $x = [];
                            foreach ($ex as $k => $v) {
                                array_push($x, $v);
                            }
                            $images_json = json_encode($x);
                            $image = ['images_json'=>$images_json];
                            $where = ['bynder_id=?'=>(int)$id];
                            $this->_resource->update($tableName, $image, $where);
                        }
                    }
                    $res_array["status"] = 2;
                    $res_array["message"] = "new product";
                }
            }
        }
        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }

    public function strjson($str)
    {
        $ex = explode("\n", $str);
        $ex = array_filter($ex);
        $res = [];
        foreach ($ex AS $k => $v) {

        }
        return $res;
    }

    public function removedb($action)
    {
        if (isset($fcookie) && !empty($fcookie)) {
            $id = $fcookie;
            $tableName = $this->_resource->getTableName("bynder_data_product");
            if ($action == "video") {
                    $video = ['video_url'=>''];
                    $where = ['bynder_id=?'=>(int)$id];
                    $this->_resource->update($tableName,$video,$where);

            }
            if ($action == "image") {
                    $image = ['images_json'=>''];
                    $where = ['bynder_id=?'=>(int)$id];
                    $this->_resource->update($tableName,$image,$where);

            }
            if ($action == "doc") {
                    $doc = ['doc_json'=>''];
                    $where = ['bynder_id=?'=>(int)$id];
                    $this->_resource->update($tableName,$doc,$where);

            }
        }
        return true;
    }
    public function getConfig($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}