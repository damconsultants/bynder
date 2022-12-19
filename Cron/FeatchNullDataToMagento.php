<?php

/**
 * the dam consultants Software.
 *
 * @category  the dam consultants
 * @package   DamConsultants_Bynder
 * @author    the dam consultants
 */

namespace DamConsultants\Bynder\Cron;

use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Bynder\Model\BynderFactory;

class FeatchNullDataToMagento
{

    protected $logger;
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        Action $action,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        BynderFactory $bynder

    ) {

        $this->_logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_byndersycData = $byndersycData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
    }
    public function execute()
    {

        $this->_logger->info("Fetch Null Attribute Value");
        $product_collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter(
                array(
                    array('attribute' => 'bynder_multi_img', 'null' => true),
                    array('attribute' => 'bynder_videos', 'null' => true),
                    array('attribute' => 'bynder_document', 'null' => true)
                )
            )
            ->load();

        $product_sku_limit = $this->datahelper->getProductSkuLimitConfig();
        if (!empty($product_sku_limit)) {
            $product_collection->getSelect()->limit($product_sku_limit);
        } else {
            $product_collection->getSelect()->limit(50);
        }

        $collection = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
        $byndeimageconfig = $this->datahelper->byndeimageconfig();
        $data_arr = array();
        $data_val_arr = array();
        $temp_arr = array();
        $productSku_array = array();
        $respon_array = array();
        $bynder_media_id = Null;
        if (!empty($collection)) {
            $property_id = $collection;
        } else {
            $property_id = null;
        }
        foreach ($product_collection as $product) {
            $productSku_array[] = $product->getSku();
        }

        if (count($productSku_array) > 0) {
            foreach ($productSku_array as $sku) {
                $get_data = $this->datahelper->image_sync_with_properties($sku, $property_id);
                $respon_array = json_decode($get_data, true);
                if (isset($respon_array)) {
                    if ($respon_array['status'] == 1) {
                        $convert_array = json_decode($respon_array['data'], true);
                        $this->_logger->info($convert_array['status']);
                        if ($convert_array['status'] != 0) {
                            foreach ($convert_array['data'] as $data_value) {

                                $bynder_media_id = $data_value['id'];
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
                            $this->_logger->info('No Data Found For API Side. Fetch Null Data');
                        }
                    }
                }
            }


            $media_ids_list = array();
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

        return $this;
    }

    public function update_image_attribute($img_json, $product_sku_key, $item_type)
    {
        $this->_logger->info("damConsultants_bynder_fetach_sku_null_for_magento_test");



        $model = $this->_byndersycData->create();
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

            $updateAttributes = array();
            if ($item_type == "image") {

                if (empty($image_value)) {
                    $new_image_array = explode(" \n", $img_json);
                    $trimmed_new_array = array_map('trim', $new_image_array);

                    $new_image_value = implode(" ", $trimmed_new_array);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $new_image_array);
                    }

                    $updateAttributes['bynder_multi_img'] = $new_image_value . " \n";

                    $this->action->updateAttributes([$product_ids], $updateAttributes, $storeId);

                    foreach ($new_image_array as $value) {
                        $url_explode = explode("https://", $value);
                        $url_filter = array_filter($url_explode);
                        foreach ($url_filter as $media_value) {
                            $media_explode = explode("/", $media_value);
                            $data_value_1 = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $value,
                                'bynder_data_type' => '1',
                                'media_id' => $media_explode[3],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            );
                            $model->setData($data_value_1);
                            $model->save();
                        }
                    }
                }
            } else if ($item_type == "document") {

                if (empty($doc_value)) {
                    $new_doc_array = explode(" \n", $img_json);
                    $trimmed_new_doc_array = array_map('trim', $new_doc_array);

                    $new_doc_value = implode(" \n", $trimmed_new_doc_array);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $new_doc_array);
                    }


                    $updateAttributes['bynder_document'] = $new_doc_value . " \n";
                    $this->action->updateAttributes([$product_ids], $updateAttributes, $storeId);


                    foreach ($new_doc_array as $new_doc_data_value) {
                        $url_doc_explode = explode("https://", $new_doc_data_value);
                        $url_doc_filter = array_filter($url_doc_explode);
                        foreach ($url_doc_filter as $media_doc_value) {
                            $media_doc_explode = explode("/", $media_doc_value);
                            $data_doc_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_doc_data_value,
                                'bynder_data_type' => '2',
                                'media_id' => $media_doc_explode[2],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            );

                            $model->setData($data_doc_value);
                            $model->save();
                        }
                    }
                }
                // documents section

            } else {

                if (empty($video_value)) {
                    $new_video_array = explode(" \n", $img_json);
                    $trimmed_new_video_array = array_map('trim', $new_video_array);
                    $new_video_value = implode(" \n", $trimmed_new_video_array);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $new_video_array);
                    }

                    $updateAttributes['bynder_videos'] = $new_video_value . " \n";
                    $this->action->updateAttributes([$product_ids], $updateAttributes, $storeId);


                    foreach ($new_video_array as $new_data_video_value) {
                        $url_video_explode = explode("https://", $new_data_video_value);
                        $url_video_filter = array_filter($url_video_explode);
                        foreach ($url_video_filter as $media_video_value) {
                            $media_video_explode = explode("/", $media_video_value);
                            $data_video_data = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $new_data_video_value,
                                'bynder_data_type' => '3',
                                'media_id' => $media_video_explode[2],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            );
                            $model->setData($data_video_data);
                            $model->save();
                        }
                    }
                }
                // video section

            }
        } catch (\Exception $e) {
            $this->_logger->info($e->getMessage());
        }
    }
}
