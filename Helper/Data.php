<?php

namespace DamConsultants\Bynder\Helper;


use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{


    protected $storeScope;

    protected $productrepository;
    protected $filesystem;
    protected $_scopeConfig;
    public $by_redirecturl;
    
    public $bynderDomain = "";
    public $permanent_token = "";
    
    const BYNDER_DOMAIN = 'bynderconfig/bynder_credential/bynderdomain';
    const PERMANENT_TOKEN = 'bynderconfig/bynder_credential/permanent_token';
    const LICENCE_TOKEN = 'bynderconfig/bynder_credential/licenses_key';
    const RADIO_BUTTON = 'byndeimageconfig/bynder_image/selectimage';
    const PRODUCT_SKU_LIMIT = 'cronimageconfig/set_limit_product_sku/product_sku_limt';
    const API_CALLED = 'https://trello.thedamconsultants.com/';

    public function __construct(
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Api\ProductRepositoryInterface $productrepository,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->productrepository = $productrepository;
        $this->filesystem = $filesystem;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    public function upload_data($pid, $val, $multi_img, $i)
    {
        $res_array = array();
        $img_dir = BP . '/pub/media/temp/';
        if (!is_dir($img_dir)) {
            mkdir($img_dir, 0755, true);
        }
        $_product = $this->getProductById($pid);
        $sku = $_product->getSku();

        $tmpDir = $this->getMediaDirTmpDir();
        $d_item = $this->download_item($val, $img_dir);
        if ($d_item != 1 && !empty($d_item)) {
            if ($i == 1) {
                $img = $_product->addImageToMediaGallery($d_item, array('image', 'small_image', 'thumbnail'), false, false);
            } else {
                $img = $_product->addImageToMediaGallery($d_item, array(), false, false);
            }
            $this->productrepository->save($_product);
        }

        $this->unsetcookie();
        $this->unsetcookie();
        $res_array["echo"] = "<br>Helper : " . $pid . "<br>" . $val . "<br>" . $sku . "<br>" . $tmpDir . "<br>d_item:- " . $d_item;
        return $res_array;
    }

    public function download_item($bynder_img_url, $img_dir)
    {
        if (!empty($bynder_img_url)) {
            $url_ex = explode("/", $bynder_img_url);
            $img_name = end($url_ex);
            $url_components = parse_url($bynder_img_url);
            $orgi_link = $url_components['scheme'] . "://" . $url_components['host'] . "" . $url_components['path'] . "?dl=1";
            $url = file_get_contents($orgi_link);
            $bynder_download_img_file_path = $img_dir . $img_name;
            file_put_contents($bynder_download_img_file_path, $url);
            $this->unsetcookie();
            return $bynder_download_img_file_path;
        } else {
            return 1;
        }
    }

    public function unsetcookie()
    {

        $this->cookieManager->deleteCookie('fcookie');
        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
        $publicCookieMetadata->setDurationOneYear();
        $publicCookieMetadata->setPath('/');
        $publicCookieMetadata->setHttpOnly(false);

        return $this->cookieManager->setPublicCookie(
            'fcookie',
            null,
            $publicCookieMetadata
        );
    }

    public function getProductById($productId)
    {
        return $this->productrepository->getById($productId);
    }

    protected function getMediaDirTmpDir()
    {
        $mediaPath = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath('tmp/');
        return $mediaPath;
    }
    public function getStoreConfig($storePath, $storeId = null)
    {
        return $this->_scopeConfig->getValue($storePath, ScopeInterface::SCOPE_STORE, $storeId);
    }
   
    public function getBynderDomain()
    {
        return (string) $this->getStoreConfig(self::BYNDER_DOMAIN);
    }
    public function getPermanentToken()
    {
        return (string) $this->getStoreConfig(self::PERMANENT_TOKEN);
    }

    public function getLicenceToken()
    {
        return (string) $this->getStoreConfig(self::LICENCE_TOKEN);
    }

    public function byndeimageconfig()
    {
        return (string) $this->getStoreConfig(self::RADIO_BUTTON);
    }

    public function getProductSkuLimitConfig()
    {
        return (string) $this->getStoreConfig(self::PRODUCT_SKU_LIMIT);
    }

    
    public function BynderDomain()
    {
        return (string) $this->getConfig(self::BYNDER_DOMAIN);
    }
    public function PermanentToken()
    {
        return (string) $this->getConfig(self::PERMANENT_TOKEN);
    }

    public function getLoadCredential()
    {
        
        $this->bynderDomain = $this->BynderDomain();
        $this->permanent_token = $this->PermanentToken();
        $this->by_redirecturl = $this->redirecturl();
        if (!empty($this->bynderDomain) && !empty($this->permanent_token) && !empty($this->by_redirecturl)) {
            return 1;
        } else {
            return "Bynder authentication failed | Please check your credential";
        }
    }
    public function redirecturl()
    {
        return (string) $this->getbaseurl() . "bynder/redirecturl";
    }
    public function getbaseurl()
    {
        $url = $this->_storeManager->getStore()->getBaseUrl();
        return $url;
    }
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function check_bynder()
    {
        $curl = curl_init();

        $fields = array(
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken()
        );

        $fields = json_encode($fields);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'check-bynder-license',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function getDerivativesImage($bynder_auth)
    {

        $fields = array(
            
            'bynder_domain' => $bynder_auth['bynderDomain'],
            'redirectUri' => $bynder_auth['redirectUri'],
            'permanent_token' => $bynder_auth['token'],
            'databaseId' => $bynder_auth['og_media_ids'],
            'daatasetType' => $bynder_auth['dataset_types'],
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken()
        );
        $fields = json_encode($fields);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'magento-derivatives',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);


        curl_close($curl);
        return $response;
    }

    public function getLicenceKey()
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl()
        );
        $fields = json_encode($fields);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'get-license-key',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,

        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    public function get_bynder_changemetadata_assets($product_url, $url_data)
    {

        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        );
        $fields = json_encode($fields);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    public function get_bynder_changemetadata_assets_doc($product_url, $url_data)
    {

        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        );
        $fields = json_encode($fields);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento-doc',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function get_bynder_changemetadata_assets_video($product_url, $url_data)
    {

        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        );
        $fields = json_encode($fields);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento-video',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function bynder_data_cms_page($CMSPageURL, $url_data)
    {

        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'cmspage_url' => $CMSPageURL,
            'bynder_multi_img' => $url_data
        );
        $fields = json_encode($fields);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento-cms-page',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function bynder_changemetadata_delete_assets($product_url, $url_data)
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        );

       

        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento-delete-assets',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function bynder_changemetadata_delete_video_assets($product_url, $url_data)
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        );

        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento-delete-video-assets',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function bynder_changemetadata_delete_doc_assets($product_url, $url_data)
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        );

        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'change-metadata-magento-delete-doc-assets',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function get_bynder_meta_properites()
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken()
        );

        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'get-bynder-meta-properites',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function image_sync_with_properties($sku_id,$property_id)
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'property_id' => $property_id
        );
        
        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'bynder-skudetails',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function data_remove_for_magento($sku_id,$media_Id,$metaProperty_id)
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'media_id' => $media_Id,
            'property_id' => $metaProperty_id
        );
        
        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'sku-data-remove-for-magento',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function added_compactview_sku_from_bynder($sku_id,$media_Id,$metaProperty_id)
    {
        $fields = array(
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->BynderDomain(),
            'permanent_token' => $this->PermanentToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'media_id' => $media_Id,
            'property_id' => $metaProperty_id
        );
        
        $fields = json_encode($fields);


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::API_CALLED . 'added-compactview-sku-from-bynder',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
