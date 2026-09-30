<?php

namespace DamConsultants\Bynder\Plugin\Product\View\Type;

use Magento\Framework\Json\DecoderInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableBlock;

class Configurable
{
    /**
     * @var EncoderInterface
     */
    protected $jsonEncoder;
    /**
     * @var DecoderInterface
     */
    protected $jsonDecoder;
    /**
     * @var ProductHelper
     */
    protected $productHelper;
    /**
     * @var \Magento\ConfigurableProduct\Helper\Data
     */
    protected $helper;
    /**
     * @var \DamConsultants\Bynder\Helper\Data
     */
    protected $bynderhelper;

    /**
     * Constructor
     *
     * @param DecoderInterface $jsonDecoder
     * @param EncoderInterface $jsonEncoder
     * @param ProductHelper $productHelper
     * @param \Magento\ConfigurableProduct\Helper\Data $helper
     * @param \DamConsultants\Bynder\Helper\Data $bynderhelper
     */
    public function __construct(
        DecoderInterface $jsonDecoder,
        EncoderInterface $jsonEncoder,
        ProductHelper $productHelper,
        \Magento\ConfigurableProduct\Helper\Data $helper,
        \DamConsultants\Bynder\Helper\Data $bynderhelper
    ) {
        $this->jsonEncoder = $jsonEncoder;
        $this->jsonDecoder = $jsonDecoder;
        $this->helper = $helper;
        $this->productHelper = $productHelper;
        $this->bynderhelper = $bynderhelper;
    }

    /**
     * After get json config
     *
     * @param ConfigurableBlock $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetJsonConfig(ConfigurableBlock $subject, $result)
    {
        $result = $this->jsonDecoder->decode($result);
        $result['images'] = $this->getOptionImages($subject);
        $result['enable'] = $this->bynderhelper->byndeimageconfig();
        return $this->jsonEncoder->encode($result);
    }

    /**
     * Get option images
     *
     * @param ConfigurableBlock $subject
     * @return mixed
     */
    protected function getOptionImages(ConfigurableBlock $subject)
    {
        $images = [];
        foreach ($subject->getAllowProducts() as $product) {
            $use_bynder_both_image = $product->getUseBynderBothImage();
            $use_bynder_cdn = $product->getUseBynderCdn();

            if ($use_bynder_both_image == 1) {
                // Bynder images followed after gallery images
                $bynderImageData = $this->getBynderImageData($product);
                $galleryImages = $this->getGalleryImageData($product);
                $images[$product->getId()] = $this->appendImages($galleryImages, $bynderImageData);
            } elseif ($use_bynder_cdn == 1) {
                // Bynder images only
                $images[$product->getId()] = $this->getBynderImageData($product);
            } else {
                // Gallery images only
                $images[$product->getId()] = $this->getGalleryImageData($product);
            }
        }
        return $images;
    }

    /**
     * Build image data from the product's Bynder attribute
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getBynderImageData($product)
    {
        $bynderImageData = [];
        $bynderImages = $product->getData('bynder_multi_img');
        if (!$bynderImages) {
            return $bynderImageData;
        }

        $decodedBynderImages = json_decode($bynderImages, true);
        if (!is_array($decodedBynderImages)) {
            return $bynderImageData;
        }

        // Once a Base image is found, isMain stays true for the remaining items (existing behaviour)
        $role_image = false;
        foreach ($decodedBynderImages as $key => $bynderImage) {
            if ($this->isBaseImage($bynderImage)) {
                $role_image = true;
            }
            $bynderImageData[] = $this->buildBynderImageItem($bynderImage, $key, $role_image);
        }

        return $bynderImageData;
    }

    /**
     * Check whether a Bynder item is an image with the Base role
     *
     * @param array $bynderImage
     * @return bool
     */
    private function isBaseImage($bynderImage)
    {
        if ($bynderImage['item_type'] != 'IMAGE' || !isset($bynderImage['image_role'])) {
            return false;
        }

        $isBase = false;
        foreach ($bynderImage['image_role'] as $image_role) {
            if ($image_role == 'Base') {
                $isBase = true;
            }
        }

        return $isBase;
    }

    /**
     * Build a single gallery item from a Bynder record
     *
     * @param array $bynderImage
     * @param int|string $key
     * @param bool $role_image
     * @return array
     */
    private function buildBynderImageItem($bynderImage, $key, $role_image)
    {
        return [
            'thumb' => $bynderImage['thum_url'] ?? '',
            'img' => $bynderImage['item_url'] ?? '',
            'full' => $bynderImage['item_url'] ?? '',
            'caption' => $bynderImage['alt_text'] ?? '',
            'position' => $key + 1,
            'isMain' => $role_image,
            'type' => ($bynderImage['item_type'] == 'IMAGE') ? 'image' : 'video',
            'videoUrl' => ($bynderImage['item_type'] == 'VIDEO') ? $bynderImage['item_url'] : null,
            'src' => ($bynderImage['item_type'] == 'VIDEO') ? $bynderImage['item_url'] : null,
        ];
    }

    /**
     * Build image data from the product's Magento media gallery
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getGalleryImageData($product)
    {
        $productImages = $this->helper->getGalleryImages($product) ?: [];
        $galleryImages = [];
        foreach ($productImages as $image) {
            $galleryImages[] = [
                'thumb' => $image->getData('small_image_url'),
                'img' => $image->getData('medium_image_url'),
                'full' => $image->getData('large_image_url'),
                'caption' => $image->getLabel(),
                'position' => $image->getPosition(),
                'isMain' => $image->getFile() == $product->getImage(),
                'type' => $image->getMediaType() ? str_replace('external-', '', $image->getMediaType()) : '',
                'videoUrl' => $image->getVideoUrl(),
            ];
        }

        return $galleryImages;
    }

    /**
     * Append items of the second list to the first (same result as array_merge for list arrays)
     *
     * @param array $first
     * @param array $second
     * @return array
     */
    private function appendImages(array $first, array $second)
    {
        foreach ($second as $item) {
            $first[] = $item;
        }

        return $first;
    }
}
