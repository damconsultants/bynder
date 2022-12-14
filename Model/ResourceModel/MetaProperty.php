<?php

namespace DamConsultants\Bynder\Model\ResourceModel;

class MetaProperty extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('bynder_metaproperty', 'id');
    }
}
