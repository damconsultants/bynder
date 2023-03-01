<?php

namespace DamConsultants\Bynder\Block\Adminhtml\Metaproperty;

use DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class Index extends \Magento\Backend\Block\Template
{
    /**
     * @var \DamConsultants\Bynder\Helper\Data
     */
    protected $helperdata;

    /**
     * @var \DamConsultants\Bynder\Model\MetaPropertyFactory
     */
    protected $metaProperty;

    /**
     * @var \DamConsultants\Bynder\Model\ResourceModel\Collection\MetaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;

    /**
     * Metaproperty
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \DamConsultants\Bynder\Helper\Data $helperdata
     * @param \DamConsultants\Bynder\Model\MetaPropertyFactory $metaProperty
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param array $data
     */

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \DamConsultants\Bynder\Helper\Data $helperdata,
        \DamConsultants\Bynder\Model\MetaPropertyFactory $metaProperty,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        array $data = []
    ) {
        $this->_helperdata = $helperdata;
        $this->_metaProperty = $metaProperty;
        $this->_metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * SubmitUrl.
     *
     * @return $this
     */
    public function getSubmitUrl()
    {
        return $this->getUrl("bynder/index/submit");
    }
    /**
     * Get MetaData.
     *
     * @return $this
     */
    public function getMetaData()
    {
        $property_name = "";
        $metadata = $this->_helperdata->getBynderMetaProperites();
        $collection = $this->_metaPropertyCollectionFactory->create();
        if (count($collection->getData()) !== 0) {
            $property_name = $collection->getData()[0]['property_name'];
        } else {
            $property_name = 0;
        }

        $data =  json_decode($metadata, true);
        if ($data['status'] == 1) {
            $response_data = [
                'metadata' => $data['data'],
                'property_name' => $property_name,
            ];
           
        } else {
            $response_data = [
                'metadata' => 0,
                'property_name' => $property_name,
            ];
        }
        return $response_data;
    }
}
