<?php
namespace DamConsultants\Bynder\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use \Magento\Store\Model\StoreManagerInterface;
class GetSyncButton extends Field
{
    protected $_template = 'DamConsultants_Bynder::system/config/getsyncbutton.phtml';
    public function __construct(Context $context, StoreManagerInterface $storeManager, \Magento\Backend\Helper\Data $HelperBackend, array $data = [])
    {
        $this->_storeManager = $storeManager;
        $this->HelperBackend = $HelperBackend;
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
    /**
     * Return ajax url for custom button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('bynder/index/getsku');
    }

    /**
     * @throws LocalizedException
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'bt_id_2',
                'label' => __('Get Data'),
            ]
        );

        return $button->toHtml();
    }
}