<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class MetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\MetaProperty::class,
            \DamConsultants\Bynder\Model\ResourceModel\MetaProperty::class
        );
    }
}
