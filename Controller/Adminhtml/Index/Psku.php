<?php

namespace DamConsultants\Bynder\Controller\Adminhtml\Index;

use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory;

class Psku extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;

    /**
     * Product Sku.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Bynder\Model\BynderFactory $bynder
     * @param \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \DamConsultants\Bynder\Helper\Data $DataHelper
     * @param BynderSycDataCollectionFactory $byndersycDataCollection
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Model\BynderFactory $bynder,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        BynderSycDataCollectionFactory $byndersycDataCollection,
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
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {

        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');
            return;
        }
        $property_id = null;
        $productSku = [];
        $product_sku = $this->getRequest()->getParam('product_sku');
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $result = $this->resultJsonFactory->create();
        $productSku = explode(",", $product_sku);
        
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        if (!empty($collection)) {
            $collections = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
            $property_id = $collections;
        } else {
            $property_id = null;
        }
        foreach ($productSku as $sku) {
            $get_data = $this->datahelper->getImageSyncWithProperties($sku, $property_id);
            
            $respon_array = json_decode($get_data, true);
            if ($respon_array['status'] == 1) {
                    $convert_array = json_decode($respon_array['data'], true);
                    $this->getDataItem($select_attribute, $convert_array);
            } else {
                $result_data = $result->setData(
                    ['status' => 0, 'message' => 'Please Select The Metaproperty First.....']
                );
                return $result_data;
            }
        }
    }
    /**
     * Get Data Item
     *
     * @param string $select_attribute
     * @param array $convert_array
     */
    public function getDataItem($select_attribute, $convert_array)
    {
        $data_arr = [];
        $data_val_arr = [];
        if ($convert_array['status'] == 1) {
            foreach ($convert_array['data'] as $data_value) {
                if ($select_attribute == $data_value['type']) {
                    $image_data = $data_value['thumbnails'];
                    $data_sku  = $data_value['property_TestMetaProperty'];
                    if ($data_value['type'] == "image") {
                            array_push($data_arr, $data_sku[0]);
                            $data_p = ["sku" => $data_sku[0], "url" => $image_data["image_link"]];
                            array_push($data_val_arr, $data_p);
                    } else {
                        if ($select_attribute == 'video') {
                            $video_link =  $image_data["image_link"].'@@'.$image_data["webimage"];
                            array_push($data_arr, $data_sku[0]);
                            $data_p = ["sku" => $data_sku[0], "url" => $video_link];
                            array_push($data_val_arr, $data_p);

                        } else {
                            $doc_name = $data_value["name"];
                            $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
                            $doc_link =  $image_data["image_link"] . '@@' . $doc_name_with_space;
                            array_push($data_arr, $data_sku[0]);
                            $data_p = ["sku" => $data_sku[0], "url" => $doc_link];
                            array_push($data_val_arr, $data_p);
                        }
                    
                    }
                }
            }
        }
        $this->getProcessItem($data_arr, $data_val_arr);
    }
    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     */
    public function getProcessItem($data_arr, $data_val_arr)
    {
        $result = $this->resultJsonFactory->create();
        if (count($data_arr) > 0) {
            foreach ($data_arr as $key => $temp) {
                $temp_arr[$temp][] = $data_val_arr[$key]["url"];
            }
            foreach ($temp_arr as $product_sku_key => $image_value) {
                $img_json = implode(" \n", $image_value);
                $this->getUpdateImage($img_json, $product_sku_key);
            }
        } else {
            $result_data = $result->setData(['status' => 0, 'message' => 'No Data Found...']);
            return $result_data;
        }
    }
    /**
     * Upate Item
     *
     * @return $this
     * @param string $img_json
     * @param string $product_sku_key
     */
    public function getUpdateImage($img_json, $product_sku_key)
    {
        $result = $this->resultJsonFactory->create();
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $model = $this->_byndersycData->create();

        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $byndeimageconfig = $this->datahelper->byndeimageconfig();
            $img_roles = explode(",", $byndeimageconfig);
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            $image_value = $_product->getBynderMultiImg();
            $doc_value = $_product->getBynderDocument();
            if ($select_attribute == "image") {
                if (!empty($image_value)) {
                    $new_image_array = explode(" \n", $img_json);
                    $all_item_url = [];
                    $item_old_value = json_decode($image_value, true);
                    if (count($item_old_value) > 0) {
                        foreach ($item_old_value as $img) {
                            $all_item_url[] = $img['item_url'];
                        }
                    }
                    foreach ($new_image_array as $image_value) {
                        $image_detail = [];
                        $item_url = explode("?", $image_value);
                        $media_image_explode = explode("/", $item_url[0]);
                        if (!in_array($item_url[0], $all_item_url)) {
                            $image_detail[] = [
                                "item_url" => $item_url[0],
                                "image_role" => $img_roles,
                                "item_type" => 'IMAGE',
                                "thum_url" => $item_url[0]
                            ];
                            $data_image_data = [
                                'sku' => $product_sku_key,
                                'bynder_data' => $item_url[0],
                                'bynder_data_type' => '1',
                                'media_id' => $media_image_explode[5],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            ];
                            $model->setData($data_image_data);
                            $model->save();
                        }
                    }
                    $array_merge = array_merge($item_old_value, $image_detail);
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
                    $new_value_array =json_encode($array_merge, true);
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_multi_img' => $new_value_array],
                        $storeId
                    );
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_isMain' => $flag],
                        $storeId
                    );
                    
                } else {
                    $new_image_array = explode(" \n", $img_json);
                    $image_detail = [];
                    foreach ($new_image_array as $image_value) {
                        $item_url = explode("?", $image_value);
                        $media_image_explode = explode("/", $item_url[0]);
                        
                            $image_detail[] = [
                                "item_url" => $item_url[0],
                                "image_role" => $img_roles,
                                "item_type" => 'IMAGE',
                                "thum_url" => $item_url[0]
                            ];
                            $data_image_data = [
                                'sku' => $product_sku_key,
                                'bynder_data' => $item_url[0],
                                'bynder_data_type' => '1',
                                'media_id' => $media_image_explode[5],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '1',
                                'added_date' => time()
                            ];
                            $model->setData($data_image_data);
                            $model->save();
                    }
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
                    $new_value_array = json_encode($image_detail, true);
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_multi_img' => $new_value_array],
                        $storeId
                    );
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_isMain' => $flag],
                        $storeId
                    );
                    
                }
            } elseif ($select_attribute == "video") {
                if (!empty($image_value)) {
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
                        $media_video_explode = explode("/", $item_url[0]);
                        if (!in_array($item_url[0], $old_item_url)) {
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
                    }
                    if (!empty($old_value_array)) {
                        $array_merge = array_merge($old_value_array, $video_detail);
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
                    }
                    $new_value_array = json_encode($array_merge, true);
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_multi_img' => $new_value_array],
                        $storeId
                    );
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_isMain' => $flag],
                        $storeId
                    );
                } else {
                    $new_video_array = explode(" \n", $img_json);
                    $video_detail = [];
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
                    $new_value_array =json_encode($video_detail, true);
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_multi_img' => $new_value_array],
                        $storeId
                    );
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_isMain' => $flag],
                        $storeId
                    );
                }
            } else {

                if (empty($doc_value)) {
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
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        ['bynder_document' => $new_value_array],
                        $storeId
                    );
                }
            }
            return $result->setData(['status' => 1, 'message' => ' Data Sync Successfully..!']);
        } catch (\Exception $e) {
            return $result->setData(['message' => $e->getMessage()]);
        }
    }
}
