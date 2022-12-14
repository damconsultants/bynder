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

class AddSkuFromBynder
{

	public function __construct(
		ProductRepository $productRepository,
		Attribute $attribute,
		Action $action,
		StoreManagerInterface $storeManagerInterface,
		\DamConsultants\Bynder\Helper\Data $DataHelper,
		\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
		\DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
		\DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory $byndersycData,
		\Psr\Log\LoggerInterface $logger,
		BynderFactory $bynder
	) {
		$this->_productRepository = $productRepository;
		$this->attribute = $attribute;
		$this->action = $action;
		$this->datahelper = $DataHelper;
		$this->collectionFactory = $collectionFactory;
		$this->_byndersycData = $byndersycData;
		$this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
		$this->storeManagerInterface = $storeManagerInterface;
		$this->bynder = $bynder;
		$this->_logger = $logger;
	}

	public function execute()
	{
		$this->_logger->info("Add Sku From Bynder");

		$metaProperty_Collection = "";

		$collection = $this->_byndersycData->create()->addFieldToFilter('added_on_cron_compactview', '2');
		$metaProperty_Collection = $this->metaPropertyCollectionFactory->create()->getData()[0]['property_id'];

		if(!empty($metaProperty_Collection)){
			$property_id = $metaProperty_Collection;
		}else{
			$property_id = "";
		}

		if (!empty($metaProperty_Collection)) {
			$data_collection = $collection->getData();
			foreach ($data_collection as $data_value) {
				$product_sku = $data_value['sku'];
				$media_id = $data_value['media_id'];
				$metaProperty_id = $property_id;
				$get_data = $this->datahelper->added_compactview_sku_from_bynder($product_sku, $media_id,$metaProperty_id);
				$this->_logger->info($get_data);
			}
		}

		return $this;
	}
}
