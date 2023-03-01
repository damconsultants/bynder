<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class BynderSycDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderSycDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\BynderSycData::class,
            \DamConsultants\Bynder\Model\ResourceModel\BynderSycData::class
        );
    }
}
