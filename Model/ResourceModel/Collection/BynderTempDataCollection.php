<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderTempDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderTempData::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderTempData::class
        );
    }
}
