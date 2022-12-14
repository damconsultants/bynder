<?php

namespace DamConsultants\Bynder\Model\ResourceModel;

class Bynder extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('bynder_data_product', 'bynder_id');
    }
}
