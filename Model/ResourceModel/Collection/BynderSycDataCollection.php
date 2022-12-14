<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderSycDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    protected function _construct()
    {
        $this->_init("DamConsultants\Bynder\Model\BynderSycData","DamConsultants\Bynder\Model\ResourceModel\BynderSycData");
    }
}
