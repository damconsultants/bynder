<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderTempDocDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderTempDocData::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderTempDocData::class
        );
    }
}
