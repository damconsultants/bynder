<?php

namespace DamConsultants\Bynder\Model\ResourceModel\Collection;

class MagentoSkuCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * MagentoSkuCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Bynder\Model\MagentoSku::class,
            \DamConsultants\Bynder\Model\ResourceModel\MagentoSku::class
        );
    }
}
