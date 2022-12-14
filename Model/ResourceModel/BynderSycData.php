<?php

namespace DamConsultants\Bynder\Model\ResourceModel;

class BynderSycData extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('bynder_cron_data', 'id');
    }
}
