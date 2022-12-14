<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class MetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    protected function _construct()
    {
        $this->_init("DamConsultants\Bynder\Model\MetaProperty","DamConsultants\Bynder\Model\ResourceModel\MetaProperty");
    }
}
