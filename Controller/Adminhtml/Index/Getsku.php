<?php

/**
 * the dam consultants Software.
 *
 * @category  the dam consultants
 * @package   DamConsultants_Bynder
 * @author    the dam consultants
 */

namespace DamConsultants\Bynder\Controller\Adminhtml\Index;

/**
 * Class Gsku
 * @package DamConsultants\Bynder\Controller\Index
 */
class Getsku extends \Magento\Backend\App\Action
{

    protected $resultPageFactory = false;

    /**
     * Edit constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Catalog\Api\ProductAttributeManagementInterface $productAttributeManagementInterface,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->attribute = $attribute;
        $this->collectionFactory = $collectionFactory;
        $this->resultJsonFactory = $jsonFactory;
        $this->productAttributeManagementInterface = $productAttributeManagementInterface;
    }

    public function execute()
    {

        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');
            return;
        }
        $attribute_value = $this->getRequest()->getParam('select_attribute');
        $product_sku = [];
        $sku = [];
        $id = [];
        $data = [];
        $res_array = array();
        $attribute = $this->collectionFactory->create();
        $productcollection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            /*->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)*/
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $productcollection->getSelect()->limit(50);
        foreach ($attribute as $value) {
            $id[] = $value['attribute_set_id'];
        }
        $array = array_unique($id);
        foreach ($array as $ids) {
            $productAttributes = $this->productAttributeManagementInterface->getAttributes($ids);

            foreach ($productAttributes as $atttr) {

                if ($atttr->getAttributeCode() == "bynder_multi_img") {
                    $image_id[] = $atttr->getAttributeSetId();
                } elseif ($atttr->getAttributeCode() == "bynder_videos") {

                    $video_id[] = $atttr->getAttributeSetId();
                } elseif ($atttr->getAttributeCode() == "bynder_document") {

                    $doc_id[] = $atttr->getAttributeSetId();
                }
            }
        }
        $array_merge = array_merge($image_id, $video_id);
        $final = array_merge($array_merge, $doc_id);
        $ids = array_unique($final);
        if (!empty($attribute_value)) {
            if ($attribute_value == "image") {
                $productcollection->addAttributeToFilter('attribute_set_id', $image_id)
                    ->addAttributeToFilter(
                        array(
                            array('attribute' => 'bynder_multi_img', 'null' => true)
                        )
                    );
                foreach ($productcollection as $product) {
                    $product_sku[] = $product->getSku();
                }
            } elseif ($attribute_value == "video") {

                $productcollection->addAttributeToFilter('attribute_set_id', $video_id)
                    ->addAttributeToFilter(
                        array(
                            array('attribute' => 'bynder_videos', 'null' => true)
                        )
                    );
                foreach ($productcollection as $product) {
                    $product_sku[] = $product->getSku();
                }
            } elseif ($attribute_value == "document") {

                $productcollection->addAttributeToFilter('attribute_set_id', $doc_id)
                    ->addAttributeToFilter(
                        array(
                            array('attribute' => 'bynder_document', 'null' => true)
                        )
                    );
                foreach ($productcollection as $product) {
                    $product_sku[] = $product->getSku();
                }
            }
        } else {

            $productcollection->addAttributeToFilter('attribute_set_id', $ids)
                ->addAttributeToFilter(
                    array(
                        array('attribute' => 'bynder_multi_img', 'null' => true),
                        array('attribute' => 'bynder_videos', 'null' => true),
                        array('attribute' => 'bynder_document', 'null' => true)
                    )
                );
            foreach ($productcollection as $product) {
                $product_sku[] = $product->getSku();
            }
        }
        $sku = array_unique($product_sku);
        if (count($sku) > 0) {
            $status = 1;
            $data_sku = $sku;
        } else {
            $status = 0;
            $data_sku = "There is not any empty Bynder Data in product";
        }
        $result = $this->resultJsonFactory->create();
        return $result->setData(['status' => $status, 'message' => $data_sku]);
    }
}
