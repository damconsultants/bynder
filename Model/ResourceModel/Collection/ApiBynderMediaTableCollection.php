<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class ApiBynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\ApiBynderMediaTable::class,
            \DamConsultants\Bynder\Model\ResourceModel\ApiBynderMediaTable::class
        );
    }
}
