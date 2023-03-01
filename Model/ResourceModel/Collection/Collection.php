<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Collection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\Bynder::class,
            \DamConsultants\Bynder\Model\ResourceModel\Bynder::class
        );
    }
}
