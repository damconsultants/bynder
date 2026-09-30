<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderDeleteDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderDeleteData::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderDeleteData::class
        );
    }
}
