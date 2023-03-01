<?php

namespace DamConsultants\Bynder\Cron;

use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\Product\Action;
use Magento\Store\Model\StoreManagerInterface;
use DamConsultants\Bynder\Model\BynderFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class AutoAddFormMagento
{

    /**
     * Auto Replace From Magento
     * @param ProductRepository $productRepository
     * @param Attribute $attribute
     * @param Action $action
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Bynder\Helper\Data $DataHelper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData
     * @param BynderSycDataCollectionFactory $byndersycDataCollection
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Psr\Log\LoggerInterface $logger
     * @param BynderFactory $bynder
     */
    public function __construct(
        ProductRepository $productRepository,
        Attribute $attribute,
        Action $action,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory $byndersycDataCollection,
        \Magento\Framework\App\ResourceConnection $resource,
        \Psr\Log\LoggerInterface $logger,
        BynderFactory $bynder
    ) {
        $this->_productRepository = $productRepository;
        $this->attribute = $attribute;
        $this->action = $action;
        $this->datahelper = $DataHelper;
        $this->collectionFactory = $collectionFactory;
        $this->_byndersycData = $byndersycData;
        $this->_byndersycDataCollection = $byndersycDataCollection;
        $this->_resource = $resource->getConnection();
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
        $this->_logger = $logger;
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
        $logger->info("DamConsultants Bynder Add  Cron");

        $productCollection =  $this->attribute->getCollection();
        $productColl = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

            $product_sku_limit = $this->datahelper->getProductSkuLimitConfig();
        if (!empty($product_sku_limit)) {
            $productColl->getSelect()->limit($product_sku_limit);
        } else {
            $productColl->getSelect()->limit(50);
        }
        $bynder = [];
        $bynder_attribute = ['bynder_multi_img',/* 'bynder_videos',*/ 'bynder_document'];

        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        if (!empty($collection)) {
            $collections = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
            $property_id = $collections;
        } else {
            $property_id = null;
        }
        $productSku_array = [];
        foreach ($productCollection as $products) {
            $bynder[] = $products->getAttributeCode();
        }
        if (array_intersect($bynder_attribute, $bynder)) {
            foreach ($productColl as $item) {
                $productSku_array[] = $item->getSku();
            }
            $logger->info("Product_SKU Start");
            $logger->info($productSku_array);
            $logger->info("Product SKU End");
            if (count($productSku_array) > 0) {
                foreach ($productSku_array as $sku) {
                    $get_data = $this->datahelper->getImageSyncWithProperties($sku, $property_id);
                    $respon_array = json_decode($get_data, true);
                    if ($respon_array['status'] == 1) {
                        $convert_array = json_decode($respon_array['data'], true);
                        $this->getDataItem($convert_array);
                    }
                }
            } else {
                $logger->info('No Data Found For SKU.');
            }
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
        $logger->info("Inner Funcation Called");
        $table_Name = $this->_resource->getTableName("bynder_cron_data");

        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);

            $product_ids = $_product->getId();
            $image_value = $_product->getBynderMultiImg();
            $doc_value = $_product->getBynderDocument();
            if ($item_type == "image") {
                $this->getImage($img_json, $image_value, $product_ids, $storeId, $table_Name, $product_sku_key);
                
            } elseif ($item_type == "document") {

                $this->getDocument($img_json, $doc_value, $product_ids, $storeId, $table_Name, $product_sku_key);
                // documents section

            } else {
                $this->getVideo($img_json, $image_value, $product_ids, $storeId, $table_Name, $product_sku_key);
                //}
                // video section

            }
        } catch (\Exception $e) {
            $logger->info($e->getMessage());
        }
    }
    /**
     * Get Image
     *
     * @param string $img_json
     * @param string $image_value
     * @param string $product_ids
     * @param string $storeId
     * @param string $table_Name
     * @param string $product_sku_key
     */
    public function getImage($img_json, $image_value, $product_ids, $storeId, $table_Name, $product_sku_key)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("GetImage Funcation Called");
        $byndeimageconfig = $this->datahelper->byndeimageconfig();
        $img_roles = explode(",", $byndeimageconfig);
        $model = $this->_byndersycData->create();
        $check_data_magetno_side = $this->_byndersycDataCollection->create()
                ->addFieldToFilter('sku', $product_sku_key);
        $data_collection = $check_data_magetno_side->getData();
        $media_array = [];
        $pervious_bynder_image = [];
        if (!empty($image_value)) {

            $new_image_array = explode(" \n", $img_json);
            $trimmed_new_array = array_map('trim', $new_image_array);
            $old_image_array = explode(" ", $image_value);
            
            $old_value_array = json_decode($image_value, true);
            $old_item_url = [];
            if (!empty($old_value_array)) {
                foreach ($old_value_array as $value) {
                    $old_item_url[] = $value['item_url'];
                
                }
            }
            foreach ($new_image_array as $image_new_value) {

                $item_url = explode("?", $image_new_value);
                if (!in_array($item_url[0], $old_item_url)) {
                     $image_detail[] = [
                         "item_url" => $item_url[0],
                         "image_role" => $img_roles,
                         "item_type" => 'IMAGE',
                         "thum_url" => $item_url[0]
                     ];
                }
            }
            $array_merge = array_merge($old_value_array, $image_detail);
            $new_value_array = json_encode($array_merge, true);
            $this->action->updateAttributes([$product_ids], ['bynder_multi_img' => $new_value_array], $storeId);
            /*  IMAGE & VIDEO == 1
            IMAGE == 2
            VIDEO == 3 */
            foreach ($array_merge as $img) {
                
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
            $trimmed_array = array_map('trim', $old_image_array);
            $trimmed_array_filter = array_filter($trimmed_array);

            $logger->info("old_image_array");
            $logger->info($trimmed_array_filter);
            $logger->info("old_image_array");

            $new_image_array = explode(" \n", $img_json);
            $trimmed_new_array = array_map('trim', $new_image_array);

            $logger->info("new_image_array");
            $logger->info($trimmed_new_array);
            $logger->info("new_image_array");

            $diff_image_array = array_diff($trimmed_new_array, $trimmed_array_filter);

            $logger->info("diff_image_array");
            $logger->info($diff_image_array);
            $logger->info("diff_image_array");

            $image_merge = array_merge($diff_image_array, $trimmed_array_filter);

            $logger->info("image_merge");
            $logger->info($image_merge);
            $logger->info("image_merge");

            foreach ($new_image_array as $value) {
                $url_explode = explode("https://", $value);
                $url_filter = array_filter($url_explode);
                foreach ($url_filter as $media_value) {
                    $media_explode = explode("/", $media_value);
                    $image_media_id[] = $media_explode[3];
                }
            }

            foreach ($data_collection as $data_collection_value) {
                $media_array[] = $data_collection_value['media_id'];
                $pervious_bynder_image[] = $data_collection_value['bynder_data'];
            }

            $diff_image_new = array_diff($new_image_array, $pervious_bynder_image);

            foreach ($image_media_id as $bynder_media_id) {
                if (in_array($bynder_media_id, $media_array)) {
                    $logger->info("Update Recored");
                    $remove_for_magento = ['remove_for_magento' => '1'];
                    $where = ['sku=?' => $product_sku_key, 'media_id=?' => $bynder_media_id];
                    $this->_resource->update($table_Name, $remove_for_magento, $where);
                } else {
                    $remove_for_magento = ['remove_for_magento' => '0'];
                    $where = ['sku=?' => $product_sku_key, 'media_id=?' => $bynder_media_id];
                    $this->_resource->update($table_Name, $remove_for_magento, $where);
                }
            }
            foreach ($diff_image_new as $new_image_data_value) {
                $item_url = explode("?", $new_image_data_value);
                $image_url_explode = explode("https://", $new_image_data_value);
                $image_url_filter = array_filter($image_url_explode);
                foreach ($image_url_filter as $image_media_value) {
                    $image_media_explode = explode("/", $image_media_value);
                    if (!in_array($image_media_explode[3], $media_array)) {
                        $data_value_1 = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'bynder_data_type' => '1',
                            'media_id' => $image_media_explode[3],
                            'remove_for_magento' => '1',
                            'added_on_cron_compactview' => '1',
                            'added_date' => time()
                        ];
                        $model->setData($data_value_1);
                        $model->save();
                    } else {
                        $update_image_data_value = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'remove_for_magento' => '1',
                            'added_date' => time()
                        ];
                        $where = ['media_id=?' => $image_media_explode[3]];
                        $this->_resource->update($table_Name, $update_image_data_value, $where);
                    }
                }
            }
        } else {
            $logger->info("Empty image_value");
            $logger->info($image_value);
            $logger->info("Empty image_value");
        }
    }
    /**
     * Get Document
     *
     * @param string $img_json
     * @param string $doc_value
     * @param string $product_ids
     * @param string $storeId
     * @param string $table_Name
     * @param string $product_sku_key
     */
    public function getDocument($img_json, $doc_value, $product_ids, $storeId, $table_Name, $product_sku_key)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("GetDocument Funcation Called");
        $model = $this->_byndersycData->create();
        $check_data_magetno_side = $this->_byndersycDataCollection->create()
                ->addFieldToFilter('sku', $product_sku_key);
        $data_collection = $check_data_magetno_side->getData();
        $pervious_bynder_doc = [];
        if (!empty($doc_value)) {

            $old_doc_array = explode(" ", $doc_value);

            $new_doc_array = explode(" \n", $img_json);
             
            $old_value_array = json_decode($doc_value, true);
            $old_item_url = [];
            if (!empty($old_value_array)) {
                foreach ($old_value_array as $value) {
                    $old_item_url[] = $value['item_url'];
                
                }
            }
            $doc_detail = [];
            foreach ($new_doc_array as $doc_value) {
                $item_url = explode("?", $doc_value);
                if (!in_array($item_url[0], $old_item_url)) {
                    $doc_detail[] = [
                        "item_url" => $item_url[0],
                        "item_type" => 'DOCUMENT',
                    ];
                }
                   
            }
            $array_merge = array_merge($old_value_array, $doc_detail);
            $new_value_array = (!empty($old_value_array))
            ? json_encode($array_merge, true)
            : json_encode($doc_detail, true);
            $this->action->updateAttributes([$product_ids], ['bynder_document' => $new_value_array], $storeId);
            $trimmed_doc_array = array_map('trim', $old_doc_array);
            $trimmed_doc_array_filter = array_filter($trimmed_doc_array);

            $new_doc_array = explode(" \n", $img_json);
            $trimmed_new_doc_array = array_map('trim', $new_doc_array);

            $diff_doc_array = array_diff($trimmed_new_doc_array, $trimmed_doc_array_filter);
            $doc_array_merge = array_merge($diff_doc_array, $trimmed_doc_array_filter);
            $merge_new_doc_value = implode(" \n", $doc_array_merge);
            foreach ($new_doc_array as $new_doc_array_value) {
                $new_doc_url_explode = explode("https://", $new_doc_array_value);
                $new_doc_url_filter = array_filter($new_doc_url_explode);
                foreach ($new_doc_url_filter as $new_doc_media_value) {
                    $new_doc_media_explode = explode("/", $new_doc_media_value);
                    $new_doc_media_id[] = $new_doc_media_explode[2];
                }
            }
            //if (count($data_collection) > 0) {
            foreach ($data_collection as $data_collection_value) {
                $old_doc_media_array[] = $data_collection_value['media_id'];
                $pervious_bynder_doc[] = $data_collection_value['bynder_data'];
            }
            $diff_doc_new_value = array_diff($new_doc_array, $pervious_bynder_doc);
            $logger->info("diff_doc_new_value");
            $logger->info($diff_doc_new_value);
            $logger->info("diff_doc_new_value");

            foreach ($new_doc_media_id as $doc_bynder_media_id) {
                if (in_array($doc_bynder_media_id, $old_doc_media_array)) {
                    $logger->info("Update Recored");
                    $remove_for_magento = ['remove_for_magento' => '1'];
                    $where = ['sku=?' => $product_sku_key, 'media_id=?' => $doc_bynder_media_id];
                    $this->_resource->update($table_Name, $remove_for_magento, $where);
                } else {
                    $remove_for_magento = ['remove_for_magento' => '0'];
                    $where = ['sku=?' => $product_sku_key, 'media_id=?' => $doc_bynder_media_id];
                    $this->_resource->update($table_Name, $remove_for_magento, $where);
                }
            }
            foreach ($diff_doc_new_value as $new_doc_data_value) {
                $item_url = explode("?", $new_doc_data_value);
                $diff_doc_url_explode = explode("https://", $new_doc_data_value);
                $diff_doc_url_filter = array_filter($diff_doc_url_explode);
                foreach ($diff_doc_url_filter as $doc_media_value) {
                    $doc_media_explode = explode("/", $doc_media_value);
                    if (!in_array($doc_media_explode[2], $old_doc_media_array)) {
                        $doc_data_value = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'bynder_data_type' => '2',
                            'media_id' => $doc_media_explode[2],
                            'remove_for_magento' => '1',
                            'added_on_cron_compactview' => '1',
                            'added_date' => time()
                        ];
                        $model->setData($doc_data_value);
                        $model->save();
                    } else {
                        $update_doc_data_value = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'remove_for_magento' => '1',
                            'added_date' => time()
                        ];
                        $where = ['media_id=?' => $doc_media_explode[2]];
                        $this->_resource->update($table_Name, $update_doc_data_value, $where);
                    }
                }
            }
        } else {
            $logger->info("Empty doc_value");
            $logger->info($doc_value);
            $logger->info("Empty doc_value");
        }
    }
    /**
     * Get Video
     *
     * @param string $img_json
     * @param string $image_value
     * @param string $product_ids
     * @param string $storeId
     * @param string $table_Name
     * @param string $product_sku_key
     */
    public function getVideo($img_json, $image_value, $product_ids, $storeId, $table_Name, $product_sku_key)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/cron.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("GetVideo Funcation Called");
        $model = $this->_byndersycData->create();
        $check_data_magetno_side = $this->_byndersycDataCollection->create()
                ->addFieldToFilter('sku', $product_sku_key);
        $data_collection = $check_data_magetno_side->getData();
        $pervious_bynder_video = [];
        if (!empty($image_value)) {

            $old_video_array = explode(" ", $image_value);
            $trimmed_video_array = array_map('trim', $old_video_array);
            $trimmed_video_array_filter = array_filter($trimmed_video_array);
            $new_video_array = explode(" \n", $img_json);

            $old_value_array = json_decode($image_value, true);
            $old_item_url = [];
            if (!empty($old_value_array)) {
                foreach ($old_value_array as $value) {
                    $old_item_url[] = $value['item_url'];
                }
            }
            foreach ($new_video_array as $video_value) {
                
                $item_url = explode("?", $video_value);
                $thum_url = explode("@@", $video_value);
                
                if (!in_array($item_url[0], $old_item_url)) {
                    $video_detail[] = [
                        "item_url" => $item_url[0],
                        "image_role" => null,
                        "item_type" => 'VIDEO',
                        "thum_url" => $thum_url[1]
                    ];
                }
            }
            $array_merge = array_merge($old_value_array, $video_detail);
            $new_value_array = json_encode($array_merge, true);
            $this->action->updateAttributes([$product_ids], ['bynder_multi_img' => $new_value_array], $storeId);
           /*  IMAGE & VIDEO == 1
            IMAGE == 2
            VIDEO == 3 */
            foreach ($array_merge as $img) {
                
                $type[] = $img['item_type'];
            }
            if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
                $flag = 1;
            } elseif (in_array("IMAGE", $type)) {
                $flag = 2;
            } elseif (in_array("VIDEO", $type)) {
                $flag = 3;
            }
            $this->action->updateAttributes([$product_ids], ['bynder_isMain' => $flag], 0);
            $logger->info("trimmed_video_array_filter");
            $logger->info($trimmed_video_array_filter);
            $logger->info("trimmed_video_array_filter");
            foreach ($data_collection as $key => $data_collection_value) {
                foreach ($new_video_array as $key => $bynder_data_array) {
                    $compare = strpos($bynder_data_array, $data_collection_value['media_id']);
                    if ($compare > -1) {
                        unset($new_video_array[$key]);
                    }
                }
            }
            $trimmed_new_video_array = array_map('trim', $new_video_array);

            $logger->info("trimmed_new_video_array");
            $logger->info($trimmed_new_video_array);
            $logger->info("trimmed_new_video_array");

            $diff_video_array = array_diff($trimmed_new_video_array, $trimmed_video_array_filter);

            $logger->info("diff_video_array");
            $logger->info($diff_video_array);
            $logger->info("diff_video_array");

            $video_array_merge = array_merge($diff_video_array, $trimmed_video_array_filter);

            $logger->info("video_array_merge");
            $logger->info($video_array_merge);
            $logger->info("video_array_merge");

            $merge_new_video_value = implode(" \n", $video_array_merge);

            $logger->info("merge_new_video_value");
            $logger->info($merge_new_video_value);
            $logger->info("merge_new_video_value");
            foreach ($new_video_array as $new_video_array_value) {
                $new_video_url_explode = explode("https://", $new_video_array_value);
                $new_video_url_filter = array_filter($new_video_url_explode);
                foreach ($new_video_url_filter as $new_video_media_value) {
                    $new_video_media_explode = explode("/", $new_video_media_value);
                    $new_video_media_id[] = $new_video_media_explode[2];
                }
            }
            foreach ($data_collection as $data_collection_value) {
                $old_video_media_array[] = $data_collection_value['media_id'];
                $pervious_bynder_video[] = $data_collection_value['bynder_data'];
            }

            $diff_video_new_value = array_diff($trimmed_new_video_array, $pervious_bynder_video);

            foreach ($new_video_media_id as $video_bynder_media_id) {
                if (in_array($video_bynder_media_id, $old_video_media_array)) {
                    $logger->info("Update Recored");
                    $remove_for_magento = ['remove_for_magento' => '1'];
                    $where = ['sku=?' => $product_sku_key, 'media_id=?' => $video_bynder_media_id];
                    $this->_resource->update($table_Name, $remove_for_magento, $where);
                } else {
                    $remove_for_magento = ['remove_for_magento' => '0'];
                    $where = ['sku=?' => $product_sku_key, 'media_id=?' => $video_bynder_media_id];
                    $this->_resource->update($table_Name, $remove_for_magento, $where);
                }
            }
            foreach ($diff_video_new_value as $new_video_data_value) {
                $item_url = explode("?", $new_video_data_value);
                $diff_video_url_explode = explode("https://", $new_video_data_value);
                $diff_video_url_filter = array_filter($diff_video_url_explode);
                foreach ($diff_video_url_filter as $video_media_value) {
                    $video_media_explode = explode("/", $video_media_value);
                    if (!in_array($video_media_explode[3], $old_video_media_array)) {
                        $video_data_value = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'bynder_data_type' => '3',
                            'media_id' => $video_media_explode[3],
                            'remove_for_magento' => '1',
                            'added_on_cron_compactview' => '1',
                            'added_date' => time()
                        ];
                        $model->setData($video_data_value);
                        $model->save();
                    } else {
                        $update_video_data_value = [
                            'sku' => $product_sku_key,
                            'bynder_data' => $item_url[0],
                            'remove_for_magento' => '1',
                            'added_date' => time()
                        ];
                        $where = ['media_id=?' => $video_media_explode[3]];
                        $this->_resource->update($table_Name, $update_video_data_value, $where);
                    }
                }
            }
        } else {
            $logger->info("Empty video_value");
            $logger->info($image_value);
            $logger->info("Empty video_value");
        }
    }
}
