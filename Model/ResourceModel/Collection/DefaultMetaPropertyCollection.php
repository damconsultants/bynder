<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class DefaultMetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\DefaultMetaProperty::class,
            \DamConsultants\Bynder\Model\ResourceModel\DefaultMetaProperty::class
        );
    }
}
