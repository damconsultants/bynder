<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderMediaTable::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderMediaTable::class
        );
    }
}
