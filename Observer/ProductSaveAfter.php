<?php

namespace DamConsultants\Bynder\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;


class ProductSaveAfter implements ObserverInterface
{

    protected $_resource;
    protected $productActionObject;
    protected $logger;
    private $productRepository;
    private $b_helper;

    public function __construct(
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Catalog\Model\Product\Action $productActionObject,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem $Filesystem,
        ProductRepositoryInterface $productRepository,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Catalog\Model\Product $productModel,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        \DamConsultants\Bynder\Helper\Data $bynder_helper_data
    ) {
        $this->cookieManager = $cookieManager;
        $this->_resource = $resource->getConnection();
        $this->productActionObject = $productActionObject;
        $this->Filesystem = $Filesystem;
        $this->productModel = $productModel;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->b_helper = $bynder_helper_data;
        $this->_byndersycData = $byndersycData;
        $this->_storeManager = $storeManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $product = $observer->getProduct();
        $productId = $observer->getProduct()->getId();
        $bynder_image_import = $product->getData('bynder_image_import');
        $bynder_multi_img = $product->getData('bynder_multi_img');
        $bynder_document = $product->getData('bynder_document');
        $bynder_videos = $product->getData('bynder_videos');
        $product_sku_key = $product->getData('sku');

        $base_url = $this->_storeManager->getStore()->getBaseUrl();
        $product_url_key = $product->getUrlKey();
        $product_url = $base_url . $product_url_key . '.html';
        $fcookie = $this->cookieManager->getCookie('fcookie');
        $model = $this->_byndersycData->create();
        try {
            if (isset($fcookie) && !empty($fcookie)) {
                $db_id = $fcookie;
                $tableName = $this->_resource->getTableName('bynder_data_product');
                $select = $this->_resource->select()->from(['c' => $tableName], ['*'])->where('c.bynder_id=?', (int)$db_id);
                $result = $this->_resource->fetchRow($select);

                if ($result && is_array($result) == 1 && isset($result["images_json"]) && !empty($result["images_json"])) {
                    $url_data = [];
                    $data = trim($bynder_multi_img);
                    if (!empty($data)) {
                        $ex = explode("\n", $data);
                        $ex = array_filter($ex);
                        foreach ($ex as $v) {
                            array_push($url_data, $v);
                        }
                    }

                    $cookie_array = json_decode($result["images_json"], true);
                    $cookie_array = array_filter($cookie_array);
                    foreach ($cookie_array as $item) {
                        if (!in_array($item, $url_data)) {
                            $bynder_multi_img .=  $item . " \n";
                            array_push($url_data, $item);
                        }
                        $url_explode = explode("https://", $item);
                        $url_filter = array_filter($url_explode);
                        foreach ($url_filter as $media_value) {
                            $media_explode = explode("/", $media_value);
                            $data_value_1 = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $item,
                                'bynder_data_type' => '1',
                                'media_id' => $media_explode[3],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '2',
                                'added_date' => time()
                            );
                            $model->setData($data_value_1);
                            $model->save();
                        }
                    }
                    $api_call = $this->b_helper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->b_helper->get_bynder_changemetadata_assets($product_url, $url_data);
                        $this->productActionObject->updateAttributes(array($productId), array('bynder_multi_img' => $bynder_multi_img), 0);
                        unset($url_data);
                    }
                }
                if ($result && is_array($result) == 1 && isset($result["doc_json"]) && !empty($result["doc_json"])) {
                    $url_data = [];
                    $data = trim($bynder_document);
                    if (!empty($data)) {
                        $ex = explode("\n", $data);
                        $ex = array_filter($ex);
                        foreach ($ex as $v) {
                            array_push($url_data, $v);
                        }
                    }

                    $doc_array = json_decode($result["doc_json"], true);
                    $doc_array = array_filter($doc_array);
                    foreach ($doc_array as $item) {
                        if (!in_array($item, $url_data)) {
                            $bynder_document .= $item . " \n";
                            array_push($url_data, $item);
                        }
                        $url_doc_explode = explode("https://", $item);
                        $url_doc_filter = array_filter($url_doc_explode);
                        foreach ($url_doc_filter as $media_doc_value) {
                            $media_doc_explode = explode("/", $media_doc_value);
                            $data_doc_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $item,
                                'bynder_data_type' => '2',
                                'media_id' => $media_doc_explode[3],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '2',
                                'added_date' => time()
                            );
                           $model->setData($data_doc_value);
                           $model->save();
                        }
                    }
                    $api_call = $this->b_helper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->b_helper->get_bynder_changemetadata_assets_doc($product_url, $url_data);
                        $this->productActionObject->updateAttributes(array($productId), array('bynder_document' => $bynder_document), 0);
                        unset($url_data);
                    }
                }
                if ($result && is_array($result) == 1 && isset($result["video_url"]) && !empty($result["video_url"])) {
                    $url_data = [];
                    $data = trim($bynder_videos);
                    if (!empty($data)) {
                        $ex = explode("\n", $data);
                        $ex = array_filter($ex);
                        foreach ($ex as $v) {
                            array_push($url_data, $v);
                        }
                    }

                    $video_array = json_decode($result["video_url"], true);

                    $video_array = array_filter($video_array);
                    foreach ($video_array as $item) {
                        if (!in_array($item, $url_data)) {
                            $bynder_videos .= $item . " \n";
                            array_push($url_data, $item);
                        }
                        $url_video_explode = explode("https://", $item);
                        $url_video_filter = array_filter($url_video_explode);
                        foreach ($url_video_filter as $media_video_value) {
                            $media_video_explode = explode("/", $media_video_value);
                            $data_video_data = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $item,
                                'bynder_data_type' => '3',
                                'media_id' => $media_video_explode[3],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '2',
                                'added_date' => time()
                            );
                           $model->setData($data_video_data);
                           $model->save();
                        }
                    }
                    $api_call = $this->b_helper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->b_helper->get_bynder_changemetadata_assets_video($product_url, $url_data);
                        $this->productActionObject->updateAttributes(array($productId), array('bynder_videos' => $bynder_videos), 0);
                        unset($url_data);
                    }
                }
            }
            if ($bynder_image_import == 1) {
                $img_dir = BP . '/pub/media/temp/';
                if (isset($fcookie) && !empty($fcookie)) {
                    $bynder = 0;
                    $db_id = $fcookie;
                    $tableName = $this->_resource->getTableName('bynder_data_product');
                    $select = $this->_resource->select()->from(['c' => $tableName], ['*'])->where('c.bynder_id=?', (int)$db_id);
                    $result = $this->_resource->fetchRow($select);
                    if (COUNT($result) != 0 && isset($result["images_json"]) && !empty($result["images_json"])) {
                        $cookie_array = json_decode($result["images_json"], true);
                        $cookie_array = array_filter($cookie_array);
                        $this->b_helper->unsetcookie();
                        $i = 1;
                        foreach ($cookie_array as $item) {
                            $bynder_multi_img .= $item . "\n";
                            $bynder = $this->b_helper->upload_data($productId, $item, $bynder_multi_img, $i);
                            $i++;
                        }
                        sleep(1);
                        $this->b_helper->unsetcookie();
                        $this->rrmdir($img_dir);
                        $this->removedb($db_id);
                        return $this->getResponse()->setBody(true);
                    }
                    if (!empty($bynder)) {

                        $this->b_helper->unsetcookie();
                    }
                    $this->removedb($db_id);
                }
                $this->b_helper->unsetcookie();
                return true;
            } else {
                $this->b_helper->unsetcookie();
            }
        } catch (Exception $ex) {
            $this->logger->debug($ex);
        }
        return true;
    }

    public function removedb($id)
    {
        if (!empty($id)) {
            $tableName = $this->_resource->getTableName('bynder_data_product');
            $where = ['bynder_id=?' => (int)$id];
            $this->_resource->delete($tableName, $where);
        }
        return true;
    }

    public function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir")
                        $this->rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    protected function getMediaDirTmpDir()
    {
        $mediaPath = $this->Filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath('tmp/');
        return $mediaPath;
    }
}
