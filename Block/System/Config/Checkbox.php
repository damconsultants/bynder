<?php

namespace DamConsultants\Bynder\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use \Magento\Store\Model\StoreManagerInterface;

class Checkbox extends Field
{
    protected $_template = 'DamConsultants_Bynder::system/config/checkbox.phtml';
    const CONFIG_PATH = 'byndersyncdata/bynder_sync_data/checkbox';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }
    /**
     * Retrieve element HTML markup.
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setNamePrefix($element->getName())
            ->setHtmlId($element->getHtmlId());
        return $this->_toHtml();
    }
    
    public function getValues()
    {
        $values = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        foreach ($objectManager->create('DamConsultants\Bynder\Model\Config\Source\Checkbox')->toOptionArray() as $value) {
            $values[$value['value']] = $value['label'];
        }
        return $values;
    }
    /**
     * 
     * @param  $name 
     * @return boolean
     */
  /*  public function getIsChecked($name)
    {
        return in_array($name, $this->getCheckedValues());
    } */
    /**
     * 
     *get the checked value from config
     */
    /*public function getCheckedValues()
    {
        /*if (is_null($this->_values)) {
            $data = $this->getConfigData();
            if (isset($data[self::CONFIG_PATH])) {
                $data = $data[self::CONFIG_PATH];
            } else {
                $data = '';
            }
            $this->_values = explode(',', $data);
        }
        return 0;
    } */
}
