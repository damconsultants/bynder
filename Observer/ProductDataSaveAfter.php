<?php

namespace DamConsultants\Bynder\Observer;

use Magento\Framework\Event\ObserverInterface;
use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class ProductDataSaveAfter implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resource;

    /**
     * @var \Magento\Catalog\Model\Product\Action
     */
    protected $productActionObject;

    /**
     * Product save after
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Catalog\Model\Product\Action $productActionObject
     * @param \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData
     * @param \DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory $collection
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \DamConsultants\Bynder\Helper\Data $DataHelper
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Backend\Model\View\Result\Redirect $resultRedirect
     */

    public function __construct(
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Catalog\Model\Product\Action $productActionObject,
        \DamConsultants\Bynder\Model\BynderSycDataFactory $byndersycData,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory $collection,
        \Magento\Framework\App\ResourceConnection $resource,
        \DamConsultants\Bynder\Helper\Data $DataHelper,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Backend\Model\View\Result\Redirect $resultRedirect
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->productActionObject = $productActionObject;
        $this->_byndersycData = $byndersycData;
        $this->datahelper = $DataHelper;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->_collection = $collection;
        $this->_resource = $resource;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirect;
    }
    /**
     * Execute
     *
     * @return $this
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        
        $product = $observer->getProduct();
        $productId = $observer->getProduct()->getId();
        $product_sku_key = $product->getData('sku');
        $bynder_multi_img = $product->getData('bynder_multi_img');
        $bynder_document = $product->getData('bynder_document');
        $image = $this->cookieManager->getCookie('bynder_image');
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $document = $this->cookieManager->getCookie('bynder_doc');
        $model = $this->_byndersycData->create();
        $collection = $this->_collection->create()->addFieldToFilter('sku', $product_sku_key);
        $delete_collection = $this->_collection->create()->addFieldToFilter('remove_for_magento', '0');
        $connection  = $this->_resource->getConnection();
        $tableName = $connection->getTableName("bynder_cron_data");
        $metaProperty_Collection = $this->metaPropertyCollectionFactory->create()->getData();
        if (!empty($metaProperty_Collection)) {
            $metaProperty_Collections = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];
        }
        /******************************Document Section******************************************************************************** */
        if (isset($document)) {
            $doc_json = json_decode($document, true);
            $old_doc_url = [];
            if (!empty($bynder_document)) {
                $old_doc = json_decode($bynder_document, true);
                
                if (!empty($old_doc)) {
                    foreach ($old_doc as $d_old) {
                        $old_doc_url[] = $d_old['item_url'];
                    }
                    
                }
            }
            /*********************************************When URL Already have in DataBase Then Update Data ********************************** */
            if (!empty($collection)) {
                $docs = [];
                if (!empty($doc_json)) {
                    foreach ($doc_json as $doc_s) {
                        $docs[] = $doc_s['item_url'];
                    }
                }
                $old_doc_collection = [];
                foreach ($collection as $doc_col) {
                    $old_doc_collection[] = $doc_col['bynder_data'];
                    if ($doc_col['bynder_data_type'] == '2') {
                        if (!in_array($doc_col['bynder_data'], $docs)) {
                            $data =  ["remove_for_magento"=>"0"];
                            $where = ['id = ?' => $doc_col['id']];
                        } else {
                            $data =  ["remove_for_magento"=>"1"];
                            $where = ['id = ?' => $doc_col['id']];
                        }
                        $connection->update($tableName, $data, $where);
                    }
                }
                /************When Delete Compactview Side then also Delete Sku Bynder Side ********************** */
                foreach ($delete_collection as $delete) {
                    if (!empty($metaProperty_Collections)) {
                        if ($delete['sku'] == $product_sku_key) {
                            $this->datahelper->getDataRemoveForMagento(
                                $product_sku_key,
                                $delete['media_id'],
                                $metaProperty_Collections
                            );
                        }
                    }
                    
                }
                /********************************************************************************************* */
            }
            /******************************************Insert Data from DataBase Side****************************** */
            if (!empty($doc_json)) {
                foreach ($doc_json as $doc) {
                    if (!in_array($doc['item_url'], $old_doc_url)) {
                        $media_doc_explode = explode("/", $doc['item_url']);
                        /*********When add Compactview side then also sku add Bynder Side ******************* */
                        if (!empty($metaProperty_Collections)) {
                            $this->datahelper->getAddedCompactviewSkuFromBynder(
                                $product_sku_key,
                                $media_doc_explode[4],
                                $metaProperty_Collections
                            );
                        } else {
                            $this->messageManager->addError(
                                'Bynder Item Not Save First Select The Metaproperty.....'
                            );
                            $this->cookieManager->deleteCookie('bynder_doc');
                            $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                            $publicCookieMetadata->setDurationOneYear();
                            $publicCookieMetadata->setPath('/');
                            $publicCookieMetadata->setHttpOnly(false);

                            $this->cookieManager->setPublicCookie(
                                'bynder_doc',
                                null,
                                $publicCookieMetadata
                            );
                            return $this->resultRedirectFactory->setPath('*/*/');
                        }
                        
                        if (!in_array($doc['item_url'], $old_doc_collection)) {
                            $data_doc_value = [
                                'sku' => $product_sku_key,
                                'bynder_data' => $doc['item_url'],
                                'bynder_data_type' => '2',
                                'media_id' => $media_doc_explode[4],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '2',
                                'added_date' => time()
                            ];
                            $model->setData($data_doc_value);
                            $model->save();
                        }
                    }
                    
                }
            }
            $this->productActionObject->updateAttributes([$productId], ['bynder_document' => $document], 0);
            $this->cookieManager->deleteCookie('bynder_doc');
            $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
            $publicCookieMetadata->setDurationOneYear();
            $publicCookieMetadata->setPath('/');
            $publicCookieMetadata->setHttpOnly(false);

            $this->cookieManager->setPublicCookie(
                'bynder_doc',
                null,
                $publicCookieMetadata
            );
        }
        /******************************************************************************************************************** */
        /***************************Video and Image Section ***************************************************************** */
        $video = "";
        $flag = 0;
        if (isset($image)) {
            $image_json = json_decode($image, true);
            $old_url = [];
            if (!empty($bynder_multi_img)) {
                $old_img = json_decode($bynder_multi_img, true);
                
                if (!empty($old_img)) {
                    foreach ($old_img as $old) {
                        $old_url[] = $old['item_url'];
                    }
                    
                }
            }
            /*********************************************When URL Already have in DataBase Then Update Data ********************************** */
            if (!empty($collection)) {
                $imgse = [];
                if (!empty($image_json)) {
                    foreach ($image_json as $imgs) {
                        $imgse[] = $imgs['item_url'];
                    }
                }
                $old_collection = [];
                $sku = [];
                foreach ($collection as $col) {
                    $old_collection[] = $col['bynder_data'];
                    $sku[] = $col['sku'];
                    if ($col['bynder_data_type'] != '2') {
                        if (!in_array($col['bynder_data'], $imgse)) {
                            $data =  ["remove_for_magento"=>"0"];
                            $where = ['id = ?' => $col['id']];
                        } else {
                            $data =  ["remove_for_magento"=>"1"];
                            $where = ['id = ?' => $col['id']];
                        }
                        $connection->update($tableName, $data, $where);
                    }
                }
                /************When Delete Compactview Side then also Delete Sku Bynder Side ********************** */
                foreach ($delete_collection as $delete) {
                    if (!empty($metaProperty_Collections)) {
                        if ($delete['sku'] == $product_sku_key) {
                            $this->datahelper->getDataRemoveForMagento(
                                $product_sku_key,
                                $delete['media_id'],
                                $metaProperty_Collections
                            );
                        }
                    }
                }
                /********************************************************************************************* */
            }
             
            $type = [];
            /******************************************Insert Data from DataBase Side****************************** */
            if (!empty($image_json)) {
                foreach ($image_json as $img) {
                    if (!in_array($img['item_url'], $old_url)) {
                        $media_image_explode = explode("/", $img['item_url']);
                        /*********When add Compactview side then also sku add Bynder Side ******************* */
                        if (!empty($metaProperty_Collections)) {
                            $this->datahelper->getAddedCompactviewSkuFromBynder(
                                $product_sku_key,
                                $media_image_explode[5],
                                $metaProperty_Collections
                            );
                        } else {
                            $this->messageManager->addError(
                                'Bynder Item Not Save First Select The Metaproperty.....'
                            );
                            $this->cookieManager->deleteCookie('bynder_image');
                            $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                            $publicCookieMetadata->setDurationOneYear();
                            $publicCookieMetadata->setPath('/');
                            $publicCookieMetadata->setHttpOnly(false);

                            $this->cookieManager->setPublicCookie(
                                'bynder_image',
                                null,
                                $publicCookieMetadata
                            );
                            return $this->resultRedirectFactory->setPath('*/*/');
                        }
                        if (!in_array($img['item_url'], $old_collection)) {
                            $data_image_data = [
                                'sku' => $product_sku_key,
                                'bynder_data' =>$img['item_url'],
                                'bynder_data_type' => ($img['item_type'] == "IMAGE") ? '1' : '3',
                                'media_id' => $media_image_explode[5],
                                'remove_for_magento' => '1',
                                'added_on_cron_compactview' => '2',
                                'added_date' => time()
                            ];
                            $model->setData($data_image_data);
                            $model->save();
                        }
                        
                    }
                    $type[] = $img['item_type'];
                }
                /*  IMAGE & VIDEO == 1
                IMAGE == 2
                VIDEO == 3 */
                if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
                    $flag = 1;
                } elseif (in_array("IMAGE", $type)) {
                    $flag = 2;
                } elseif (in_array("VIDEO", $type)) {
                    $flag = 3;
                }
            }
            $this->productActionObject->updateAttributes([$productId], ['bynder_isMain' => $flag], $storeId);
            $this->productActionObject->updateAttributes([$productId], ['bynder_multi_img' => $image], $storeId);
            if ($product->getBynderVideos()) {
                $this->productActionObject->updateAttributes([$productId], ['bynder_videos' => $video], $storeId);
            }
            $this->cookieManager->deleteCookie('bynder_image');
            $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
            $publicCookieMetadata->setDurationOneYear();
            $publicCookieMetadata->setPath('/');
            $publicCookieMetadata->setHttpOnly(false);

            $this->cookieManager->setPublicCookie(
                'bynder_image',
                null,
                $publicCookieMetadata
            );
        }
    }
}
