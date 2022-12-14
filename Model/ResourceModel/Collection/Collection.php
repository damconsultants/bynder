<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    protected function _construct()
    {
        $this->_init("DamConsultants\Bynder\Model\Bynder","DamConsultants\Bynder\Model\ResourceModel\Bynder");
    }
}
