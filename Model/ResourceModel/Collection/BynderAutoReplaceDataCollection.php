<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderAutoReplaceDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderAutoReplaceData::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderAutoReplaceData::class
        );
    }
}
