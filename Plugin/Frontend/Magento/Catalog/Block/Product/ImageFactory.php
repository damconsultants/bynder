<?php

declare(strict_types = 1);

namespace DamConsultants\Bynder\Plugin\Frontend\Magento\Catalog\Block\Product;

use Magento\Catalog\Model\View\Asset\ImageFactory as AssetImageFactory;
use Magento\Catalog\Block\Product\Image as ImageBlock;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\PlaceholderFactory;
use Magento\Framework\View\ConfigInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\ObjectManagerInterface;

class ImageFactory {

    /**
     * @var ConfigInterface
     */
    private $presentationConfig;

    /**
     * @var ParamsBuilder
     */
    private $imageParamsBuilder;

    /**
     * @var hoverImage
     */
    private $hoverImage;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var product
     */
    private $product;

    /**
     * @var product
     */
    public $_helper;

    public function __construct(
    ConfigInterface $presentationConfig, ObjectManagerInterface $objectManager, AssetImageFactory $viewAssetImageFactory, PlaceholderFactory $viewAssetPlaceholderFactory, ParamsBuilder $imageParamsBuilder, \Magento\Catalog\Model\Product $product
    ) {
        $this->presentationConfig = $presentationConfig;
        $this->objectManager = $objectManager;
        $this->viewAssetPlaceholderFactory = $viewAssetPlaceholderFactory;
        $this->viewAssetImageFactory = $viewAssetImageFactory;
        $this->imageParamsBuilder = $imageParamsBuilder;
        $this->product = $product;
    }

    public function aroundCreate(
    \Magento\Catalog\Block\Product\ImageFactory $subject, callable $proceed, $product, $imageId, $attributes = null
    ) {
        $viewImageConfig = $this->presentationConfig->getViewConfig()->getMediaAttributes(
                'Magento_Catalog', ImageHelper::MEDIA_TYPE_CONFIG_NODE, $imageId
        );

        $imageMiscParams = $this->imageParamsBuilder->build($viewImageConfig);
        $originalFilePath = $product->getData($imageMiscParams['image_type']);

        $product_id = $product->getId();

        $product_details = $this->objectManager->create('Magento\Catalog\Model\Product')->load($product_id);

        $use_bynder_cdn = $product_details->getData('use_bynder_cdn');

        if ($originalFilePath === null || $originalFilePath === 'no_selection') {
            $_imageAsset = $this->viewAssetPlaceholderFactory->create(
                    [
                        'type' => $imageMiscParams['image_type']
                    ]
            );
        } else {
            $_imageAsset = $this->viewAssetImageFactory->create(
                    [
                        'miscParams' => $imageMiscParams,
                        'filePath' => $this->hoverImage,
                    ]
            );
        }

        if ($originalFilePath === null || $originalFilePath === 'no_selection') {
            $imageAsset = $this->viewAssetPlaceholderFactory->create(
                    [
                        'type' => $imageMiscParams['image_type']
                    ]
            );
        } else {
            $imageAsset = $this->viewAssetImageFactory->create(
                    [
                        'miscParams' => $imageMiscParams,
                        'filePath' => $originalFilePath,
                    ]
            );
        }

        $attributes = $attributes === null ? [] : $attributes;


        if ($use_bynder_cdn == 1) {
            if (!empty($product_details->getData('bynder_multi_img'))) {
                $bynder_image = $product_details->getData('bynder_multi_img');
                $bynder_image = trim($bynder_image);
                $byder_image_array = explode("\n", $bynder_image);
                $cookie_array = array_filter($byder_image_array);
                $i = 1;
                foreach ($cookie_array as $values) {
                    if (!empty($values) && $i == 1) {
                        $values = trim($values);
                        $image_url = $values;
                        $i++;
                    }
                }
            } else {
                $image_url = $imageAsset->getUrl();
            }
        } else {
            $image_url = $imageAsset->getUrl();
        }

        $data = [
            'data' => [
                'template' => 'DamConsultants_Bynder::product/image_with_borders.phtml',
                'image_url' => $image_url,
                'width' => $imageMiscParams['image_width'],
                'height' => $imageMiscParams['image_height'],
                'label' => $product->getName(),
                'ratio' => $this->getRatio($imageMiscParams['image_width'], $imageMiscParams['image_height']),
                'custom_attributes' => $this->getStringCustomAttributes($attributes),
                'class' => $this->getClass($attributes),
                'product_id' => $product->getId()
            ],
        ];
        return $this->objectManager->create(ImageBlock::class, $data);
    }

    private function getLabel(Product $product, string $imageType): string {
        $label = $product->getData($imageType . '_' . 'label');
        if (empty($label)) {
            $label = $product->getName();
        }
        return (string) $label;
    }

    private function getRatio(int $width, int $height): float {
        if ($width && $height) {
            return $height / $width;
        }
        return 1.0;
    }

    private function getStringCustomAttributes(array $attributes): string {
        $result = [];
        foreach ($attributes as $name => $value) {
            if ($name != 'class') {
                $result[] = $name . '="' . $value . '"';
            }
        }
        return !empty($result) ? implode(' ', $result) : '';
    }

    private function getClass(array $attributes): string {
        return $attributes['class'] ?? 'product-image-photo';
    }

}
