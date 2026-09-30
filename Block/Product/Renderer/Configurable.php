<?php
namespace DamConsultants\Bynder\Block\Product\Renderer;

use Magento\Catalog\Model\Product;

class Configurable extends \Magento\Swatches\Block\Product\Renderer\Configurable
{
    /**
     * Get swatch product image
     *
     * @param Product $childProduct
     * @param mixed $imageType
     * @return mixed
     */
    protected function getSwatchProductImage(Product $childProduct, $imageType)
    {
        $bynderImage = $childProduct->getBynderMultiImg();
        $use_bynder_both_image = $childProduct->getUseBynderBothImage();
        $use_bynder_cdn = $childProduct->getUseBynderCdn();
        if ($this->getRequest()->getFullActionName() != 'catalog_product_view'
            || !($use_bynder_cdn == 1 || $use_bynder_both_image == 1)
            || !$bynderImage
        ) {
            return parent::getSwatchProductImage($childProduct, $imageType);
        }
        return $this->findBynderSwatchUrl($bynderImage);
    }

    /**
     * Get the thumb URL of the first Bynder image with the Swatch role, or null when none
     *
     * @param string $bynderImageJson
     * @return string|null
     */
    private function findBynderSwatchUrl($bynderImageJson)
    {
        $decodedBynderImages = json_decode($bynderImageJson, true);
        if (!is_array($decodedBynderImages)) {
            return null;
        }
        foreach ($decodedBynderImages as $bynderImage) {
            if ($bynderImage['item_type'] != 'IMAGE' || !isset($bynderImage['image_role'])) {
                continue;
            }
            foreach ($bynderImage['image_role'] as $image_role) {
                if ($image_role == 'Swatch') {
                    return $bynderImage['thum_url'];
                }
            }
        }
        return null;
    }
}
