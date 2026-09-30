<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderConfigSyncDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderConfigSyncData::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderConfigSyncData::class
        );
    }
}
