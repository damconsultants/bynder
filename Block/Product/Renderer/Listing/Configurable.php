<?php

namespace DamConsultants\Bynder\Block\Product\Renderer\Listing;

use Magento\Catalog\Model\Product;
use Magento\Swatches\Model\Swatch;

class Configurable extends \Magento\Swatches\Block\Product\Renderer\Listing\Configurable
{
    /**
     * Override the `getVariationMedia` method.
     *
     * @param string $attributeCode
     * @param string $optionId
     * @return array
     */
    protected function getVariationMedia($attributeCode, $optionId)
    {
        $variationProduct = $this->swatchHelper->loadFirstVariationWithSwatchImage(
            $this->getProduct(),
            [$attributeCode => $optionId]
        );

        if (!$variationProduct) {
            $variationProduct = $this->swatchHelper->loadFirstVariationWithImage(
                $this->getProduct(),
                [$attributeCode => $optionId]
            );
        }

        $variationMediaArray = [];
        if (!$variationProduct) {
            return $variationMediaArray;
        }

        if ($this->getRequest()->getFullActionName() == 'catalog_category_view') {
            $bynderImage = $variationProduct->getBynderMultiImg();
            $use_bynder_both_image = $variationProduct->getUseBynderBothImage();
            $use_bynder_cdn = $variationProduct->getUseBynderCdn();
            if (($use_bynder_cdn == 1 || $use_bynder_both_image == 1) && $bynderImage) {
                return $this->getBynderSwatchMedia($bynderImage);
            }
        }

        return $this->getDefaultVariationMedia($variationProduct);
    }

    /**
     * Swatch media from the last Bynder image with the Swatch role, or [] when none
     *
     * @param string $bynderImageJson
     * @return array
     */
    private function getBynderSwatchMedia($bynderImageJson)
    {
        $variationMediaArray = [];
        $decodedBynderImages = json_decode($bynderImageJson, true);
        if (!is_array($decodedBynderImages)) {
            return $variationMediaArray;
        }
        foreach ($decodedBynderImages as $bynderImage) {
            if ($bynderImage['item_type'] != 'IMAGE' || !isset($bynderImage['image_role'])) {
                continue;
            }
            foreach ($bynderImage['image_role'] as $image_role) {
                if ($image_role == 'Swatch') {
                    $variationMediaArray = [
                        'value' => $bynderImage['thum_url'],
                        'thumb' => $bynderImage['thum_url']
                    ];
                }
            }
        }
        return $variationMediaArray;
    }

    /**
     * Standard Magento swatch media for the variation
     *
     * @param Product $variationProduct
     * @return array
     */
    private function getDefaultVariationMedia($variationProduct)
    {
        return [
            'value' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_IMAGE_NAME),
            'thumb' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_THUMBNAIL_NAME),
        ];
    }
}
