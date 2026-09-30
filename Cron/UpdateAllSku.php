<?php

namespace DamConsultants\Bynder\Cron;

use Psr\Log\LoggerInterface;
use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;
use DamConsultants\Bynder\Model\ResourceModel\Collection\MagentoSkuCollectionFactory;
use DamConsultants\Bynder\Model\ResourceModel\MagentoSku;
use Magento\Framework\App\ResourceConnection;
use Exception;

class UpdateAllSku
{
    /**
     * processSku() outcome: SKU handled, remove it from the queue
     */
    private const SKU_DONE = 'done';

    /**
     * processSku() outcome: skip to the next SKU without removing it
     */
    private const SKU_SKIP = 'skip';

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;
    /**
     * @var $resultJsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var $productAction
     */
    protected $productAction;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var $bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var $bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $product
     */
    protected $product;
    /**
     * @var ResourceConnection
     */
    protected $resource;
    /**
     * @var MagentoSkuCollectionFactory
     */
    protected $magentoSkuCollectionFactory;
    /**
     * @var MagentoSku
     */
    protected $magentoSku;

    /**
     * Product Sku.
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Bynder\Model\BynderConfigSyncDataFactory $byndersycData
     * @param \DamConsultants\Bynder\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param MagentoSkuCollectionFactory $magentoSkuCollectionFactory
     * @param MagentoSku $magentoSku
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \DamConsultants\Bynder\Helper\Data $DataHelper
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param ResourceConnection $resource
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Bynder\Model\BynderConfigSyncDataFactory $byndersycData,
        \DamConsultants\Bynder\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        MagentoSkuCollectionFactory $magentoSkuCollectionFactory,
        MagentoSku $magentoSku,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        ResourceConnection $resource
    ) {
        $this->resultJsonFactory = $jsonFactory;
        $this->productAction = $action;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->magentoSkuCollectionFactory = $magentoSkuCollectionFactory;
        $this->datahelper = $DataHelper;
        $this->magentoSku = $magentoSku;
        $this->_byndersycData = $byndersycData;
        $this->_productRepository = $productRepository;
        $this->product = $product;
        $this->resource = $resource;
    }

    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $skucollection = $this->magentoSkuCollectionFactory->create();
        $skucollection->addFieldToFilter('status', 'pending')->setPageSize(100);
        if ($skucollection->getSize() === 0) {
            return $result->setData(['status' => 0, 'message' => 'No pending SKUs to process.']);
        }
        $property_id = null;

        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);

        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];
        foreach ($skucollection as $skuData) {
            $outcome = $this->processSku(
                $skuData,
                $result,
                $property_id,
                $collection_value,
                $collection_slug_val
            );
            if ($outcome === self::SKU_SKIP) {
                continue;
            }
            if ($outcome !== self::SKU_DONE) {
                return $outcome;
            }
            $this->magentoSku->delete($skuData);
        }
        $result_data = $result->setData([
            'status' => 1,
            'message' => 'Data Sync Successfully.Please check Bynder Synchronization Log.!'
        ]);
        return $result_data;
    }

    /**
     * Process a single queued SKU
     *
     * Returns SKU_DONE (delete row and continue), SKU_SKIP (continue without deleting)
     * or a JSON result that execute() must return immediately.
     *
     * @param mixed $skuData
     * @param \Magento\Framework\Controller\Result\Json $result
     * @param mixed $property_id
     * @param array $collection_value
     * @param array $collection_slug_val
     * @return string|\Magento\Framework\Controller\Result\Json
     */
    private function processSku($skuData, $result, $property_id, $collection_value, $collection_slug_val)
    {
        $sku = $skuData['sku'];
        if ($sku == "") {
            return self::SKU_DONE;
        }
        $select_attribute = $skuData['select_attribute'];
        $select_store = $skuData['select_store'];

        if (!$this->isSkuInCatalog($sku)) {
            return self::SKU_SKIP;
        }

        $bd_sku = trim(preg_replace('/[^A-Za-z0-9-]/', '_', $sku));
        $storeIds = $this->storeManagerInterface->getStore()->getId();
        $_product = $this->_productRepository->get($sku);
        $product_ids = $_product->getId();
        $get_data = $this->datahelper->getImageSyncWithProperties(
            $bd_sku,
            $property_id,
            $collection_value
        );
        $getIsJson = $this->getIsJSON($get_data);
        if (empty($get_data) || !$getIsJson) {
            return $result->setData(
                [
                    'status' => 0,
                    'message' => 'Something went wrong from API side, Please contact to support team!'
                ]
            );
        }

        $respon_array = json_decode($get_data, true);
        if ($respon_array['status'] != 1) {
            $insert_data = [
                "sku" => $sku,
                "message" => 'Please Select The Metaproperty First.....',
                "data_type" => "",
                "lable" => 0
            ];
            $this->getInsertDataTable($insert_data);
            return $result->setData(
                ['status' => 0, 'message' => 'Please check Bynder Synchronization. Action Log.....']
            );
        }

        $convert_array = json_decode($respon_array['data'], true);
        if ($convert_array['status'] == 1) {
            try {
                $this->getDataItem(
                    $storeIds,
                    $select_attribute,
                    $convert_array,
                    $collection_slug_val,
                    $sku
                );
            } catch (Exception $e) {
                $insert_data = [
                    "sku" => $sku,
                    "message" => $e->getMessage(),
                    "data_type" => "",
                    "lable" => 0
                ];
                $this->getInsertDataTable($insert_data);
            }
            return self::SKU_DONE;
        }

        $this->resetBynderAttributes($product_ids, $storeIds, $select_store);
        $insert_data = [
            "sku" => $sku,
            "message" => $convert_array['data'],
            "data_type" => "",
            "lable" => "0"
        ];
        $this->getInsertDataTable($insert_data);
        return self::SKU_DONE;
    }

    /**
     * Check the SKU exists in the catalog, logging when it does not
     *
     * @param string $sku
     * @return bool
     */
    private function isSkuInCatalog($sku)
    {
        try {
            $product_id = $this->product->getIdBySku($sku);
            if (!$product_id) {
                $insert_data = [
                    "sku" => $sku,
                    "message" => "SKU not found in products",
                    "data_type" => "",
                    "lable" => "0"
                ];
                $this->getInsertDataTable($insert_data);
                return false;
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $insert_data = [
                "sku" => $sku,
                "message" => "SKU not match in products",
                "data_type" => "",
                "lable" => "0"
            ];
            $this->getInsertDataTable($insert_data);
            return false;
        }
        return true;
    }

    /**
     * Clear Bynder attributes for the product in the selected store(s)
     *
     * @param mixed $product_ids
     * @param mixed $storeIds
     * @param mixed $select_store
     * @return void
     */
    private function resetBynderAttributes($product_ids, $storeIds, $select_store)
    {
        $updated_values = [
            'bynder_multi_img' => null,
            'bynder_isMain' => null,
            'bynder_auto_replace' => null
        ];

        if ($select_store == 'all_store') {
            $this->productAction->updateAttributes(
                [$product_ids],
                $updated_values,
                $storeIds
            );
            $all_stores = $this->getMyStoreId();
            if (count($all_stores) > 0) {
                foreach ($all_stores as $storeId) {
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        $updated_values,
                        $storeId
                    );
                }
            }
        } else {
            $this->productAction->updateAttributes(
                [$product_ids],
                $updated_values,
                $select_store
            );
        }
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
            'bynder_sync_data' => $insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            'lable' => $insert_data['lable']
        ];
        $model->setData($data_image_data);
        $model->save();
    }

    /**
     * Is Json
     *
     * @param string $sku
     * @param array $m_id
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
            $new_m_id = trim($new_data);
            $data_image_data = [
                'sku' => $sku,
                'media_id' => $new_m_id,
                'status' => "1",
            ];
            $model->setData($data_image_data);
            $model->save();
        }
        $updated_values = [
            'bynder_delete_cron' => 1
        ];
        try {
            $this->productAction->updateAttributes(
                [$product_ids],
                $updated_values,
                $storeId
            );
        } catch (Exception $e) {
            $insert_data = [
                "sku" => $sku,
                "message" => $e->getMessage(),
                'media_id' => "",
                "data_type" => ""
            ];
            $this->getInsertDataTable($insert_data);
        }
    }

    /**
     * Is Json
     *
     * @param string $sku
     * @param string $media_id
     * @return $this
     */
    public function getDeleteMedaiDataTable($sku, $media_id)
    {
        $model = $this->bynderMediaTableCollectionFactory->create();
        $model->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();
            }
        }
    }

    /**
     * Get Data Item
     *
     * @param string $select_store
     * @param string $select_attribute
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param array $current_sku
     * @return this
     */
    public function getDataItem(
        $select_store,
        $select_attribute,
        $convert_array,
        $collection_data_slug_val,
        $current_sku
    ) {
        $data_arr = [];
        $doc_data_arr = [];
        $data_val_arr = [];
        $doc_data = [];
        $result = $this->resultJsonFactory->create();
        if ($convert_array['status'] == 1) {
            foreach ($convert_array['data'] as $k => $data_value) {
                $isTypeMatch = ($select_attribute == $data_value['type']);
                if (!$isTypeMatch && $select_attribute != 'all_attribute') {
                    continue;
                }
                $asset = $this->buildAssetItem($data_value, $collection_data_slug_val, $current_sku);
                if ($asset === null) {
                    continue;
                }
                // Documents go to the separate document list only in the "all_attribute" branch
                if ($asset['is_doc'] && !$isTypeMatch) {
                    $doc_data_arr[] = $current_sku;
                    $doc_data[] = $asset['entry'];
                } else {
                    $data_arr[] = $current_sku;
                    $data_val_arr[] = $asset['entry'];
                }
            }
        }
        if (count($data_arr) > 0) {
            $this->getProcessItem($data_arr, $data_val_arr, $select_attribute);
        }
        if (count($doc_data_arr) > 0) {
            $this->getProcessItemDoc($doc_data_arr, $doc_data, $select_attribute);
        }
        if (count($doc_data_arr) == 0 || count($data_arr) == 0) {
            $result_data = $result->setData(['status' => 0, 'message' => 'No Data Found...']);
            return $result_data;
        }
    }

    /**
     * Build a single asset entry (image, video or document) from the API data
     *
     * Returns null for a document without a public URL.
     *
     * @param array $data_value
     * @param array $collection_data_slug_val
     * @param string $current_sku
     * @return array|null ['is_doc' => bool, 'entry' => array]
     */
    private function buildAssetItem($data_value, $collection_data_slug_val, $current_sku)
    {
        $bynder_media_id = $data_value['id'];
        $image_data = $data_value['thumbnails'];
        // Read as in the original code (these keys are expected to be present)
        $bynder_image_role = $image_data['magento_role_options'];
        $bynder_alt_text = $image_data['img_alt_text'];
        $sku_slug_name = "property_" . $collection_data_slug_val['sku']['bynder_property_slug'];

        /*Below code for multiple derivative according to image roll */
        $imageMetadata = $this->prepareImageMetadata($image_data, $bynder_media_id);
        $new_image_role = $imageMetadata['roles'];
        $new_magento_role_list = $new_image_role;
        $new_bynder_mediaid_text = array_unique($imageMetadata['media_ids']);
        $new_bynder_alt_text = array_unique($imageMetadata['alt_text']);

        if ($data_value['type'] == "image") {
            $image_link = $image_data['Base'] ?? "";
            return [
                'is_doc' => false,
                'entry' => [
                    "sku" => $current_sku,
                    "url" => [$image_link . "\n"],
                    'magento_image_role' => $new_magento_role_list,
                    'image_alt_text' => $new_bynder_alt_text,
                    'bynder_media_id_new' => $new_bynder_mediaid_text,
                ],
            ];
        }

        if ($data_value['type'] == 'video') {
            $video_link = ($data_value["videoPreviewURLs"][0] ?? "")
                . '@@' . ($image_data["webimage"] ?? "");
            return [
                'is_doc' => false,
                'entry' => [
                    "sku" => $current_sku,
                    "url" => [$video_link . "\n"],
                    'magento_image_role' => $new_image_role,
                    'image_alt_text' => $new_bynder_alt_text,
                    'bynder_media_id_new' => $new_bynder_mediaid_text,
                    "type" => "video",
                ],
            ];
        }

        $doc_link = $this->getDocumentLink($data_value);
        if (empty($doc_link)) {
            return null;
        }
        return [
            'is_doc' => true,
            'entry' => [
                "sku" => $current_sku,
                "url" => [$doc_link],
                'magento_image_role' => $new_image_role,
                'image_alt_text' => $new_bynder_alt_text,
                'bynder_media_id_new' => $new_bynder_mediaid_text,
            ],
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
     * @param string $select_attribute
     * @return $this
     */
    public function getProcessItem($data_arr, $data_val_arr, $select_attribute)
    {
        $result = $this->resultJsonFactory->create();
        $image_value_details_role = [];
        $temp_arr = [];
        foreach ($data_arr as $key => $skus) {
            $temp_arr[$skus][] =  implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$skus][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$skus][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$skus][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
        }
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
                $select_attribute
            );
        }
    }

    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @param string $select_attribute
     * @return $this
     */
    public function getProcessItemDoc($data_arr, $data_val_arr, $select_attribute)
    {
        $result = $this->resultJsonFactory->create();
        $image_value_details_role = [];
        $temp_arr = [];
        foreach ($data_arr as $key => $skus) {
            $temp_arr[$skus][] =  implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$skus][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$skus][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$skus][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
        }
        foreach ($temp_arr as $product_sku_key => $image_value) {
            $img_json = implode("", $image_value);
            $mg_role = $image_value_details_role[$product_sku_key];
            $image_alt_text_value = implode("", $image_alt_text[$product_sku_key]);
            $this->getUpdateDoc(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $byn_md_id_new,
                $select_attribute
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
     * @param string $select_attribute
     */
    public function getUpdateDoc(
        $img_json,
        $product_sku_key,
        $mg_img_role_option,
        $img_alt_text,
        $bynder_media_ids,
        $select_attribute
    ) {
        $result = $this->resultJsonFactory->create();
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            $doc_values = $_product->getBynderDocument();
            $bynder_media_id = $bynder_media_ids[$product_sku_key];
            if (empty($doc_values)) {
                $doc_detail = $this->buildDocumentDetails(
                    $img_json,
                    $product_sku_key,
                    $bynder_media_id,
                    false,
                    true
                );
            } else {
                $this->readExistingDocuments($doc_values);
                $doc_detail = $this->buildDocumentDetails(
                    $img_json,
                    $product_sku_key,
                    $bynder_media_id,
                    true,
                    true
                );
            }
            $new_value_array = json_encode($doc_detail, true);
            $this->productAction->updateAttributes(
                [$product_ids],
                ['bynder_document' => $new_value_array],
                $storeId
            );
        } catch (\Exception $e) {
            return $result->setData(['message' => $e->getMessage()]);
        }
    }

    /**
     * Read existing document entries (kept from the original flow; values are not used further)
     *
     * @param string $doc_json
     * @return array
     */
    private function readExistingDocuments($doc_json)
    {
        $all_item_url = [];
        $b_id = [];
        $item_old_value = json_decode($doc_json, true);
        if (!is_array($item_old_value)) {
            return [$all_item_url, $b_id];
        }
        foreach ($item_old_value as $doc) {
            if ($doc['item_type'] == 'DOCUMENT') {
                $all_item_url[] = $doc['item_url'];
                $b_id[] = $doc['bynder_md_id'];
            }
        }
        return [$all_item_url, $b_id];
    }

    /**
     * Build document details from the "url@@name" lines
     *
     * @param string $img_json
     * @param string $product_sku_key
     * @param array $bynder_media_id
     * @param bool $skipEmpty skip empty lines before parsing
     * @param bool $logEach write a sync log row for each document
     * @return array
     */
    private function buildDocumentDetails($img_json, $product_sku_key, $bynder_media_id, $skipEmpty, $logEach)
    {
        $doc_detail = [];
        $new_doc_array = explode("\n", $img_json);
        foreach ($new_doc_array as $vv => $doc_line) {
            if ($skipEmpty && empty($doc_line)) {
                continue;
            }
            $doc_name = explode("@@", $doc_line);
            if (!isset($doc_name[1]) || !isset($bynder_media_id[$vv])) {
                continue;
            }
            $doc_detail[] = [
                "item_url" => $doc_name[0],
                "item_type" => 'DOCUMENT',
                "doc_name" => $doc_name[1],
                "bynder_md_id" => $bynder_media_id[$vv],
            ];
            if ($logEach) {
                $data_doc_value = [
                    'sku' => $product_sku_key,
                    'message' => $doc_name[0],
                    'data_type' => '2',
                    'media_id' => $bynder_media_id[$vv],
                    'lable' => 1
                ];
                $this->getInsertDataTable($data_doc_value);
            }
        }
        return $doc_detail;
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
     * @param string $select_attribute
     */
    public function getUpdateImage(
        $img_json,
        $product_sku_key,
        $mg_img_role_option,
        $img_alt_text,
        $bynder_media_ids,
        $select_attribute
    ) {
        $result = $this->resultJsonFactory->create();
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            $image_value = $_product->getBynderMultiImg();
            $doc_value = $_product->getBynderDocument();
            $bynder_media_id = $bynder_media_ids[$product_sku_key];
            $args = [
                $img_json,
                $img_alt_text,
                $mg_img_role_option,
                $image_value,
                $bynder_media_id,
                $product_sku_key,
                $product_ids,
                $storeId
            ];
            if ($select_attribute == "image") {
                if (!empty($image_value)) {
                    $this->updateImagesWithExisting(...$args);
                } else {
                    $this->updateImagesNew(...$args);
                }
            } elseif ($select_attribute == "video") {
                if (!empty($image_value)) {
                    $this->updateVideosWithExisting(
                        $img_json,
                        $image_value,
                        $bynder_media_id,
                        $product_sku_key,
                        $product_ids,
                        $storeId
                    );
                } else {
                    $this->updateVideosNew($img_json, $bynder_media_id, $product_sku_key, $product_ids, $storeId);
                }
            } elseif ($select_attribute == "document") {
                $this->updateDocumentAttribute(
                    $img_json,
                    $doc_value,
                    $bynder_media_id,
                    $product_sku_key,
                    $product_ids,
                    $storeId
                );
            } elseif ($select_attribute == "all_attribute") {
                if (!empty($image_value)) {
                    $this->updateAllWithExisting(...$args);
                } else {
                    $this->updateAllNew(...$args);
                }
            }
        } catch (\Exception $e) {
            return $result->setData(['message' => $e->getMessage()]);
        }
    }

    /**
     * "image" attribute: product already has Bynder media
     *
     * @param string $img_json
     * @param string $img_alt_text
     * @param array $mg_img_role_option
     * @param string $image_value
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateImagesWithExisting(
        $img_json,
        $img_alt_text,
        $mg_img_role_option,
        $image_value,
        $bynder_media_id,
        $product_sku_key,
        $product_ids,
        $storeId
    ) {
        $image_detail = [];
        $diff_image_detail = [];
        $new_image_array = explode("\n", $img_json);
        $new_alttext_array = explode("\n", $img_alt_text);
        $new_magento_role_option_array = $mg_img_role_option;
        $all_item_url = [];
        $item_old_value = json_decode($image_value, true);
        if (count($item_old_value) > 0) {
            foreach ($item_old_value as $img) {
                $all_item_url[] = $img['item_url'];
            }
        }
        foreach ($new_image_array as $vv => $new_image_value) {
            if (trim($new_image_value) == "" || $new_image_value == "no image") {
                continue;
            }
            $item_url = explode("@@", $new_image_value);
            $img_altText_val = $this->getAltTextValue($new_alttext_array, $vv);
            $curt_img_role = $this->getCurrentImageRole($new_magento_role_option_array, $vv);
            $find_video = strpos($new_image_value, "@@");
            if ($find_video) {
                continue;
            }
            $image_detail[] = [
                "item_url" => $new_image_value,
                "alt_text" => $img_altText_val,
                "image_role" => $curt_img_role,
                "item_type" => 'IMAGE',
                "thum_url" => $item_url[0],
                "bynder_md_id" => $bynder_media_id[$vv],
                "is_import" => 0,
            ];
            $image_detail = $this->stripRolesFromEarlierImages($image_detail, $new_magento_role_option_array, $vv);
            if (in_array($item_url[0], $all_item_url)) {
                continue;
            }
            $diff_image_detail[] = [
                "item_url" => $new_image_value,
                "alt_text" => $img_altText_val,
                "image_role" => $curt_img_role,
                "item_type" => 'IMAGE',
                "thum_url" => $new_image_value,
                "bynder_md_id" => $bynder_media_id[$vv],
                "is_import" => 0,
            ];
            $item_old_value = $this->stripRolesFromAllImages($item_old_value, $new_magento_role_option_array, $vv);
            $diff_image_detail = $this->stripRolesFromEarlierImages(
                $diff_image_detail,
                $new_magento_role_option_array,
                $vv
            );
        }
        $image_detail = $this->applyDefaultBaseRoles($image_detail);
        $image_detail = $this->removePlaceholderRoles($image_detail);

        $d_img_roll = "";
        $d_media_id = [];
        if (count($diff_image_detail) > 0) {
            foreach ($diff_image_detail as $d_img) {
                $d_img_roll = $d_img['image_role'];
                $d_media_id[] =  $d_img['bynder_md_id'];
            }
            $this->getInsertMedaiDataTable($product_sku_key, $d_media_id, $product_ids, $storeId);
        }
        if (count($image_detail) > 0) {
            foreach ($image_detail as $img) {
                $image[] = $img['item_url'];
            }
        }
        $old_video_detail = [];
        $new_image_detail = [];
        foreach ($item_old_value as $key1 => $img) {
            if ($img['item_type'] == 'IMAGE') {
                if (in_array($img['item_url'], $image)) {
                    $item_key = array_search($img['item_url'], array_column($image_detail, "item_url"));
                    if (isset($d_img_roll)) {
                        $roll = $image_detail[$item_key]['image_role'];
                    } else {
                        $roll = $img['image_role'];
                    }
                    $new_image_detail[] = [
                        "item_url" => $img['item_url'],
                        "alt_text" => $image_detail[$item_key]['alt_text'],
                        "image_role" => $roll,
                        "item_type" => $img['item_type'],
                        "thum_url" => $img['thum_url'],
                        "bynder_md_id" => $img['bynder_md_id'],
                        "is_import" => $img['is_import'],
                    ];
                }
                if (count($new_image_detail) > 1) {
                    $new_image_detail = $this->stripRolesFromEarlierImages(
                        $new_image_detail,
                        $new_magento_role_option_array,
                        $item_key
                    );
                }
            } elseif ($img['item_type'] == 'VIDEO') {
                $old_video_detail[] = [
                    "item_url" => $img['item_url'],
                    "image_role" => null,
                    "item_type" => $img['item_type'],
                    "thum_url" => $img['thum_url'],
                    "bynder_md_id" => $img['bynder_md_id'],
                ];
            }
        }
        $array_merge = array_merge($new_image_detail, $diff_image_detail);
        $final_array_merge = array_merge($image_detail, $old_video_detail);
        $type = [];
        $image = [];
        $media_id = [];
        foreach ($final_array_merge as $img) {
            $type[] = $img['item_type'];
            $image[] = $img['item_url'];
            $media_id[] = $img['bynder_md_id'];
            $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
        }
        $this->getInsertMedaiDataTable($product_sku_key, $media_id, $product_ids, $storeId);
        $image_value_array = implode(',', $image);
        $flag = $this->getMediaFlag($type);
        $new_value_array = json_encode($final_array_merge, true);
        $data_image_data = [
            'sku' => $product_sku_key,
            'message' => $image_value_array,
            'data_type' => '1',
            "lable" => "1"
        ];
        $this->getInsertDataTable($data_image_data);
        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'use_bynder_cdn' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * "image" attribute: product has no Bynder media yet
     *
     * @param string $img_json
     * @param string $img_alt_text
     * @param array $mg_img_role_option
     * @param string $image_value
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateImagesNew(
        $img_json,
        $img_alt_text,
        $mg_img_role_option,
        $image_value,
        $bynder_media_id,
        $product_sku_key,
        $product_ids,
        $storeId
    ) {
        $image_detail = [];
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
            if (!$find_video) {
                $image_detail[] = [
                    "item_url" => $image_line,
                    "alt_text" => $img_altText_val,
                    "image_role" => $curt_img_role,
                    "item_type" => 'IMAGE',
                    "thum_url" => $image_line,
                    "bynder_md_id" => $bynder_media_id[$vv],
                    "is_import" => 0,
                ];
            }
            $image_detail = $this->stripRolesFromEarlierImages($image_detail, $new_magento_role_option_array, $vv);
        }
        $image_detail = $this->applyDefaultBaseRoles($image_detail);
        $media_id = [];
        $image = [];
        foreach ($image_detail as $img) {
            $type[] = $img['item_type'];
            $image[] = $img['item_url'];
            $media_id[] = $img['bynder_md_id'];
        }
        $this->getInsertMedaiDataTable($product_sku_key, $media_id, $product_ids, $storeId);
        $image_value_array = implode(',', $image);
        $flag = $this->getMediaFlag($type);
        $data_image_data = [
            'sku' => $product_sku_key,
            'message' => $image_value_array,
            'data_type' => '1',
            "lable" => "1"
        ];
        $this->getInsertDataTable($data_image_data);
        $new_value_array = json_encode($image_detail, true);

        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'use_bynder_cdn' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * "video" attribute: product already has Bynder media
     *
     * @param string $img_json
     * @param string $image_value
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateVideosWithExisting(
        $img_json,
        $image_value,
        $bynder_media_id,
        $product_sku_key,
        $product_ids,
        $storeId
    ) {
        $video_detail = [];
        $new_video_array = explode("\n", $img_json);
        $old_value_array = json_decode($image_value, true);
        $old_item_url = [];
        if (!empty($old_value_array)) {
            foreach ($old_value_array as $value) {
                $old_item_url[] = $value['item_url'];
            }
        }
        foreach ($new_video_array as $vv => $video_value) {
            $item_url = explode("@@", $video_value);
            $thum_url = explode("@@", $video_value);
            $find_video = strpos($video_value, "@@");
            if ($find_video && !in_array($item_url[0], $old_item_url)) {
                $video_detail[] = [
                    "item_url" => $item_url[0],
                    "image_role" => null,
                    "item_type" => 'VIDEO',
                    "thum_url" => $thum_url[1],
                    "bynder_md_id" => $bynder_media_id[$vv],
                ];
            }
        }
        $array_merge = array_merge($old_value_array, $video_detail);
        $v_m_id = [];
        foreach ($array_merge as $img) {
            $type[] = $img['item_type'];
            $v_m_id[] = $img['bynder_md_id'];
            $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
        }
        $this->getInsertMedaiDataTable($product_sku_key, $v_m_id, $product_ids, $storeId);
        $flag = $this->getMediaFlag($type);
        $new_value_array = json_encode($array_merge, true);
        $data_video_data = [
            'sku' => $product_sku_key,
            'message' => $new_value_array,
            'data_type' => '3',
            "lable" => "1"
        ];
        $this->getInsertDataTable($data_video_data);
        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'use_bynder_cdn' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * "video" attribute: product has no Bynder media yet
     *
     * @param string $img_json
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateVideosNew($img_json, $bynder_media_id, $product_sku_key, $product_ids, $storeId)
    {
        $new_video_array = explode("\n", $img_json);
        $video_detail = [];
        foreach ($new_video_array as $vv => $video_value) {
            $find_video = strpos($video_value, "@@");
            if ($find_video) {
                $item_url = explode("@@", $video_value);
                $thum_url = explode("@@", $video_value);
                $video_detail[] = [
                    "item_url" => $item_url[0],
                    "image_role" => null,
                    "item_type" => 'VIDEO',
                    "thum_url" => $thum_url[1],
                    "bynder_md_id" => $bynder_media_id[$vv],
                ];
            }
        }
        $video_m_id = [];
        foreach ($video_detail as $img) {
            $type[] = $img['item_type'];
            $video_m_id[] = $img['bynder_md_id'];
        }
        $this->getInsertMedaiDataTable($product_sku_key, $video_m_id, $product_ids, $storeId);
        $flag = $this->getMediaFlag($type);
        $new_value_array = json_encode($video_detail, true);
        $data_video_data = [
            'sku' => $product_sku_key,
            'message' => $new_value_array,
            'data_type' => '3',
            "lable" => "1"
        ];
        $this->getInsertDataTable($data_video_data);
        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'use_bynder_cdn' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * "document" attribute
     *
     * @param string $img_json
     * @param string $doc_value
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateDocumentAttribute(
        $img_json,
        $doc_value,
        $bynder_media_id,
        $product_sku_key,
        $product_ids,
        $storeId
    ) {
        if (empty($doc_value)) {
            // No sync log rows are written in this case (same as before)
            $doc_detail = $this->buildDocumentDetails($img_json, $product_sku_key, $bynder_media_id, false, false);
        } else {
            $this->readExistingDocuments($doc_value);
            $doc_detail = $this->buildDocumentDetails($img_json, $product_sku_key, $bynder_media_id, true, true);
        }
        $new_value_array = json_encode($doc_detail, true);
        $this->productAction->updateAttributes(
            [$product_ids],
            ['bynder_document' => $new_value_array],
            $storeId
        );
    }

    /**
     * "all_attribute": product already has Bynder media
     *
     * @param string $img_json
     * @param string $img_alt_text
     * @param array $mg_img_role_option
     * @param string $image_value
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateAllWithExisting(
        $img_json,
        $img_alt_text,
        $mg_img_role_option,
        $image_value,
        $bynder_media_id,
        $product_sku_key,
        $product_ids,
        $storeId
    ) {
        $image_detail = [];
        $video_detail = [];
        $diff_image_detail = [];
        $new_image_array = explode("\n", $img_json);
        $new_alttext_array = explode("\n", $img_alt_text);
        $new_magento_role_option_array = $mg_img_role_option;
        $all_item_url = [];
        $all_video_url = [];
        $item_old_value = json_decode($image_value, true);
        if (count($item_old_value) > 0) {
            foreach ($item_old_value as $img) {
                if ($img['item_type'] == 'IMAGE') {
                    $all_item_url[] = $img['item_url'];
                } else {
                    $all_video_url[] = $img['item_url'];
                }
            }
        }
        foreach ($new_image_array as $vv => $new_image_value) {
            if (trim($new_image_value) == "" || $new_image_value == "no image") {
                continue;
            }
            $item_url = explode("?", $new_image_value);
            $img_altText_val = $this->getAltTextValue($new_alttext_array, $vv);
            $curt_img_role = $this->getCurrentImageRole($new_magento_role_option_array, $vv);
            $find_video = strpos($new_image_value, "@@");
            if (!$find_video) {
                $image_detail[] = [
                    "item_url" => $new_image_value,
                    "alt_text" => $img_altText_val,
                    "image_role" => $curt_img_role,
                    "item_type" => 'IMAGE',
                    "thum_url" => $item_url[0],
                    "bynder_md_id" => $bynder_media_id[$vv],
                    "is_import" => 0,
                ];
                $image_detail = $this->stripRolesFromEarlierImages(
                    $image_detail,
                    $new_magento_role_option_array,
                    $vv
                );
                if (!in_array($item_url[0], $all_item_url)) {
                    $diff_image_detail[] = [
                        "item_url" => $new_image_value,
                        "alt_text" => $img_altText_val,
                        "image_role" => $curt_img_role,
                        "item_type" => 'IMAGE',
                        "thum_url" => $new_image_value,
                        "bynder_md_id" => $bynder_media_id[$vv],
                        "is_import" => 0,
                    ];
                    $item_old_value = $this->stripRolesFromAllImages(
                        $item_old_value,
                        $new_magento_role_option_array,
                        $vv
                    );
                    $diff_image_detail = $this->stripRolesFromEarlierImages(
                        $diff_image_detail,
                        $new_magento_role_option_array,
                        $vv
                    );
                }
            } else {
                $item_url = explode("@@", $new_image_value);
                $video_detail_diff = [];
                // Reset for every video line (same as before)
                $video_detail = [];
                if (!empty($new_image_value)) {
                    $video_detail[] = [
                        "item_url" => $item_url[0],
                        "image_role" => null,
                        "item_type" => 'VIDEO',
                        "thum_url" => $item_url[1],
                        "bynder_md_id" => $bynder_media_id[$vv],
                    ];
                    if (!in_array($item_url[0], $all_video_url)) {
                        $video_detail_diff[] = [
                            "item_url" => $item_url[0],
                            "image_role" => null,
                            "item_type" => 'VIDEO',
                            "thum_url" => $item_url[1],
                            "bynder_md_id" => $bynder_media_id[$vv],
                        ];
                        $data_video_data = [
                            'sku' => $product_sku_key,
                            'message' => $item_url[0],
                            'data_type' => '3',
                            'media_id' => $bynder_media_id[$vv],
                            'lable' => 1
                        ];
                        $this->getInsertDataTable($data_video_data);
                    }
                }
            }
        }
        $image_detail = $this->applyDefaultBaseRoles($image_detail);
        $image_detail = $this->removePlaceholderRoles($image_detail);
        $array_merge = array_merge($image_detail, $video_detail);
        $m_id = [];
        foreach ($array_merge as $img) {
            $type[] = $img['item_type'];
            $m_id[] = $img['bynder_md_id'];
            $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
        }
        $this->getInsertMedaiDataTable($product_sku_key, $m_id, $product_ids, $storeId);

        $flag = $this->getMediaFlag($type);
        $new_value_array = json_encode($array_merge, true);
        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'use_bynder_cdn' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }

    /**
     * "all_attribute": product has no Bynder media yet
     *
     * @param string $img_json
     * @param string $img_alt_text
     * @param array $mg_img_role_option
     * @param string $image_value
     * @param array $bynder_media_id
     * @param string $product_sku_key
     * @param mixed $product_ids
     * @param mixed $storeId
     * @return void
     */
    private function updateAllNew(
        $img_json,
        $img_alt_text,
        $mg_img_role_option,
        $image_value,
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
            if (!$find_video) {
                $image_detail[] = [
                    "item_url" => $image_line,
                    "alt_text" => $img_altText_val,
                    "image_role" => $curt_img_role,
                    "item_type" => 'IMAGE',
                    "thum_url" => $image_line,
                    "bynder_md_id" => $bynder_media_id[$vv],
                    "is_import" => 0,
                ];
                $data_image_data = [
                    'sku' => $product_sku_key,
                    'message' => $image_line,
                    'data_type' => '1',
                    'media_id' => $bynder_media_id[$vv],
                    'lable' => 1
                ];
                $this->getInsertDataTable($data_image_data);
                $image_detail = $this->stripRolesFromEarlierImages(
                    $image_detail,
                    $new_magento_role_option_array,
                    $vv
                );
            } elseif (!empty($image_line)) {
                $item_url = explode("@@", $image_line);
                $media_video_explode = explode("/", $item_url[0]);
                $video_detail[] = [
                    "item_url" => $item_url[0],
                    "image_role" => null,
                    "item_type" => 'VIDEO',
                    "thum_url" => $item_url[1],
                    "bynder_md_id" => $bynder_media_id[$vv],
                ];
                $data_video_data = [
                    'sku' => $product_sku_key,
                    'message' => $item_url[0],
                    'data_type' => '3',
                    'media_id' => $media_video_explode[5],
                    'lable' => 1
                ];
                $this->getInsertDataTable($data_video_data);
            }
        }
        $image_detail = $this->applyDefaultBaseRoles($image_detail);
        $image_detail = $this->removePlaceholderRoles($image_detail);
        $media_id = [];
        $image = [];
        $type = [];
        $both_merge = array_merge($image_detail, $video_detail);
        foreach ($both_merge as $img) {
            $type[] = $img['item_type'];
            $image[] = $img['item_url'];
            $media_id[] = $img['bynder_md_id'];
        }
        $this->getInsertMedaiDataTable($product_sku_key, $media_id, $product_ids, $storeId);
        $image_value_array = implode(',', $image);
        $flag = $this->getMediaFlag($type);
        $new_value_array = json_encode($both_merge, true);

        $updated_values = [
            'bynder_multi_img' => $new_value_array,
            'bynder_isMain' => $flag,
            'use_bynder_cdn' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
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
     * Remove the roles at $roleKey from every IMAGE entry
     *
     * @param array $details
     * @param array $roleOptions
     * @param mixed $roleKey
     * @return array
     */
    private function stripRolesFromAllImages($details, $roleOptions, $roleKey)
    {
        if (count($details) > 0) {
            foreach ($details as $kv => $img) {
                if ($img['item_type'] != "IMAGE" || $roleOptions[$roleKey] == "###") {
                    continue;
                }
                $new_mg_role_array = (array)$roleOptions[$roleKey];
                if (count($img["image_role"]) > 0 && count($new_mg_role_array) > 0) {
                    $details[$kv]["image_role"] = array_diff($img["image_role"], $new_mg_role_array);
                }
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
}
