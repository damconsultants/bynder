<?php
namespace DamConsultants\Bynder\Block\Adminhtml\Catalog\Product\Form;

class Gallery extends \Magento\Backend\Block\Template
{
    /**
     * Block template.
     *
     * @var string
     */
    protected $_template = 'group/gallery.phtml';
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
    /**
     * Json.
     *
     * @return $this
     * @param array $attr
     */
    public function getJson($attr)
    {
        return json_encode($attr);
    }
}