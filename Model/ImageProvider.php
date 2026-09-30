<?php

namespace DamConsultants\Bynder\Model;

use Magento\Checkout\Model\Cart\ImageProvider as CoreImageProvider;
use Magento\Checkout\CustomerData\DefaultItem;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Checkout\CustomerData\ItemPoolInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ImageProvider extends CoreImageProvider
{
    /**
     * @var CartItemRepositoryInterface
     */
    protected $itemRepository;
    /**
     * @var ItemPoolInterface
     */
    protected $itemPool;
    /**
     * @var DefaultItem
     */
    protected $customerDataItem;
    /**
     * @var Image
     */
    private $imageHelper;
    /**
     * @var ItemResolverInterface
     */
    private $itemResolver;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * Constructor
     *
     * @param CartItemRepositoryInterface $itemRepository
     * @param ItemPoolInterface $itemPool
     * @param ProductRepositoryInterface $productRepository
     * @param DefaultItem|null $customerDataItem
     * @param Image|null $imageHelper
     * @param ItemResolverInterface|null $itemResolver
     */
    public function __construct(
        CartItemRepositoryInterface $itemRepository,
        ItemPoolInterface $itemPool,
        ProductRepositoryInterface $productRepository,
        ?DefaultItem $customerDataItem = null,
        ?Image $imageHelper = null,
        ?ItemResolverInterface $itemResolver = null
    ) {
        $this->itemRepository = $itemRepository;
        $this->itemPool = $itemPool;
        $this->productRepository = $productRepository;
        $this->customerDataItem = $customerDataItem ?: ObjectManager::getInstance()->get(DefaultItem::class);
        $this->imageHelper = $imageHelper ?: ObjectManager::getInstance()->get(Image::class);
        $this->itemResolver = $itemResolver ?: ObjectManager::getInstance()->get(ItemResolverInterface::class);
    }

    /**
     * Get images
     *
     * @param mixed $cartId
     * @return mixed
     */
    public function getImages($cartId)
    {
        $itemData = [];
        $items = $this->itemRepository->getList($cartId);

        foreach ($items as $cartItem) {
            $itemData[$cartItem->getItemId()] = $this->getProductImageData($cartItem);
        }

        return $itemData;
    }

    /**
     * Get product image data
     *
     * @param \Magento\Quote\Model\Quote\Item $cartItem
     * @return mixed
     */
    private function getProductImageData(\Magento\Quote\Model\Quote\Item $cartItem)
    {
        $imageHelper = $this->imageHelper->init(
            $this->itemResolver->getFinalProduct($cartItem),
            'mini_cart_product_thumbnail'
        );

        $imageUrl = $imageHelper->getUrl();

        $productId = $cartItem->getProduct()->getId();
        $product = $this->productRepository->getById($productId);
        $bynderImage = $product->getData('bynder_multi_img');

        if (!empty($bynderImage)) {
            $jsonData = json_decode($bynderImage, true);
            if (!empty($jsonData)) {
                foreach ($jsonData as $values) {
                    if (!empty($values['image_role']) && in_array('Thumbnail', $values['image_role'])) {
                        if (!empty($values['thum_url'])) {
                            $imageUrl = trim($values['thum_url']);
                            break;
                        }
                    }
                }
            }
        }

        return [
            'src' => $imageUrl,
            'alt' => $imageHelper->getLabel(),
            'width' => $imageHelper->getWidth(),
            'height' => $imageHelper->getHeight(),
        ];
    }
}
