<?php

namespace DamConsultants\Bynder\Controller\Index;

/**
 * Class Activate
 * @package DamConsultants\Bynder\Controller\Index
 */
class Activate extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \DamConsultants\Bynder\Helper\Data $helperData
    ) {

        $this->_helperData = $helperData;
        return parent::__construct($context);
    }
    
    public function execute()
    {
        $getlicenceKey = $this->_helperData->getLicenceKey();
        return $this->getResponse()->setBody($getlicenceKey);
    }

    protected function _isAllowed()
    {
        return true;
    }
}
