<?php

namespace DamConsultants\Bynder\Controller\Adminhtml\Index;

use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class Submit extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;

     /**
      * Submit.
      * @param \Magento\Backend\App\Action\Context $context
      * @param \DamConsultants\Bynder\Helper\Data $helperData
      * @param \DamConsultants\Bynder\Model\MetaPropertyFactory $metaProperty
      * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
      * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
      */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \DamConsultants\Bynder\Helper\Data $helperData,
        \DamConsultants\Bynder\Model\MetaPropertyFactory $metaProperty,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->_helperData = $helperData;
        $this->metaProperty = $metaProperty;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->resultPageFactory = $resultPageFactory;
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $metadata = $this->_helperData->getBynderMetaProperites();
            $data =  json_decode($metadata, true);
            $label = $this->getRequest()->getParam('select_meta_tag');
            $collection = $this->metaPropertyCollectionFactory->create();
            $meta = [];
            foreach ($collection as $metacollection) {
                $meta[] = $metacollection['property_name'];
                $id = $metacollection['id'];
            }
            if (in_array($label, $meta)) {
                $message = __('This MetaProperty Already Submited Please select Another Property...!');
                $this->messageManager->addSuccessMessage($message);
                $this->resultPageFactory->create();
                return $resultRedirect->setPath('bynder/index/metaproperty');
            } else {
                if ($label == $data['data'][$label]['name']) {
                    if (!empty($id)) {
                        $model = $this->metaProperty->create()->load($id);
                    } else {
                        $model = $this->metaProperty->create();
                    }
                    $model->setData('property_name', $data['data'][$label]['name']);
                    $model->setData('property_id', $data['data'][$label]['id']);
                    $model->save();
                    $message = __('Submited MetaProperty...!');
                    $this->messageManager->addSuccessMessage($message);
                    $this->resultPageFactory->create();
                    return $resultRedirect->setPath('bynder/index/metaproperty');
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('We can\'t submit your request, Please try again.'));
        }
    }
}
