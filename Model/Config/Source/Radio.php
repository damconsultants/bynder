<?php
namespace DamConsultants\Bynder\Model\Config\Source;
class Radio implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * To option array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'mini', 'label' => __('Mini')],
            ['value' => 'webimage', 'label' => __('Web Image')],
            ['value' => 'thul', 'label' => __('Thumbnails')],
          ];
    }
}