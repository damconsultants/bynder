<?php

namespace DamConsultants\Bynder\Ui\DataProvider\Product;

use DamConsultants\Bynder\Model\ResourceModel\Collection\BynderSycDataCollectionFactory;

class ProductDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{

    /**
     * @param BynderSycDataCollectionFactory $BynderSycDataCollectionFactory
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        BynderSycDataCollectionFactory $BynderSycDataCollectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $collection = $BynderSycDataCollectionFactory;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );
        return $this->collection = $BynderSycDataCollectionFactory->create();
    }
}
