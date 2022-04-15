<?php
namespace DamConsultants\Bynder\Setup\Patch\Data;

/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 *  DamConsultants_Bynder
 */
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;


class AddBynderAttribute implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EavSetupFactory */
    private $eavSetupFactory;

    /**
        * @param ModuleDataSetupInterface $moduleDataSetup
        * @param EavSetupFactory $eavSetupFactory
        */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
        
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        
    }

    /**
        * {@inheritdoc}
        */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_multi_img');
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_multi_img', [
            'group' => 'Product Details',
            'type' => 'text',
            'sort_order' => 200,
            'backend' => '',
            'frontend' => '',
            'label' => 'Only Bynder Multi Image ',
            'input' => 'textarea',
            'class' => '',
            'source' => '',
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => ''
        ]);

        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_document');
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_document', [
            'group' => 'Product Details',
            'type' => 'text',
            'backend' => '',
            'frontend' => '',
            'sort_order' => 210,
            'label' => 'Only Bynder Document',
            'input' => 'textarea',
            'class' => '',
            'source' => '',
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'apply_to' => ''
        ]);

        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_videos');
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_videos', [
            'group' => 'Product Details',
            'type' => 'text',
            'backend' => '',
            'frontend' => '',
            'sort_order' => 220,
            'label' => 'Only Bynder Videos',
            'input' => 'textarea',
            'class' => '',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'unique' => false,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'apply_to' => ''
        ]);

        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'use_bynder_both_image');
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'use_bynder_both_image', [
            'group' => 'Product Details',
            'type' => 'int',
            'sort_order' => 230,
            'backend' => '',
            'frontend' => '',
            'label' => 'Use Both Image',
            'input' => 'boolean',
            'class' => '',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'user_defined' => true,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => ''
            /* 'attribute_set_id' => '4' */
        ]);

        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_image_import');
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'bynder_image_import', [
            'group' => 'Product Details',
            'type' => 'int',
            'sort_order' => 240,
            'backend' => '',
            'frontend' => '',
            'label' => 'Bynder Image Import',
            'input' => 'boolean',
            'class' => '',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'user_defined' => true,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => ''
            /* 'attribute_set_id' => '4' */
        ]);

        $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'use_bynder_cdn');
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'use_bynder_cdn', [
            'group' => 'Product Details',
            'type' => 'int',
            'sort_order' => 250,
            'backend' => '',
            'frontend' => '',
            'label' => 'Use Only Bynder CDN',
            'input' => 'boolean',
            'class' => '',
            'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
            'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'user_defined' => true,
            'default' => '',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'unique' => false,
            'apply_to' => ''
            /* 'attribute_set_id' => '4' */
        ]);

    }

    /**
        * {@inheritdoc}
        */
    public static function getDependencies()
    {
        return [];
    }

    /**
        * {@inheritdoc}
        */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion()
    {
        return '2.0.1';
    }
}