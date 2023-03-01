<?php

namespace DamConsultants\Bynder\Controller\Index;

class Activate extends \Magento\Framework\App\Action\Action
{
    /**
     * Activate
     * @param \Magento\Framework\App\Action\Context $context
     * @param \DamConsultants\Bynder\Helper\Data $helperData
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \DamConsultants\Bynder\Helper\Data $helperData
    ) {

        $this->_helperData = $helperData;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $getlicenceKey = $this->_helperData->getLicenceKey();
        return $this->getResponse()->setBody($getlicenceKey);
    }
    /**
     * Is Allowed
     *
     * @return $this
     */
    protected function _isAllowed()
    {
        return true;
    }
}
