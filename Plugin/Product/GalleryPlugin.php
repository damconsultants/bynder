<?php

namespace DamConsultants\Bynder\Plugin\Product;

use Magento\Catalog\Block\Product\View\Gallery;
use Magento\Framework\DataObject;
use Magento\Framework\AuthorizationInterface;

class GalleryPlugin
{
    /**
     * @var AuthorizationInterface
     */
    protected $authorization;

    /**
     * Constructor
     *
     * @param AuthorizationInterface $authorization
     */
    public function __construct(AuthorizationInterface $authorization)
    {
        $this->authorization = $authorization;
    }

    /**
     * Modify the gallery JSON data
     *
     * @param Gallery $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetGalleryImagesJson(Gallery $subject, callable $proceed)
    {
        $product = $subject->getProduct();

        $useBynderCdn = $product->getData('use_bynder_cdn');
        $useBynderBothImage = $product->getData('use_bynder_both_image');
        $imagesItems = [];
        if (!$this->authorization->isAllowed('DamConsultants_BynderDemo::manage_product_attribute')) {
            if ($useBynderBothImage == 1) {
                $imagesItems = $this->getBynderGalleryItems($product, 'image');
            } elseif ($useBynderCdn == 1) {
                $imagesItems = $this->getBynderGalleryItems($product, 'Base');
            }
        }
        // Fallback to default gallery images if not using Bynder
        if (empty($imagesItems)) {
            $result = $proceed();

            // Decode existing gallery JSON data
            $existingImages = json_decode($result, true);
            if (!empty($existingImages)) {
                $imagesItems = $existingImages;
            }
        }

        return json_encode($imagesItems);
    }

    /**
     * Build gallery items from the product's Bynder media
     *
     * Once an image with $mainRoleName is found, isMain stays 1 for the remaining items (existing behaviour).
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $mainRoleName role that marks an image as main ('image' or 'Base')
     * @return array
     */
    private function getBynderGalleryItems($product, $mainRoleName)
    {
        $imagesItems = [];
        if (empty($product->getData('bynder_multi_img'))) {
            return $imagesItems;
        }

        $bynderImage = $product->getData('bynder_multi_img');
        $jsonValue = json_decode($bynderImage, true);
        $roleImage = 0;
        foreach ($jsonValue as $key => $values) {
            $imageValues = trim($values['thum_url']);

            if ($values['item_type'] == 'IMAGE' && isset($values['image_role'])) {
                foreach ($values['image_role'] as $imageRole) {
                    if ($imageRole == $mainRoleName) {
                        $roleImage = 1;
                    }
                }
            }

            $imageItem = new DataObject([
                'thumb' => $imageValues,
                'img' => $imageValues,
                'full' => $imageValues,
                'caption' => $product->getName(),
                'position' => $key + 1,
                'isMain' => $roleImage,
                'type' => ($values['item_type'] == 'IMAGE') ? 'image' : 'video',
                'videoUrl' => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                "src" => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                "type" => ($values['item_type'] == 'VIDEO') ? 'iframe' : 'image'
            ]);

            $imagesItems[] = $imageItem->toArray();
        }

        return $imagesItems;
    }
}
