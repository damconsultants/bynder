<?php
/**
 * the dam consultants Software.
 *
 * @category  the dam consultants
 * @package   DamConsultants_Bynder
 * @author    the dam consultants
 */

 
namespace DamConsultants\Bynder\Block\Adminhtml\Metaproperty;

class Index extends  \Magento\Backend\Block\Template
{

    protected $helperdata;
    protected $metaProperty;
    protected $metaPropertyCollectionFactory;
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \DamConsultants\Bynder\Helper\Data $helperdata,
        \DamConsultants\Bynder\Model\MetaPropertyFactory $metaProperty,
        \DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        array $data = []
    ) {
        $this->_helperdata = $helperdata;
        $this->_metaProperty = $metaProperty;
        $this->_metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getSubmitUrl()
    {
        return $this->getUrl("bynder/index/submit");
    }

    public function getMetaData()
    {
        $property_name = "";
        $metadata = $this->_helperdata->get_bynder_meta_properites();
        $collection = $this->_metaPropertyCollectionFactory->create();
        if (count($collection->getData()) !== 0) {
            $property_name = $collection->getData()[0]['property_name'];
        } else {
            $property_name = 0;
        }

        $data =  json_decode($metadata, true);
        if ($data['status'] == 1) {
            $response_data = array(
                'metadata' => $data['data'],
                'property_name' => $property_name,
            );
           
        } else {
            $response_data = array(
                'metadata' => 0,
                'property_name' => $property_name,
            );
        }
        return $response_data;
    }
}
