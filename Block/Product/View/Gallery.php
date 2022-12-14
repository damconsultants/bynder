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

/**
 * Class Gallery
 *
 * @package DamConsultants\Bynder\Block\Product\View
 */

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
    public $request;

    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        Context $context,
        ArrayUtils $arrayUtils,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Registry $Registry,
        \Magento\Theme\Block\Html\Header\Logo $logo,
        ImagesConfigFactoryInterface $imagesConfigFactory = null,
        
        array $galleryImagesConfig = [],
        UrlBuilder $urlBuilder = null,
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
        $this->_logo = $logo;
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

    public function getSingleImage()
    {
        $product = $this->getProduct();
        $use_bynder_cdn = $product->getData('use_bynder_cdn');
        if ($use_bynder_cdn == 1) {
            $img_attr = $product->getData('bynder_multi_img');
            $bynder_image = trim($img_attr);
           
            if (!empty($bynder_image)) {
                $byder_image_array = explode(" \n", $bynder_image);
                $cookie_array = array_filter($byder_image_array);
                $i = 1;
                foreach ($cookie_array as $values) {
                    $values = trim($values);
                    if ($i == 1 && !empty($values)) {
                        return $values;
                        $i++;
                    }
                }
            }
        }
        return '0';
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

    /*
     * Dev_31-03-2021
     */

    public function getGalleryVideoJson()
    {
        $item_array = array();
        $product = $this->_registry->registry('product');
        $use_bynder_cdn = $product->getData('use_bynder_cdn');
        $use_bynder_both_image = $product->getData('use_bynder_both_image');
        if (!empty($product->getData('bynder_videos'))) {
            $bynder_videos = $product->getData('bynder_videos');
            $byder_videos_array = explode(" ", $bynder_videos);
            $cookie_array = array_filter($byder_videos_array);
            foreach ($cookie_array as $v) {
                $v = trim($v);
                if (!empty($v)) {
                    $thumb_img = "";
                    $thumb = explode("@@", $v);
                    if (isset($thumb[0]) && isset($thumb[1])) {
                        $thumb_img = $thumb[1];
                    }
                    if(empty($thumb_img)){
                        $thumb_img = $this->_logo->getLogoSrc();
                    }else{
                        $thumb_img;
                    }
                    $v = explode("?", $v);
                    $item_array[] = array(
                        "thumb" => $thumb_img,
                        "caption" => $this->getProduct()->getName(),
                        "src" => $v[0],
                        "type" => 'iframe'
                    );
                }
            }
        }
        //}
        return json_encode($item_array, true);
    }

    /**
     * Retrieve product images in JSON format
     *
     * @return string
     */
    public function getGalleryImagesJson()
    {
        $imagesItems = [];
        $product = $this->_registry->registry('product');
        $use_bynder_cdn = $product->getData('use_bynder_cdn');
        $use_bynder_both_image = $product->getData('use_bynder_both_image');

        if ($use_bynder_both_image == 1) { /*Both Image*/
            foreach ($this->getGalleryImages() as $image) {
                $imageItem = new DataObject([
                    'thumb' => $image->getData('small_image_url'),
                    'img' => $image->getData('medium_image_url'),
                    'full' => $image->getData('large_image_url'),
                    'caption' => ($image->getLabel() ?: $this->getProduct()->getName()),
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
                $imagesItems[] = $imageItem->toArray();
            }
            if (!empty($product->getData('bynder_multi_img'))) {
                $bynder_image = $product->getData('bynder_multi_img');
                $byder_image_array = explode(" ", $bynder_image);
                $cookie_array = array_filter($byder_image_array);
                foreach ($cookie_array as $values) {
                    $values = trim($values);
                    $imageItem = new DataObject([
                        'thumb' => $values,
                        'img' => $values,
                        'full' => $values,
                        'caption' => $product->getName(),
                        'position' => 1,
                        'isMain' => 1,
                        'type' => 'image',
                        'videoUrl' => null,
                    ]);
                    $imagesItems[] = $imageItem->toArray();
                }
            }
        } elseif ($use_bynder_cdn == 1) { /*CDN Image*/
            if (!empty($product->getData('bynder_multi_img'))) {
                $bynder_image = $product->getData('bynder_multi_img');
                $byder_image_array = explode(" ", $bynder_image);
                $cookie_array = array_filter($byder_image_array);
                foreach ($cookie_array as $values) {
                    $values = trim($values);
                    if (!empty($values)) {
                        $imageItem = new DataObject([
                            'thumb' => $values,
                            'img' => $values,
                            'full' => $values,
                            'caption' => $product->getName(),
                            'position' => 1,
                            'isMain' => 1,
                            'type' => 'image',
                            'videoUrl' => null,
                        ]);
                        $imagesItems[] = $imageItem->toArray();
                    }
                }
            } else {
                /* CDN link empty */
                foreach ($this->getGalleryImages() as $image) {
                    $imageItem = new DataObject([
                        'thumb' => $image->getData('small_image_url'),
                        'img' => $image->getData('medium_image_url'),
                        'full' => $image->getData('large_image_url'),
                        'caption' => ($image->getLabel() ?: $this->getProduct()->getName()),
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
                    $imagesItems[] = $imageItem->toArray();
                }
            }
        } else {
            foreach ($this->getGalleryImages() as $image) {
                $imageItem = new DataObject([
                    'thumb' => $image->getData('small_image_url'),
                    'img' => $image->getData('medium_image_url'),
                    'full' => $image->getData('large_image_url'),
                    'caption' => ($image->getLabel() ?: $this->getProduct()->getName()),
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
                $imagesItems[] = $imageItem->toArray();
            }
        }
        return json_encode($imagesItems);
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
