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
 * Class Psku
 * @package DamConsultants\Bynder\Controller\Index
 */
class Submit extends \Magento\Backend\App\Action
{

    protected $resultPageFactory = false;

     /**
      * Edit constructor.
      * @param \Magento\Backend\App\Action\Context $context
      * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
      */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \DamConsultants\Bynder\Helper\Data $helperData,
        \DamConsultants\Bynder\Model\MetaPropertyFactory $metaProperty,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->_helperData = $helperData;
        $this->metaProperty = $metaProperty;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try{
            $metadata = $this->_helperData->get_bynder_meta_properites();
            $data =  json_decode($metadata,true);
            $label = $this->getRequest()->getParam('select_meta_tag');
            $collection = $this->metaPropertyCollectionFactory->create();
            $meta = [];
            foreach($collection as $metacollection){
                $meta[] = $metacollection['property_name'];
                $id = $metacollection['id'];
            }
            if(in_array($label, $meta)){
                $message = __('This MetaProperty Already Submited Please select Another Property...!');
                $this->messageManager->addSuccessMessage($message);
                $this->resultPageFactory->create();
                return $resultRedirect->setPath('bynder/index/metaproperty');
            } else { 
                if($label == $data['data'][$label]['name']) 
                {   
                    if(!empty($id)){
                        $model = $this->metaProperty->create()->load($id);
                    } else {
                        $model = $this->metaProperty->create();
                    }
                    $model->setData('property_name',$data['data'][$label]['name']);
                    $model->setData('property_id',$data['data'][$label]['id']);
                    $model->save();
                    $message = __('Submited MetaProperty...!');
                    $this->messageManager->addSuccessMessage($message);
                    $this->resultPageFactory->create();
                    return $resultRedirect->setPath('bynder/index/metaproperty');
                }
            }
        } catch (\Exception $e){
            $this->messageManager->addException($e, __('We can\'t submit your request, Please try again.'));
        }
        
    }
}
