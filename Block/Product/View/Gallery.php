<?php

/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  DamConsultants
 * @package   DamConsultants_Bynder
 *
 */

namespace DamConsultants\Bynder\Block\Product\View;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ImagesConfigFactoryInterface;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\Framework\Data\Collection;
use Magento\Framework\DataObject;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\ArrayUtils;

class Gallery extends \Magento\Catalog\Block\Product\View\Gallery
{
    /**
     * @var \Magento\Framework\Config\View
     */
    protected $configView;

    /**
     * @var EncoderInterface
     */
    protected $jsonEncoder;

    /**
     * @var array
     */
    private $galleryImagesConfig;

    /**
     * @var ImagesConfigFactoryInterface
     */
    private $galleryImagesConfigFactory;

    /**
     * @var UrlBuilder
     */
    private $imageUrlBuilder;
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    public $_request;
    /**
     * @var _registry
     */
    protected $_registry;

    /**
     * Gallery
     * @param \Magento\Framework\App\Request\Http $request
     * @param Context $context
     * @param ArrayUtils $arrayUtils
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Framework\Registry $Registry
     * @param ImagesConfigFactoryInterface $imagesConfigFactory
     * @param array $galleryImagesConfig
     * @param UrlBuilder $urlBuilder
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        Context $context,
        ArrayUtils $arrayUtils,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Registry $Registry,
        ?ImagesConfigFactoryInterface $imagesConfigFactory = null,
        array $galleryImagesConfig = [],
        ?UrlBuilder $urlBuilder = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $arrayUtils,
            $jsonEncoder,
            $data,
            $imagesConfigFactory,
            $galleryImagesConfig,
            $urlBuilder
        );
        $this->jsonEncoder = $jsonEncoder;
        $this->galleryImagesConfigFactory = $imagesConfigFactory ?: ObjectManager::getInstance()
            ->get(ImagesConfigFactoryInterface::class);
        $this->galleryImagesConfig = $galleryImagesConfig;
        $this->imageUrlBuilder = $urlBuilder ?? ObjectManager::getInstance()->get(UrlBuilder::class);
        $this->_registry = $Registry;
        $this->_request = $request;
    }

    /**
     * Retrieve collection of gallery images
     *
     * @return Collection
     */
    public function getGalleryImages()
    {
        $product = $this->getProduct();
        $images = $product->getMediaGalleryImages();
        if (!$images instanceof \Magento\Framework\Data\Collection) {
            return $images;
        }

        foreach ($images as $image) {
            $galleryImagesConfig = $this->getGalleryImagesConfig()->getItems();
            foreach ($galleryImagesConfig as $imageConfig) {
                $image->setData(
                    $imageConfig->getData('data_object_key'),
                    $this->imageUrlBuilder->getUrl($image->getFile(), $imageConfig['image_id'])
                );
            }
        }

        return $images;
    }

    /**
     * Return magnifier options
     *
     * @return string
     */
    public function getMagnifier()
    {
        return $this->jsonEncoder->encode($this->getVar('magnifier'));
    }

    /**
     * Return breakpoints options
     *
     * @return string
     */
    public function getBreakpoints()
    {
        return $this->jsonEncoder->encode($this->getVar('breakpoints'));
    }

    /**
     * Retrieve product images in JSON format
     *
     * @return string
     */
    public function getGalleryImagesJson()
    {
        $product = $this->_registry->registry('product');

        $use_bynder_cdn = $product->getData('use_bynder_cdn');
        $use_bynder_both_image = $product->getData('use_bynder_both_image');
        $imagesItems = [];
        if ($use_bynder_both_image == 1) { /*Both Image*/
            if (!empty($product->getData('bynder_multi_img'))) {
                [$imagesItems, $role_image] = $this->getBynderGalleryItems($product, 'image', false);
            }
            foreach ($this->getGalleryImages() as $image) {
                $caption = ($role_image == 1) ? 0 : ($image->getLabel() ?: $this->getProduct()->getName());
                $imagesItems[] = $this->buildGalleryItem($image, $caption);
            }
        } elseif ($use_bynder_cdn == 1) { /*CDN Image*/
            if (!empty($product->getData('bynder_multi_img'))) {
                [$imagesItems, $role_image] = $this->getBynderGalleryItems($product, 'Base', true);
            } else {
                /* CDN link empty */
                $imagesItems = $this->getDefaultGalleryItems();
            }
        } else {
            $imagesItems = $this->getDefaultGalleryItems();
        }
        return json_encode($imagesItems);
    }

    /**
     * Build gallery items from the product's Bynder media (sorted by is_order)
     *
     * Once an image with $mainRoleName is found, isMain stays 1 for the remaining items (existing behaviour).
     *
     * @param Product $product
     * @param string $mainRoleName role that marks an image as main ('image' or 'Base')
     * @param bool $positionFromOrder use is_order as position instead of index + 1
     * @return array [items, role_image]
     */
    private function getBynderGalleryItems($product, $mainRoleName, $positionFromOrder)
    {
        $imagesItems = [];
        $bynder_image = $product->getData('bynder_multi_img');
        $json_value = json_decode($bynder_image, true);
        if (is_array($json_value) && !empty($json_value)) {
            usort($json_value, function ($a, $b) {
                return $a['is_order'] <=> $b['is_order'];
            });
        } else {
            $json_value = [];
        }
        $role_image = 0;
        foreach ($json_value as $key => $values) {
            $image_values =  trim($values['thum_url']);
            if ($values['item_type'] == 'IMAGE' && isset($values['image_role'])) {
                foreach ($values['image_role'] as $image_role) {
                    if ($image_role == $mainRoleName) {
                        $role_image = 1;
                    }
                }
            }
            $imageItem = new DataObject([
                'thumb' => $image_values,
                'img' => $image_values,
                'full' => $image_values,
                'caption' => $this->getProduct()->getName(),
                'position' => $positionFromOrder ? $values['is_order'] : $key + 1,
                'isMain' => $role_image,
                'type' => ($values['item_type'] == 'IMAGE') ? 'image' : 'video',
                'videoUrl' => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                "src" => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                "type" => ($values['item_type'] == 'VIDEO') ? 'iframe' : 'image'
            ]);
            $imagesItems[] = $imageItem->toArray();
        }
        return [$imagesItems, $role_image];
    }

    /**
     * Build items from the standard Magento media gallery
     *
     * @return array
     */
    private function getDefaultGalleryItems()
    {
        $imagesItems = [];
        foreach ($this->getGalleryImages() as $image) {
            $caption = ($image->getLabel() ?: $this->getProduct()->getName());
            $imagesItems[] = $this->buildGalleryItem($image, $caption);
        }
        return $imagesItems;
    }

    /**
     * Build one item from a Magento gallery image
     *
     * @param DataObject $image
     * @param mixed $caption
     * @return array
     */
    private function buildGalleryItem($image, $caption)
    {
        $imageItem = new DataObject([
            'thumb' => $image->getData('small_image_url'),
            'img' => $image->getData('medium_image_url'),
            'full' => $image->getData('large_image_url'),
            'caption' => $caption,
            'position' => $image->getData('position'),
            'isMain' => $this->isMainImage($image),
            'type' => str_replace('external-', '', $image->getMediaType()),
            'videoUrl' => $image->getVideoUrl(),
        ]);
        foreach ($this->getGalleryImagesConfig()->getItems() as $imageConfig) {
            $imageItem->setData(
                $imageConfig->getData('json_object_key'),
                $image->getData($imageConfig->getData('data_object_key'))
            );
        }
        return $imageItem->toArray();
    }

    /**
     * Retrieve gallery url
     *
     * @param null|\Magento\Framework\DataObject $image
     * @return string
     */
    public function getGalleryUrl($image = null)
    {
        $params = ['id' => $this->getProduct()->getId()];
        if ($image) {
            $params['image'] = $image->getValueId();
        }
        return $this->getUrl('catalog/product/gallery', $params);
    }

    /**
     * Is product main image
     *
     * @param \Magento\Framework\DataObject $image
     * @return bool
     */
    public function isMainImage($image)
    {
        $product = $this->getProduct();
        return $product->getImage() == $image->getFile();
    }

    /**
     * Returns image attribute
     *
     * @param string $imageId
     * @param string $attributeName
     * @param string $default
     * @return string
     */
    public function getImageAttribute($imageId, $attributeName, $default = null)
    {
        $attributes = $this->getConfigView()
            ->getMediaAttributes('Magento_Catalog', Image::MEDIA_TYPE_CONFIG_NODE, $imageId);
        return $attributes[$attributeName] ?? $default;
    }

    /**
     * Retrieve config view
     *
     * @return \Magento\Framework\Config\View
     */
    private function getConfigView()
    {
        if (!$this->configView) {
            $this->configView = $this->_viewConfig->getViewConfig();
        }
        return $this->configView;
    }

    /**
     * Returns image gallery config object
     *
     * @return Collection
     */
    private function getGalleryImagesConfig()
    {
        if (false === $this->hasData('gallery_images_config')) {
            $galleryImageConfig = $this->galleryImagesConfigFactory->create($this->galleryImagesConfig);
            $this->setData('gallery_images_config', $galleryImageConfig);
        }

        return $this->getData('gallery_images_config');
    }
}
