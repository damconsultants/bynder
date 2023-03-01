<?php

namespace DamConsultants\Bynder\Cron;

use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Bynder\Model\BynderFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class FeatchNullDataToMagento
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Featch Null Data To Magento
     * @param LoggerInterface $logger
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Bynder\Helper\Data $DataHelper
     * @param \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData
     * @param Action $action
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param BynderFactory $bynder
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        BynderFactory $bynder
    ) {

        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_byndersycData = $byndersycData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("Fetch Null Attribute Value");
        $product_collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'null' => true],
                    ['attribute' => 'bynder_document', 'null' => true]
                ]
            )
            ->load();
            $product_sku_limit = $this->datahelper->getProductSkuLimitConfig();
        if (!empty($product_sku_limit)) {
            $product_collection->getSelect()->limit($product_sku_limit);
        } else {
            $product_collection->getSelect()->limit(50);
        }
        
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        
        if (!empty($collection)) {
            $collections = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
            $property_id = $collections;
        } else {
            $property_id = null;
        }
        foreach ($product_collection as $product) {
            
            $productSku_array[] = $product->getSku();
        }
        $logger->info("Product_SKU Start");
        $logger->info($productSku_array);
        $logger->info("Product SKU End");

        if (count($productSku_array) > 0) {
            foreach ($productSku_array as $sku) {
                $get_data = $this->datahelper->getImageSyncWithProperties($sku, $property_id);
                $respon_array = json_decode($get_data, true);
                $logger->info("respon_array");
                $logger->info($respon_array);
                $logger->info("respon_array");
                if ($respon_array['status'] == 1) {
                    $convert_array = json_decode($respon_array['data'], true);
                    $this->getDataItem($convert_array);
                }
            }
        } else {
            $logger->info('No Data Found For SKU.');
        }

        return $this;
    }
    /**
     * Get Data Item
     *
     * @param array $convert_array
     */
    public function getDataItem($convert_array)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("getDataItem funcation called");
        $data_arr = [];
        $data_val_arr = [];
        $temp_arr = [];
        if ($convert_array['status'] != 0) {
            foreach ($convert_array['data'] as $data_value) {
                $image_data = $data_value['thumbnails'];
                $data_sku  = $data_value['property_TestMetaProperty'];
                if ($data_value['type'] == "image") {
                    array_push($data_arr, $data_sku[0]);
                    $data_p = [
                        "sku" => $data_sku[0],
                        "url" => $image_data["image_link"],
                        "type" => $data_value['type']
                    ];
                    array_push($data_val_arr, $data_p);
                } else {
                    if ($data_value['type'] == 'video') {
                        $video_link =  $image_data["image_link"] . '@@' . $image_data["webimage"];
                        array_push($data_arr, $data_sku[0]);
                        $data_p = [
                            "sku" => $data_sku[0],
                            "url" => $video_link,
                            "type" => $data_value['type']
                        ];
                        array_push($data_val_arr, $data_p);
                    } else {
                        $doc_name = $data_value["name"];
                            $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
                            $doc_link =  $image_data["image_link"] . '@@' . $doc_name_with_space;
                        array_push($data_arr, $data_sku[0]);
                        $data_p = [
                            "sku" => $data_sku[0],
                            "url" => $doc_link,
                            "type" => $data_value['type']
                        ];
                        array_push($data_val_arr, $data_p);
                    }
                }
            }
        } else {
            $logger->info('No Data Found For API Side.');
        }
        $this->getProcessItem($data_arr, $data_val_arr, $temp_arr);
    }
    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @param array $temp_arr
     */
    public function getProcessItem($data_arr, $data_val_arr, $temp_arr)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("getProcessItem funcation called");
        if (count($data_arr) > 0) {
            foreach ($data_arr as $key => $temp) {
                $temp_arr[$temp][$data_val_arr[$key]["type"]]["url"][] = $data_val_arr[$key]["url"];
            }

            foreach ($temp_arr as $product_sku_key => $image_value) {

                foreach ($image_value as $kk => $vv) {
                    $img_json = implode(" \n", $vv["url"]);
                    $item_type = $kk;
                    $this->getImageUPdate($img_json, $product_sku_key, $item_type);
                }
            }
        } else {
            $logger->info('No Data Found For Data Array.');
        }
    }
    /**
     * Update Item
     *
     * @return $this
     * @param string $img_json
     * @param string $product_sku_key
     * @param string $item_type
     */
    public function getImageUPdate($img_json, $product_sku_key, $item_type)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("funcation called");
        $byndeimageconfig = $this->datahelper->byndeimageconfig();
        $img_roles = explode(",", $byndeimageconfig);
        $model = $this->_byndersycData->create();
        try {

            $storeId = $this->storeManagerInterface->getStore()->getId();

            $_product = $this->_productRepository->get($product_sku_key);

            $product_ids = $_product->getId();
            if ($item_type == "image") {

                $new_image_array = explode(" \n", $img_json);
                $image_detail = [];
                foreach ($new_image_array as $image_new_value) {
                    $item_url = explode("?", $image_new_value);
                    $media_image_explode = explode("/", $item_url[0]);
                    
                    $image_detail[] = [
                        "item_url" => $item_url[0],
                        "image_role" => $img_roles,
                        "item_type" => 'IMAGE',
                        "thum_url" => $item_url[0]
                    ];
                        $data_image_data = [
                            'sku' => $product_sku_key,
                            'bynder_data' =>$item_url[0],
                            'bynder_data_type' => '1',
                            'media_id' => $media_image_explode[5],
                            'remove_for_magento' => '1',
                            'added_on_cron_compactview' => '1',
                            'added_date' => time()
                        ];
                        
                        $model->setData($data_image_data);
                        $model->save();
                    
                }
                /*  IMAGE & VIDEO == 1
                IMAGE == 2
                VIDEO == 3 */
                foreach ($image_detail as $img) {
                    $type[] = $img['item_type'];
                }
                if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
                    $flag = 1;
                } elseif (in_array("IMAGE", $type)) {
                    $flag = 2;
                } elseif (in_array("VIDEO", $type)) {
                    $flag = 3;
                }
                $this->action->updateAttributes([$product_ids], ['bynder_isMain' => $flag], $storeId);
                $new_value_array = json_encode($image_detail, true);
                $this->action->updateAttributes([$product_ids], ['bynder_multi_img' => $new_value_array], $storeId);

            } elseif ($item_type == "document") {

                $new_doc_array = explode(" \n", $img_json);
                $doc_detail = [];
                foreach ($new_doc_array as $doc_value) {
                    $item_url = explode("?", $doc_value);
                    $media_doc_explode = explode("/", $item_url[0]);
                    $doc_detail[] = [
                        "item_url" => $item_url[0],
                        "item_type" => 'DOCUMENT',
                    ];
                    $data_doc_value = [
                        'sku' => $product_sku_key,
                        'bynder_data' => $item_url[0],
                        'bynder_data_type' => '2',
                        'media_id' => $media_doc_explode[4],
                        'remove_for_magento' => '1',
                        'added_on_cron_compactview' => '1',
                        'added_date' => time()
                    ];
                    $model->setData($data_doc_value);
                    $model->save();
                }
                $new_value_array =json_encode($doc_detail, true);
                $this->action->updateAttributes([$product_ids], ['bynder_document' => $new_value_array], $storeId);

                // documents section

            } else {

                $new_video_array = explode(" \n", $img_json);
                $trimmed_new_video_array = array_map('trim', $new_video_array);
                $new_video_value = implode(" \n", $trimmed_new_video_array);
                foreach ($new_video_array as $video_value) {
                        
                    $item_url = explode("?", $video_value);
                    $thum_url = explode("@@", $video_value);
                    $media_video_explode = explode("/", $item_url[0]);
                    
                        $video_detail[] = [
                            "item_url" => $item_url[0],
                            "image_role" => null,
                            "item_type" => 'VIDEO',
                            "thum_url" => $thum_url[1]
                        ];
                        $data_video_data = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'bynder_data_type' => '3',
                            'media_id' => $media_video_explode[5],
                            'remove_for_magento' => '1',
                            'added_on_cron_compactview' => '1',
                            'added_date' => time()
                        ];
                        $model->setData($data_video_data);
                        $model->save();
                    
                }
                /*  IMAGE & VIDEO == 1
                IMAGE == 2
                VIDEO == 3 */
                foreach ($video_detail as $img) {
                    
                    $type[] = $img['item_type'];
                }
                if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
                    $flag = 1;
                } elseif (in_array("IMAGE", $type)) {
                    $flag = 2;
                } elseif (in_array("VIDEO", $type)) {
                    $flag = 3;
                }
                $this->action->updateAttributes([$product_ids], ['bynder_isMain' => $flag], $storeId);
                $new_value_array = json_encode($video_detail, true);
                $this->action->updateAttributes([$product_ids], ['bynder_multi_img' => $new_value_array], $storeId);

            }
        } catch (\Exception $e) {
            $logger->info($e->getMessage());
        }
    }
}
