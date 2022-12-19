<?php

/**
 * the dam consultants Software.
 *
 * @category  the dam consultants
 * @package   DamConsultants_Bynder
 * @author    the dam consultants
 */

namespace DamConsultants\Bynder\Controller\Adminhtml\Index;

set_time_limit(0);

/**
 * Class Psku
 * @package DamConsultants\Bynder\Controller\Index
 */
class Psku extends \Magento\Backend\App\Action
{

    protected $resultPageFactory = false;

    /**
     * Edit constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Model\BynderFactory $bynder,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory $byndersycDataCollection,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $jsonFactory;
        $this->productAction = $action;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->datahelper = $DataHelper;
        $this->_byndersycData = $byndersycData;
        $this->_byndersycDataCollection = $byndersycDataCollection;
        $this->bynder = $bynder;
        $this->_productRepository = $productRepository;
        $this->product = $product;
    }

    public function execute()
    {

        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');
            return;
        }


        $property_id = null;
        $productSku = array();
        $product_sku = $this->getRequest()->getParam('product_sku');
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $result = $this->resultJsonFactory->create();
        $productSku = explode(",", $product_sku);
        $byndeimageconfig = $this->datahelper->byndeimageconfig();
        $image_array = array();
        $collection = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
        if (!empty($collection)) {
            $property_id = $collection;
        } else {
            $property_id = null;
        }

        $data_arr = array();
        $data_val_arr = array();

        foreach ($productSku as $sku) {
            $get_data = $this->datahelper->image_sync_with_properties($sku, $property_id);
            $respon_array = json_decode($get_data, true);
            if (isset($respon_array)) {
                if ($respon_array['status'] == 1) {
                    $convert_array = json_decode($respon_array['data'], true);

                    if ($convert_array['status'] == 1) {

                        foreach ($convert_array['data'] as $data_value) {

                            if ($select_attribute == $data_value['type']) {
                                $image_data = $data_value['thumbnails'];
                                $data_sku  = $data_value['property_TestMetaProperty'];
                                if ($data_value['type'] == "image") {
                                    foreach ($image_data as $k => $v) {
                                        if ($byndeimageconfig == $k) {
                                            array_push($data_arr, $data_sku[0]);
                                            $data_p = array("sku" => $data_sku[0], "url" => $image_data["image_link"]);
                                            array_push($data_val_arr, $data_p);
                                        }
                                    }
                                } else {

                                    if($select_attribute == 'video'){
                                        $video_link =  $image_data["image_link"].'@@'.$image_data["webimage"];
                                        array_push($data_arr, $data_sku[0]);
                                        $data_p = array("sku" => $data_sku[0], "url" => $video_link);
                                        array_push($data_val_arr, $data_p);
                                    }else{
                                        $doc_name = $data_value["name"];
                                        $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
                                        $doc_link =  $image_data["image_link"] . '@@' . $doc_name_with_space;
                                        array_push($data_arr, $data_sku[0]);
                                        $data_p = array("sku" => $data_sku[0], "url" => $doc_link);
                                        array_push($data_val_arr, $data_p);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (count($data_arr) > 0) {
            foreach ($data_arr as $key => $temp) {
                $temp_arr[$temp][] = $data_val_arr[$key]["url"];
            }
            foreach ($temp_arr as $product_sku_key => $image_value) {
                $img_json = implode(" \n", $image_value);
                $result_data = $this->update_image_attribute($img_json, $product_sku_key);
            }
            return $result_data;
        } else {
            $result_data = $result->setData(['status' => 0, 'message' => 'No Data Found...']);
            return $result_data;
        }
    }

    public function update_image_attribute($img_json, $product_sku_key)
    {
        $result = $this->resultJsonFactory->create();
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $model = $this->_byndersycData->create();


        try {

            $new_image_push = array();
            $new_video_push = array();
            $new_doc_push = array();
            $url_explode =  $url_video_explode =  $url_doc_explode = Null;
            $url_filter = $url_video_filter =  $url_video_filter = Null;
            $media_explode = $media_video_explode = $media_doc_explode = Null;
            $storeId = $this->storeManagerInterface->getStore()->getId();

            $_product = $this->_productRepository->get($product_sku_key);

            $product_ids = $_product->getId();
            $base_url = $this->storeManagerInterface->getStore()->getBaseUrl();
            $product_url_key = $_product->getUrlKey();
            $product_url = $base_url . $product_url_key . '.html';

            $image_value = $_product->getBynderMultiImg();
            $doc_value = $_product->getBynderDocument();
            $video_value = $_product->getBynderVideos();

            if ($select_attribute == "image") {

                if (!empty($image_value)) {

                    $old_image_array = explode(" ", $image_value);
                    $trimmed_array = array_map('trim', $old_image_array);
                    $trimmed_array_filter = array_filter($trimmed_array);
                    $new_image_array = explode(" \n", $img_json);
                    $trimmed_new_array = array_map('trim', $new_image_array);
                    $diff_image_array = array_diff($trimmed_new_array, $trimmed_array_filter);
                    $image_merge = array_merge($diff_image_array, $trimmed_array_filter);
                    $new_image_value = implode(" ", $image_merge);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $image_merge);
                    }


                    $updateAttributes['bynder_multi_img'] = $new_image_value . " \n";
                    $this->productAction->updateAttributes([$product_ids], $updateAttributes, $storeId);

                    foreach ($diff_image_array as $value) {
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
                } else {
                    $new_image_array = explode(" \n", $img_json);
                    $new_image_value = implode(" \n", $new_image_array);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $new_image_value);
                    }

                    $updateAttributes['bynder_multi_img'] = $new_image_value . " \n";
                    $this->productAction->updateAttributes([$product_ids], $updateAttributes, $storeId);

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
            } elseif ($select_attribute == "video") {


                if (!empty($video_value)) {

                    $old_video_array = explode(" ", $video_value);
                    $trimmed_video_array = array_map('trim', $old_video_array);
                    $trimmed_video_array_filter = array_filter($trimmed_video_array);

                    $new_video_array = explode(" \n", $img_json);
                    $trimmed_new_video_array = array_map('trim', $new_video_array);

                    $diff_video_array = array_diff($trimmed_new_video_array, $trimmed_video_array_filter);

                    $video_merge = array_merge($diff_video_array, $trimmed_video_array_filter);

                    $new_video_value = implode(" \n", $video_merge);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $video_merge);
                    }

                    $updateAttributes['bynder_videos'] = $new_video_value . " \n";
                    $this->productAction->updateAttributes([$product_ids], $updateAttributes, $storeId);

                    foreach ($diff_video_array as $new_data_video_value) {
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
                } else {
                    $new_video_array = explode(" \n", $img_json);
                    $new_video_value = implode(" \n", $new_video_array);

                    $updateAttributes['bynder_videos'] = $new_video_value . " \n";

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $new_video_value);
                    }


                    $this->productAction->updateAttributes([$product_ids], $updateAttributes, $storeId);

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
            } else {

                if (!empty($doc_value)) {

                    $old_doc_array = explode(" ", $doc_value);
                    $trimmed_doc_array = array_map('trim', $old_doc_array);
                    $trimmed_doc_array_filter = array_filter($trimmed_doc_array);

                    $new_doc_array = explode(" \n", $img_json);
                    $trimmed_new_doc_array = array_map('trim', $new_doc_array);

                    $diff_doc_array = array_diff($trimmed_new_doc_array, $trimmed_doc_array_filter);

                    $doc_merge = array_merge($diff_doc_array, $trimmed_doc_array_filter);

                    $new_doc_value = implode(" \n", $doc_merge);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $doc_merge);
                    }


                    $updateAttributes['bynder_document'] = $new_doc_value . " \n";
                    $this->productAction->updateAttributes([$product_ids], $updateAttributes, $storeId);

                    foreach ($diff_doc_array as $doc_data_value) {
                        $url_doc_explode = explode("https://", $doc_data_value);
                        $url_doc_filter = array_filter($url_doc_explode);
                        foreach ($url_doc_filter as $media_doc_value) {
                            $media_doc_explode = explode("/", $media_doc_value);
                            $data_doc_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $doc_value,
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
                } else {
                    $new_doc_array = explode(" \n", $img_json);

                    $new_doc_value = implode(" \n", $new_doc_array);

                    $api_call = $this->datahelper->check_bynder();
                    $api_response = json_decode($api_call, true);
                    if (isset($api_response['status']) == 1) {
                        $assets_track = $this->datahelper->get_bynder_changemetadata_assets($product_url, $new_doc_value);
                    }

                    $updateAttributes['bynder_document'] = $new_doc_value . " \n";
                    $this->productAction->updateAttributes([$product_ids], $updateAttributes, $storeId);

                    foreach ($new_doc_array as $doc_value) {
                        $url_doc_explode = explode("https://", $doc_value);
                        $url_doc_filter = array_filter($url_doc_explode);
                        foreach ($url_doc_filter as $media_doc_value) {
                            $media_doc_explode = explode("/", $media_doc_value);
                            $data_doc_value = array(
                                'sku' => $product_sku_key,
                                'bynder_data' => $doc_value,
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
            }


            return $result->setData(['status' => 1, 'message' => ' Data Sync Successfully..!']);
        } catch (\Exception $e) {
            return $result->setData(['message' => $e->getMessage()]);
        }
    }
}
