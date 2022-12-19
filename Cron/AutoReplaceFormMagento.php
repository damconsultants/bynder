<?php

/**
 * the dam consultants Software.
 *
 * @category  the dam consultants
 * @package   DamConsultants_Bynder
 * @author    the dam consultants
 */

namespace DamConsultants\Bynder\Cron;

use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\Product\Action;
use Magento\Store\Model\StoreManagerInterface;
use DamConsultants\Bynder\Model\BynderFactory;

class AutoReplaceFormMagento
{

    public function __construct(
        ProductRepository $productRepository,
        Attribute $attribute,
        Action $action,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
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

    public function execute()
    {
        $this->_logger->info("DamConsultants Bynder Add  Cron");

        $productCollection =  $this->attribute->getCollection();
        $productColl = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

        /*$productColl->getSelect()->limit(50);*/
        $product_sku_limit = $this->datahelper->getProductSkuLimitConfig();
        if (!empty($product_sku_limit)) {
            $productColl->getSelect()->limit($product_sku_limit);
        } else {
            $productColl->getSelect()->limit(50);
        }

        $bynder = [];
        $bynder_attribute = array('bynder_multi_img', 'bynder_videos', 'bynder_document');

        $collection = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
        if (!empty($collection)) {
            $property_id = $collection;
        } else {
            $property_id = null;
        }
        $byndeimageconfig = $this->datahelper->byndeimageconfig();
        $data_arr = array();
        $data_val_arr = array();
        $temp_arr = array();
        $productSku_array = array();
        $media_id = 0;
        foreach ($productCollection as $products) {
            $bynder[] = $products->getAttributeCode();
        }
        if (array_intersect($bynder_attribute, $bynder)) {
            foreach ($productColl as $item) {
                $productSku_array[] = $item->getSku();
            }
            $this->_logger->info("Product_SKU Start");

            if (count($productSku_array) > 0) {
                foreach ($productSku_array as $sku) {
                    $get_data = $this->datahelper->image_sync_with_properties($sku, $property_id);
                    $respon_array = json_decode($get_data, true);
                    if (isset($respon_array)) {
                        if ($respon_array['status'] == 1) {
                            $convert_array = json_decode($respon_array['data'], true);
                            if ($convert_array['status'] != 0) {
                                foreach ($convert_array['data'] as $data_value) {
                                    $image_data = $data_value['thumbnails'];
                                    $data_sku  = $data_value['property_TestMetaProperty'];
                                    if ($data_value['type'] == "image") {
                                        foreach ($image_data as $k => $v) {
                                            if ($byndeimageconfig == $k) {
                                                array_push($data_arr, $data_sku[0]);
                                                $data_p = array("sku" => $data_sku[0], "url" => $image_data["image_link"], "type" => $data_value['type']);
                                                array_push($data_val_arr, $data_p);
                                            }
                                        }
                                    } else {
                                        if ($data_value['type'] == 'video') {
                                            $video_link =  $image_data["image_link"] . '@@' . $image_data["webimage"];
                                            array_push($data_arr, $data_sku[0]);
                                            $data_p = array("sku" => $data_sku[0], "url" => $video_link, "type" => $data_value['type']);
                                            array_push($data_val_arr, $data_p);
                                        } else {
                                            $doc_name = $data_value["name"];
                                            $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
                                            $doc_link =  $image_data["image_link"] . '@@' . $doc_name_with_space;
                                            array_push($data_arr, $data_sku[0]);
                                            $data_p = array("sku" => $data_sku[0], "url" => $doc_link, "type" => $data_value['type']);
                                            array_push($data_val_arr, $data_p);
                                        }
                                    }
                                }
                            } else {
                                $this->_logger->info('No Data Found For API Side.');
                            }
                        }
                    }
                }
                $this->_logger->info("Start Data Arr");
                //$data_arr = array_unique($data_arr);
                if (count($data_arr) > 0) {
                    foreach ($data_arr as $key => $temp) {
                        $temp_arr[$temp][$data_val_arr[$key]["type"]]["url"][] = $data_val_arr[$key]["url"];
                    }
                    foreach ($temp_arr as $product_sku_key => $image_value) {

                        foreach ($image_value as $kk => $vv) {
                            $img_json = implode(" \n", $vv["url"]);
                            $item_type = $kk;
                            $result_data = $this->update_image_attribute($img_json, $product_sku_key, $item_type);
                        }
                    }
                } else {
                    $this->_logger->info('No Data Found For Data Array.');
                }
            } else {
                $this->_logger->info('No Data Found For SKU.');
            }
        }
        return $this;
    }

    public function update_image_attribute($img_json, $product_sku_key, $item_type)
    {
        $this->_logger->info("Inner Funcation Called");

        $model = $this->_byndersycData->create();
        $table_Name = $this->_resource->getTableName("bynder_cron_data");

        try {


            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $base_url = $this->storeManagerInterface->getStore()->getBaseUrl();
            $product_url_key = $_product->getUrlKey();
            $product_url = $base_url . $product_url_key . '.html';

            $product_ids = $_product->getId();
            $image_value = $_product->getBynderMultiImg();
            $video_value = $_product->getBynderVideos();
            $doc_value = $_product->getBynderDocument();

            $media_array = array();
            $updateAttributes = array();
            $pervious_bynder_image = array();

            $check_data_magetno_side = $this->_byndersycDataCollection->create()
                ->addFieldToFilter('sku', $product_sku_key)
                ->addFieldToFilter('added_on_cron_compactview', '1');

            $data_collection = $check_data_magetno_side->getData();

            if ($item_type == "image") {

                if (!empty($image_value)) {

                    $old_image_array = explode(" ", $image_value);
                    $trimmed_array = array_map('trim', $old_image_array);
                    $trimmed_array_filter = array_filter($trimmed_array);

                    $new_image_array = explode(" \n", $img_json);
                    $trimmed_new_array = array_map('trim', $new_image_array);
                    $diff_image_array = array_diff($trimmed_new_array, $trimmed_array_filter);
                    $image_merge = array_merge($diff_image_array, $trimmed_array_filter);

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
                        if (in_array($data_collection_value['bynder_data'], $image_merge)) {
                            unset($image_merge[array_search($data_collection_value['bynder_data'], $image_merge)]);
                        }
                    }

                    $new_image_value = implode(" ", $image_merge);

                    $updateAttributes['bynder_multi_img'] = $new_image_value . " \n";
                    $this->action->updateAttributes([$product_ids], $updateAttributes, $storeId);

                    $diff_image_new = array_diff($new_image_array, $pervious_bynder_image);
                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $diff_image_new);
                    }

                    foreach ($image_media_id as $bynder_media_id) {
                        if (in_array($bynder_media_id, $media_array)) {
                            $this->_logger->info("Update Recored");
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
                        $image_url_explode = explode("https://", $new_image_data_value);
                        $image_url_filter = array_filter($image_url_explode);
                        foreach ($image_url_filter as $image_media_value) {
                            $image_media_explode = explode("/", $image_media_value);
                            $data_value_1 = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_image_data_value,
                                'bynder_data_type' => '1',
                                'media_id' => $image_media_explode[3],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            );
                            $model->setData($data_value_1);
                            $model->save();
                        }
                    }
                } else {
                    $this->_logger->info("Empty image_value");
                }
            } else if ($item_type == "document") {

                $old_doc_array = explode(" ", $doc_value);
                $trimmed_doc_array = array_map('trim', $old_doc_array);
                $trimmed_doc_array_filter = array_filter($trimmed_doc_array);

                $new_doc_array = explode(" \n", $img_json);
                $trimmed_new_doc_array = array_map('trim', $new_doc_array);

                $diff_doc_array = array_diff($trimmed_new_doc_array, $trimmed_doc_array_filter);
                $doc_array_merge = array_merge($diff_doc_array, $trimmed_doc_array_filter);


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
                    if (in_array($data_collection_value['bynder_data'], $doc_array_merge)) {
                        unset($doc_array_merge[array_search($data_collection_value['bynder_data'], $doc_array_merge)]);
                        $update_doc_data_value1 = array(
                            'sku' => $product_sku_key,
                            'remove_for_magento' => '2',
                            'added_date' => time()
                        );
                        $where = ['bynder_data=?' => $data_collection_value['bynder_data']];
                        $this->_resource->update($table_Name, $update_doc_data_value1, $where);
                    }
                }

                $merge_new_doc_value = implode(" \n", $doc_array_merge);

                $updateAttributes['bynder_document'] = $merge_new_doc_value . " \n";
                $this->action->updateAttributes([$product_ids], $updateAttributes, $storeId);

                $diff_doc_new_value = array_diff($new_doc_array, $pervious_bynder_doc);

                $api_call = $this->datahelper->check_bynder();
                $api_response = json_decode($api_call, true);
                if (isset($api_response['status']) == 1) {
                    $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $diff_doc_new_value);
                }


                foreach ($new_doc_media_id as $doc_bynder_media_id) {
                    if (in_array($doc_bynder_media_id, $old_doc_media_array)) {
                        $this->_logger->info("Update Recored");
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
                    $diff_doc_url_explode = explode("https://", $new_doc_data_value);
                    $diff_doc_url_filter = array_filter($diff_doc_url_explode);
                    foreach ($diff_doc_url_filter as $doc_media_value) {
                        $doc_media_explode = explode("/", $doc_media_value);
                        if (!in_array($doc_media_explode[2], $old_doc_media_array)) {
                            $doc_data_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_doc_data_value,
                                'bynder_data_type' => '2',
                                'media_id' => $doc_media_explode[2],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            );
                            $model->setData($doc_data_value);
                            $model->save();
                        } else {
                            $update_doc_data_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_doc_data_value,
                                'remove_for_magento' => '1',
                                'added_date' => time()
                            );
                            $where = ['media_id=?' => $doc_media_explode[2]];
                            $this->_resource->update($table_Name, $update_doc_data_value, $where);
                        }
                    }
                }
                //}

                // documents section

            } else {


                $old_video_array = explode(" ", $video_value);
                $trimmed_video_array = array_map('trim', $old_video_array);
                $trimmed_video_array_filter = array_filter($trimmed_video_array);
                $new_video_array = explode(" \n", $img_json);
                $trimmed_new_video_array = array_map('trim', $new_video_array);
                $diff_video_array = array_diff($trimmed_new_video_array, $trimmed_video_array_filter);
                $video_array_merge = array_merge($diff_video_array, $trimmed_video_array_filter);

                foreach ($new_video_array as $new_video_array_value) {
                    $new_video_url_explode = explode("https://", $new_video_array_value);
                    $new_video_url_filter = array_filter($new_video_url_explode);
                    foreach ($new_video_url_filter as $new_video_media_value) {
                        $new_video_media_explode = explode("/", $new_video_media_value);
                        $new_video_media_id[] = $new_video_media_explode[2];
                    }
                }


                //if (count($data_collection) > 0) {
                foreach ($data_collection as $data_collection_value) {
                    $old_video_media_array[] = $data_collection_value['media_id'];
                    $pervious_bynder_video[] = $data_collection_value['bynder_data'];
                    if (in_array($data_collection_value['bynder_data'], $video_array_merge)) {
                        unset($video_array_merge[array_search($data_collection_value['bynder_data'], $video_array_merge)]);
                        $update_video_data_value = array(
                            'sku' => $product_sku_key,
                            'remove_for_magento' => '2',
                            'added_date' => time()
                        );
                        $where = ['bynder_data=?' => $data_collection_value['bynder_data']];
                        $this->_resource->update($table_Name, $update_video_data_value, $where);
                    }
                }

                $merge_new_video_value = implode(" \n", $video_array_merge);

                $updateAttributes['bynder_videos'] = $merge_new_video_value . " \n";
                $this->action->updateAttributes([$product_ids], $updateAttributes, $storeId);

                $diff_video_new_value = array_diff($new_video_array, $pervious_bynder_video);

                $api_call = $this->datahelper->check_bynder();
                $api_response = json_decode($api_call, true);
                if (isset($api_response['status']) == 1) {
                    $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $diff_video_new_value);
                }

                foreach ($new_video_media_id as $video_bynder_media_id) {
                    if (in_array($video_bynder_media_id, $old_video_media_array)) {
                        $this->_logger->info("Update Recored");
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
                    $diff_video_url_explode = explode("https://", $new_video_data_value);
                    $diff_video_url_filter = array_filter($diff_video_url_explode);
                    foreach ($diff_video_url_filter as $video_media_value) {
                        $video_media_explode = explode("/", $video_media_value);

                        if (!in_array($video_media_explode[2], $old_video_media_array)) {
                            $video_data_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_video_data_value,
                                'bynder_data_type' => '3',
                                'media_id' => $video_media_explode[2],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            );
                            $model->setData($video_data_value);
                            $model->save();
                        } else {
                            $update_video_data_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_video_data_value,
                                'remove_for_magento' => '1',
                                'added_date' => time()
                            );
                            $where = ['media_id=?' => $video_media_explode[2]];
                            $this->_resource->update($table_Name, $update_video_data_value, $where);
                        }
                    }
                }
                //}
                // video section

            }
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
        }
    }
}
