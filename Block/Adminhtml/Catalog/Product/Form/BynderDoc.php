<?php
namespace DamConsultants\Bynder\Block\Adminhtml\Catalog\Product\Form;

class BynderDoc extends \Magento\Backend\Block\Template
{
    /**
     * Block template.
     *
     * @var string
     */
    protected $_template = 'group/bynderdoc.phtml';

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Get current product being edited
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    public function getCurrentProduct()
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Get media base URL of the current store
     *
     * @return string
     */
    public function getMediaBaseUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }
    /**
     * EntityId.
     *
     * @return $this
     */
    public function getEntityId()
    {
        return $this->getRequest()->getParam('id');
    }
    /**
     * Image.
     *
     * @return $this
     */
    public function getDrag()
    {
        return $this->getViewFileUrl('DamConsultants_Bynder::images/drag.png');
    }
    /**
     * Image.
     *
     * @return $this
     */
    public function getDelete()
    {
        return $this->getViewFileUrl('DamConsultants_Bynder::images/delete_.avif');
    }
}
