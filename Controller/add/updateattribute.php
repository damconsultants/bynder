<?php
/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 *  DamConsultants_Bynder
 */
namespace DamConsultants\Bynder\Controller\Add;
/**
 * Class Index
 *
 * @package DamConsultants\Bynder\Controller\Add
 */
class UpdateAttribute extends \Magento\Framework\App\Action\Action
{
    protected $resource;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Catalog\Model\Product $product,
        \DamConsultants\Bynder\Model\BynderFactory $bynder,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->_resource = $resource->getConnection();
        $this->collectionFactory = $collectionFactory;
        $this->product = $product;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->action = $action;
        $this->bynder = $bynder;
        return parent::__construct($context);
    }

    public function execute()
    {

        $storeId = $this->storeManagerInterface->getStore()->getId();

        $productcollection = $this->collectionFactory->create()
        ->addAttributeToSelect('*');
        
        foreach ($productcollection as $product) {
            $sku = $product->getSku();
            $product_ids[] = $this->product->getIdBySku($sku);
        }

        $updateAttributes['bynder_multi_img'] = Null;
        $img_json = Null;
        $this->action->updateAttributes($product_ids, $updateAttributes, $storeId);
        $model = $this->bynder->create();
        $data = ['images_json' => $img_json];
        $model->setData($data);
        if ($model->save()) {
            echo "<pre>";print_r("IN");die;
        }else{
            echo "<pre>";print_r("Out");die;
        }


       
    }
}
