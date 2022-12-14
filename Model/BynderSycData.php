<?php

namespace DamConsultants\Bynder\Model;

class BynderSycData extends \Magento\Framework\Model\AbstractModel
{
    const CACHE_TAG = 'DamConsultants_Bynder';

    protected $_cacheTag = 'DamConsultants_Bynder';

    protected $_eventPrefix = 'DamConsultants_Bynder';

    protected function _construct()
    {
        $this->_init("DamConsultants\Bynder\Model\ResourceModel\BynderSycData");
    }

}
