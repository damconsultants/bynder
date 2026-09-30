<?php

namespace DamConsultants\Bynder\Cron;

use Exception;
use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Bynder\Model\BynderFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;

class FeatchNullDataToMagento
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var $bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var $bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $action
     */
    protected $action;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $configWriter
     */
    protected $configWriter;
    /**
     * @var $resouce
     */
    protected $resouce;
    /**
     * @var $collectionFactory
     */
    protected $collectionFactory;
    /**
     * @var $bynder
     */
    protected $bynder;
    /**
     * @var $_resource
     */
    protected $_resource;

    /**
     * Featch Null Data To Magento
     * @param LoggerInterface $logger
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Bynder\Helper\Data $DataHelper
     * @param \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData
     * @param \DamConsultants\Bynder\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param Action $action
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param BynderFactory $bynder
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        \DamConsultants\Bynder\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        BynderFactory $bynder,
        \Magento\Framework\App\ResourceConnection $resource
    ) {

        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_byndersycData = $byndersycData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
        $this->_resource = $resource;
    }

    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        $enable = $this->datahelper->getFetchCronEnable();
        if (!$enable) {
            return false;
        }
        $product_collection = $this->collectionFactory->create();
        $product_sku_limit = (int)$this->datahelper->getFetchProductSkuLimitConfig();
        if (!empty($product_sku_limit)) {
            $product_collection->getSelect()->limit($product_sku_limit);
        } else {
            $product_collection->getSelect()->limit(50);
        }
        $product_collection->addAttributeToSelect(['sku', 'bynder_multi_img', 'bynder_document', 'bynder_cron_sync'])
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'null' => true],
                    ['attribute' => 'bynder_document', 'null' => true]
                ]
            )
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_cron_sync', 'null' => true]
                ]
            )
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->load();
        $property_id = null;
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);
        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];
        $productSku_array = [];
        foreach ($product_collection->getData() as $product) {
            $productSku_array[] = $product['sku'];
        }
        if (count($productSku_array) > 0) {
            foreach ($productSku_array as $sku) {
                if ($sku != "") {
                    $this->processSku($sku, $property_id, $collection_value, $collection_slug_val);
                }
            }
        }
        return true;
    }

    /**
     * Fetch Bynder data for one SKU and sync it, logging and resetting on failure
     *
     * @param string $sku
     * @param mixed $property_id
     * @param array $collection_value
     * @param array $collection_slug_val
     * @return void
     */
    private function processSku($sku, $property_id, $collection_value, $collection_slug_val)
    {
        $bd_sku = trim(preg_replace('/[^A-Za-z0-9-]/', '_', $sku));
        $get_data = $this->datahelper->getImageSyncWithProperties($bd_sku, $property_id, $collection_value);
        if (empty($get_data) || !$this->getIsJSON($get_data)) {
            $this->logFailureAndReset($sku, "Something problem in DAM side please contact to developer.");
            return;
        }

        $respon_array = json_decode($get_data, true);
        if ($respon_array['status'] != 1) {
            $this->logFailureAndReset($sku, 'Please Select The Metaproperty First.....');
            return;
        }

        $convert_array = json_decode($respon_array['data'], true);
        if ($convert_array['status'] != 1) {
            $this->logFailureAndReset($sku, $convert_array['data']);
            return;
        }

        try {
            $this->getDataItem($convert_array, $collection_slug_val, $sku);
        } catch (Exception $e) {
            $this->logFailureAndReset($sku, $e->getMessage());
        }
    }

    /**
     * Write a failure row to the sync log and clear the product's Bynder attributes
     *
     * @param string $sku
     * @param mixed $message
     * @return void
     */
    private function logFailureAndReset($sku, $message)
    {
        $insert_data = [
            "sku" => $sku,
            "message" => $message,
            "data_type" => "",
            'media_id' => "",
            'remove_for_magento' => '',
            'added_on_cron_compactview' => '',
            "lable" => "0"
        ];
        $this->getInsertDataTable($insert_data);
        $this->resetProductAttributes($sku);
    }

    /**
     * Get Meta Properties Collection
     *
     * @param array $collection
     * @return array $response_array
     */
    public function getMetaPropertiesCollection($collection)
    {
        $collection_data_value = [];
        $collection_data_slug_val = [];
        if (count($collection) >= 1) {
            foreach ($collection as $key => $collection_value) {
                $collection_data_value[] = [
                    'id' => $collection_value['id'],
                    'property_name' => $collection_value['property_name'],
                    'property_id' => $collection_value['property_id'],
                    'magento_attribute' => $collection_value['magento_attribute'],
                    'attribute_id' => $collection_value['attribute_id'],
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                    'system_slug' => $collection_value['system_slug'],
                    'system_name' => $collection_value['system_name']
                ];
                $collection_data_slug_val[$collection_value['system_slug']] = [
                    'bynder_property_slug' => $collection_value['system_slug'],
                ];
            }
        }
        $response_array = [
            "collection_data_value" => $collection_data_value,
            "collection_data_slug_val" => $collection_data_slug_val
        ];
        return $response_array;
    }

    /**
     * Is int
     *
     * @return $this
     */
    public function getMyStoreId()
    {
        $storeId = $this->storeManagerInterface->getStore()->getId();
        return $storeId;
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return $this
     */
    public function getIsJSON($string)
    {
        return ((json_decode($string)) === null) ? false : true;
    }

    /**
     * Normalize image role values returned by Bynder.
     *
     * @param mixed $role
     * @return string
     */
    private function normalizeMagentoRole($role): string
    {
        if (is_array($role)) {
            return '';
        }

        $role = trim((string)$role);
        if ($role === '') {
            return '';
        }

        if (strtolower($role) === 'thumb') {
            return 'Thumbnail';
        }

        if ((string)(int)$role === $role) {
            return (int)$role === 0 ? 'Base' : '';
        }

        return $role;
    }

    /**
     * Prepare image role, alt text, and media id values from the API response.
     *
     * @param array $imageData
     * @param string|int $bynderMediaId
     * @return array
     */
    private function prepareImageMetadata(array $imageData, $bynderMediaId): array
    {
        $roles = [];
        $altTexts = [];
        $mediaIds = [];

        $roleOptions = $imageData['magento_role_options'] ?? [];
        $roleDetails = $imageData['magento_role_options_details'] ?? [];

        if (!is_array($roleOptions)) {
            $roleOptions = [];
        }

        if (!is_array($roleDetails)) {
            $roleDetails = [];
        }

        if (count($roleOptions) > 0) {
            foreach ($roleOptions as $index => $roleOption) {
                $normalizedRole = $this->normalizeMagentoRole($roleOption);
                if ($normalizedRole === '' && isset($roleDetails[$index]['label'])) {
                    $normalizedRole = trim((string)$roleDetails[$index]['label']);
                }
                if ($normalizedRole === '' && isset($roleDetails[$index]['slug'])) {
                    $normalizedRole = trim((string)$roleDetails[$index]['slug']);
                }
                $roles[] = $normalizedRole !== '' ? $normalizedRole : '###';

                $altTextValue = $imageData['img_alt_text'] ?? '';
                if (is_array($altTextValue)) {
                    $altTextValue = implode(' ', $altTextValue);
                }
                $altTextValue = trim((string)$altTextValue);
                $altTexts[] = $altTextValue !== '' ? $altTextValue . "\n" : "###\n";
                $mediaIds[] = $bynderMediaId;
            }
        } elseif (count($roleDetails) > 0) {
            foreach ($roleDetails as $detail) {
                $normalizedRole = trim((string)($detail['label'] ?? ''));
                if ($normalizedRole === '') {
                    $normalizedRole = trim((string)($detail['slug'] ?? ''));
                }
                $roles[] = $normalizedRole !== '' ? $normalizedRole : '###';

                $altTextValue = $imageData['img_alt_text'] ?? '';
                if (is_array($altTextValue)) {
                    $altTextValue = implode(' ', $altTextValue);
                }
                $altTextValue = trim((string)$altTextValue);
                $altTexts[] = $altTextValue !== '' ? $altTextValue . "\n" : "###\n";
                $mediaIds[] = $bynderMediaId;
            }
        }

        if (empty($roles)) {
            $roles[] = '###';
            $altTexts[] = "###\n";
            $mediaIds[] = $bynderMediaId;
        }

        return [
            'roles' => $roles,
            'alt_text' => $altTexts,
            'media_ids' => $mediaIds,
        ];
    }

    /**
     * Is Json
     *
     * @param array $insert_data
     * @return $this
     */
    public function getInsertDataTable($insert_data)
    {
        $model = $this->_byndersycData->create();
        $data_image_data = [
            'sku' => $insert_data['sku'],
            'bynder_data' =>$insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            'media_id' => $insert_data['media_id'],
            'remove_for_magento' => $insert_data['remove_for_magento'],
            'added_on_cron_compactview' => $insert_data['added_on_cron_compactview'],
            'lable' => $insert_data['lable']
        ];

        $model->setData($data_image_data);
        $model->save();
    }

    /**
     * Is Json
     *
     * @param string $sku
     * @param string $m_id
     * @param string $product_ids
     * @param string $storeId
     * @return $this
     */
    public function getInsertMedaiDataTable($sku, $m_id, $product_ids, $storeId)
    {
        $model = $this->bynderMediaTable->create();
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        $table_m_id = [];
        if (!empty($modelcollection)) {
            foreach ($modelcollection as $mdata) {
                $table_m_id[] = $mdata['media_id'];
            }
        }
        $media_diff = array_diff($m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $data_image_data = [
                'sku' => $sku,
                'media_id' => trim($new_data),
                'status' => "1",
            ];
            $model->setData($data_image_data);
            $model->save();
        }
        $updated_values = [
            'bynder_delete_cron' => 1
        ];
        $this->action->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * Is Json
     *
     * @param array $sku
     * @param string $media_id
     * @return $this
     */
    public function getDeleteMedaiDataTable($sku, $media_id)
    {
        $model = $this->bynderMediaTableCollectionFactory->create()->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();
            }
        }
    }

    /**
     * Get Data Item
     *
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     */
    public function getDataItem($convert_array, $collection_data_slug_val, $current_sku)
    {
        $data_arr = [];
        $data_val_arr = [];
        if ($convert_array['status'] == 1) {
            foreach ($convert_array['data'] as $data_value) {
                $entry = $this->buildAssetItem($data_value, $collection_data_slug_val, $current_sku);
                if ($entry === null) {
                    continue;
                }
                $data_arr[] = $current_sku;
                $data_val_arr[] = $entry;
            }
        }
        if (count($data_arr) > 0) {
            $this->getProcessItem($data_arr, $data_val_arr);
        }
    }

    /**
     * Build one asset entry (image, video or document); null for a document without a public URL
     *
     * @param array $data_value
     * @param array $collection_data_slug_val
     * @param string $current_sku
     * @return array|null
     */
    private function buildAssetItem($data_value, $collection_data_slug_val, $current_sku)
    {
        $bynder_media_id = $data_value['id'];
        $image_data = $data_value['thumbnails'] ?? [];
        $sku_slug_name = "property_" . $collection_data_slug_val['sku']['bynder_property_slug'];
        $new_image_role = [];

        $imageMetadata = $this->prepareImageMetadata($image_data, $bynder_media_id);
        $new_magento_role_list = $imageMetadata['roles'];

        // image order metadata is ignored in this cron flow

        $new_bynder_mediaid_text = array_unique($imageMetadata['media_ids']);
        $new_bynder_alt_text = array_unique($imageMetadata['alt_text']);

        if ($data_value['type'] == "image") {
            $image_link = $image_data['Base'] ?? "";
            return [
                "sku" => $current_sku,
                "url" => [$image_link . "\n"],
                'magento_image_role' => $new_magento_role_list,
                'image_alt_text' => $new_bynder_alt_text,
                'bynder_media_id_new' => $new_bynder_mediaid_text,
                "type" => "image"
            ];
        }

        if ($data_value['type'] == 'video') {
            $video_link = ($data_value["videoPreviewURLs"][0] ?? "")
                . '@@' . ($image_data["webimage"] ?? "");
            return [
                "sku" => $current_sku,
                "url" => [$video_link . "\n"],
                'magento_image_role' => $new_image_role,
                'image_alt_text' => $new_bynder_alt_text,
                'bynder_media_id_new' => $new_bynder_mediaid_text,
                "type" => "video"
            ];
        }

        $doc_link = $this->getDocumentLink($data_value);
        if (empty($doc_link)) {
            return null;
        }
        return [
            "sku" => $current_sku,
            "url" => [$doc_link],
            'magento_image_role' => $new_image_role,
            'image_alt_text' => $new_bynder_alt_text,
            'bynder_media_id_new' => $new_bynder_mediaid_text,
            "type" => "document"
        ];
    }

    /**
     * Get the first public derivative URL of a document, suffixed with its name
     *
     * @param array $data_value
     * @return string
     */
    private function getDocumentLink($data_value)
    {
        $doc_name = $data_value["name"];
        $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
        $doc_link = "";
        if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
            foreach ($data_value['derivatives'] as $derivative) {
                if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                    $doc_link = $derivative['public_url'] . '@@' . $doc_name . "\n";
                    break; // take the first available public_url
                }
            }
        }
        return $doc_link;
    }

    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     */
    public function getProcessItem($data_arr, $data_val_arr)
    {
        $image_value_details_role = [];
        $temp_arr = [];
        $types = [];
        foreach ($data_arr as $key => $skus) {
            $temp_arr[$skus][] =  implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$skus][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$skus][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$skus][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
            $types[] = $data_val_arr[$key]['type'];
        }
        $types = array_unique($types);
        foreach ($temp_arr as $product_sku_key => $image_value) {
            $img_json = implode("", $image_value);
            $mg_role = $image_value_details_role[$product_sku_key];
            $image_alt_text_value = implode("", $image_alt_text[$product_sku_key]);
            $this->getUpdateImage(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $byn_md_id_new,
                $types,
            );
        }
    }

    /**
     * Upate Item
     *
     * @return $this
     * @param string $img_json
     * @param string $product_sku_key
     * @param string $mg_img_role_option
     * @param string $img_alt_text
     * @param string $bynder_media_ids
     * @param array $types
     */
    public function getUpdateImage(
        $img_json,
        $product_sku_key,
        $mg_img_role_option,
        $img_alt_text,
        $bynder_media_ids,
        $types
    ) {
        $model = $this->_byndersycData->create();
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            $image_value = $_product->getBynderMultiImg();
            $doc_value = $_product->getBynderDocument();
            $bynder_media_id = $bynder_media_ids[$product_sku_key];
            if ((in_array("image", $types) || in_array("video", $types)) && empty($image_value)) {
                $this->syncImagesAndVideos(
                    $img_json,
                    $img_alt_text,
                    $mg_img_role_option,
                    $bynder_media_id,
                    $product_sku_key,
                    $product_ids,
                    $storeId
                );
            }
            if (in_array("document", $types) && empty($doc_value)) {
                $this->syncDocuments(
                    $img_json,
                    $bynder_media_id,
                    $product_sku_key,
                    $product_ids,
                    $storeId
                );
            }
        } catch (Exception $e) {
            $insert_data = [
                "sku" => $product_sku_key,
                "message" => $e->getMessage(),
                "data_type" => "",
                'media_id' => "",
                'remove_for_magento' => '',
                'added_on_cron_compactview' => '',
                "lable" => "0"
            ];
            $this->getInsertDataTable($insert_data);
        }
    }

    /**
     * Save images and videos to bynder_multi_img for a product that has none yet
     *
     * @param string $img_json
     * @param string $img_alt_text
     * @param array $mg_img_role_option
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function syncImagesAndVideos(
        $img_json,
        $img_alt_text,
        $mg_img_role_option,
        $bynder_media_id,
        $product_sku_key,
        $product_ids,
        $storeId
    ) {
        $image_detail = [];
        $video_detail = [];
        $new_image_array = explode("\n", $img_json);
        $new_alttext_array = explode("\n", $img_alt_text);
        $new_magento_role_option_array = $mg_img_role_option;
        foreach ($new_image_array as $vv => $image_line) {
            if (trim($image_line) == "" || $image_line == "no image") {
                continue;
            }
            $img_altText_val = $this->getAltTextValue($new_alttext_array, $vv);
            $curt_img_role = $this->getCurrentImageRole($new_magento_role_option_array, $vv);
            $find_video = strpos($image_line, "@@");
            $find_doc = strpos($image_line, "??");
            if (!$find_video && !$find_doc) {
                $image_detail[] = [
                    "item_url" => $image_line,
                    "alt_text" => $img_altText_val,
                    "image_role" => $curt_img_role,
                    "item_type" => 'IMAGE',
                    "thum_url" => $image_line,
                    "bynder_md_id" => $bynder_media_id[$vv],
                    "is_import" => 0
                ];
                $data_image_data = [
                    'sku' => $product_sku_key,
                    'message' => $image_line,
                    'data_type' => '1',
                    'media_id' => $bynder_media_id[$vv],
                    'remove_for_magento' => '1',
                    'added_on_cron_compactview' => '1',
                    'lable' => 1
                ];
                $this->getInsertDataTable($data_image_data);
            } elseif ($find_video) {
                $item_url = explode("@@", $image_line);
                $thum_url = explode("@@", $image_line);
                $media_video_explode = explode("/", $item_url[0]);

                $video_detail[] = [
                    "item_url" => $item_url[0],
                    "image_role" => null,
                    "item_type" => 'VIDEO',
                    "thum_url" => $thum_url[1],
                    "bynder_md_id" => $bynder_media_id[$vv]
                ];
                $data_video_data = [
                    'sku' => $product_sku_key,
                    'message' => $item_url[0],
                    'data_type' => '3',
                    'media_id' => $media_video_explode[$vv],
                    'remove_for_magento' => '1',
                    'added_on_cron_compactview' => '1',
                    'lable' => 1
                ];
                $this->getInsertDataTable($data_video_data);
            }
            $image_detail = $this->stripRolesFromEarlierImages($image_detail, $new_magento_role_option_array, $vv);
        }
        $image_detail = $this->applyDefaultBaseRoles($image_detail);
        $image_detail = $this->removePlaceholderRoles($image_detail);
        $marge = array_merge($image_detail, $video_detail);
        $m_id = [];
        foreach ($marge as $img) {
            $type[] = $img['item_type'];
            $m_id[] = $img['bynder_md_id'];
            $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
        }
        $this->getInsertMedaiDataTable($product_sku_key, $m_id, $product_ids, $storeId);

        $flag = $this->getMediaFlag($type);
        $new_value_array = json_encode($marge, true);
        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'bynder_cron_sync' => 1,
            'use_bynder_cdn' => 1
        ];
        $this->action->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * Save documents to bynder_document for a product that has none yet
     *
     * @param string $img_json
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function syncDocuments($img_json, $bynder_media_id, $product_sku_key, $product_ids, $storeId)
    {
        $new_doc_array = explode("\n", $img_json);
        $doc_detail = [];
        foreach ($new_doc_array as $vv => $doc_line) {
            $find_doc = strpos($doc_line, "??");
            if (!$find_doc) {
                continue;
            }
            $doc_parts = explode("??", $doc_line);
            if (!isset($doc_parts[1]) || !isset($bynder_media_id[$vv])) {
                continue;
            }
            $doc_detail[] = [
                "item_url" => $doc_parts[0],
                "item_type" => 'DOCUMENT',
                "doc_name" => $doc_parts[1],
                "bynder_md_id" => $bynder_media_id[$vv]
            ];
            $data_doc_value = [
                'sku' => $product_sku_key,
                'message' => $doc_parts[0],
                'data_type' => '2',
                'media_id' => $bynder_media_id[$vv],
                'remove_for_magento' => '1',
                'added_on_cron_compactview' => '1',
                'lable' => 1
            ];
            $this->getInsertDataTable($data_doc_value);
        }
        $new_value_array = json_encode($doc_detail, true);
        $this->action->updateAttributes(
            [$product_ids],
            ['bynder_document' => $new_value_array, 'bynder_cron_sync' => 1],
            $storeId
        );
    }

    /**
     * Alt text for a line, or "" when missing / placeholder / blank
     *
     * @param array $new_alttext_array
     * @param int|string $vv
     * @return string
     */
    private function getAltTextValue($new_alttext_array, $vv)
    {
        $img_altText_val = "";
        if (isset($new_alttext_array[$vv])
            && $new_alttext_array[$vv] != "###"
            && strlen(trim($new_alttext_array[$vv])) > 0
        ) {
            $img_altText_val = $new_alttext_array[$vv];
        }
        return $img_altText_val;
    }

    /**
     * Role(s) for a line, or [] when it is the placeholder
     *
     * @param array $new_magento_role_option_array
     * @param int|string $vv
     * @return mixed
     */
    private function getCurrentImageRole($new_magento_role_option_array, $vv)
    {
        $curt_img_role = [];
        if ($new_magento_role_option_array[$vv] != "###") {
            $curt_img_role = $new_magento_role_option_array[$vv];
        }
        return $curt_img_role;
    }

    /**
     * Remove the roles at $roleKey from every IMAGE entry except the last one
     *
     * @param array $details
     * @param array $roleOptions
     * @param mixed $roleKey
     * @return array
     */
    private function stripRolesFromEarlierImages($details, $roleOptions, $roleKey)
    {
        $total = count($details);
        if ($total <= 1) {
            return $details;
        }
        foreach ($details as $nn => $n_img) {
            if ($n_img['item_type'] != "IMAGE" || $nn == ($total - 1)) {
                continue;
            }
            if ($roleOptions[$roleKey] == "###") {
                continue;
            }
            $new_mg_role_array = (array)$roleOptions[$roleKey];
            if (count($n_img["image_role"]) > 0 && count($new_mg_role_array) > 0) {
                $details[$nn]["image_role"] = array_diff($n_img["image_role"], $new_mg_role_array);
            }
        }
        return $details;
    }

    /**
     * When no image has the Base role, give placeholder entries the default roles
     *
     * @param array $image_detail
     * @return array
     */
    private function applyDefaultBaseRoles($image_detail)
    {
        $replacementRoles = ["Base", "Small", "Swatch", "Thumbnail"];
        $flags = true;
        foreach ($image_detail as &$item) {
            if (in_array('Base', $item['image_role'])) {
                $flags = false;
            }
        }
        foreach ($image_detail as &$item) {
            if ($flags && isset($item['image_role']) && is_array($item['image_role'])) {
                $containsPlaceholder = in_array("###\n", $item['image_role']);
                $hasAllReplacementRoles = empty(array_diff($replacementRoles, $item['image_role']));
                if ($hasAllReplacementRoles) {
                    break;
                }
                if ($containsPlaceholder && !$hasAllReplacementRoles) {
                    $item['image_role'] = $replacementRoles;
                }
            }
        }
        unset($item);
        return $image_detail;
    }

    /**
     * Clean out "###" from image_role
     *
     * @param array $image_detail
     * @return array
     */
    private function removePlaceholderRoles($image_detail)
    {
        foreach ($image_detail as &$items) {
            if (isset($items['image_role']) && is_array($items['image_role'])) {
                $items['image_role'] = array_values(array_filter(
                    $items['image_role'],
                    fn($role) => trim($role) !== '###'
                ));
            }
        }
        unset($items);
        return $image_detail;
    }

    /**
     * Media flag: 1 = images and videos, 2 = images only, 3 = videos only, 0 = none
     *
     * @param array $type
     * @return int
     */
    private function getMediaFlag($type)
    {
        $flag = 0;
        if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
            $flag = 1;
        } elseif (in_array("IMAGE", $type)) {
            $flag = 2;
        } elseif (in_array("VIDEO", $type)) {
            $flag = 3;
        }
        return $flag;
    }

    /**
     * Reset the empty Bynder attributes when a sync fails.
     *
     * @param string $sku
     */
    public function resetProductAttributes($sku)
    {
        try {
            $storeId = $this->getMyStoreId();
            $_product = $this->_productRepository->get($sku);
            $product_ids = $_product->getId();

            $updated_values = [
                'bynder_multi_img' => null,
                'bynder_isMain' => null,
                'bynder_document' => null
            ];

            $this->action->updateAttributes(
                [$product_ids],
                $updated_values,
                $storeId
            );
        } catch (Exception $e) {
            $this->logger->warning('Unable to reset Bynder attributes for SKU ' . $sku . ': ' . $e->getMessage());
        }
    }
}
