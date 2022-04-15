<?php

namespace DamConsultants\Bynder\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use \Magento\Store\Model\StoreManagerInterface;

class Button extends Field
{
    protected $_template = 'DamConsultants_Bynder::system/config/button.phtml';
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
        $originalData = $element->getOriginalData();
        $path = explode('/', $originalData['path']);
        $url = $this->_storeManager->getStore()->getBaseUrl();
        $this->addData(
            [
                'mp_active_url'      => $url . 'bynder/index/activate',
                'mp_module_html_id'  => implode('_', $path)
            ]
        );
        return $this->_toHtml();
    }
    public function getCustomUrl()
    {
        return $this->getUrl();
    }
    public function getButtonHtml()
    {
        $activeButton = $this->getLayout()
            ->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setData([
                'id'      => 'bynder_module_active',
                'label'   => __('Get License Key'),
                'onclick' => 'javascript:mageplazaModuleActive(); return false;',
            ]);
        return $activeButton->toHtml();
    }
}
