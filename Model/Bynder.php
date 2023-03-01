<?php

namespace DamConsultants\Bynder\Model;

class Bynder extends \Magento\Framework\Model\AbstractModel
{
    protected const CACHE_TAG = 'DamConsultants_Bynder';

    /**
     * @var $_cacheTag
     */
    protected $_cacheTag = 'DamConsultants_Bynder';

    /**
     * @var $_eventPrefix
     */
    protected $_eventPrefix = 'DamConsultants_Bynder';

    /**
     * Bynder
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(\DamConsultants\Bynder\Model\ResourceModel\Bynder::class);
    }
}
