<?php

namespace DamConsultants\Bynder\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    /**
     * @var $storeScope
     */
    protected $storeScope;

    /**
     * @var $productrepository
     */
    protected $productrepository;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var $by_redirecturl
     */
    public $by_redirecturl;

    /**
     * @var $bynderDomain
     */
    public $bynderDomain = "";

    /**
     * @var $permanent_token
     */
    public $permanent_token = "";
    
    public const BYNDER_DOMAIN = 'bynderconfig/bynder_credential/bynderdomain';
    public const PERMANENT_TOKEN = 'bynderconfig/bynder_credential/permanent_token';
    public const LICENCE_TOKEN = 'bynderconfig/bynder_credential/licenses_key';
    public const RADIO_BUTTON = 'byndeimageconfig/bynder_image/selectimage';
    public const PRODUCT_SKU_LIMIT = 'cronimageconfig/set_limit_product_sku/product_sku_limt';
    public const API_CALLED = 'https://trello.thedamconsultants.com/';

    /**
     * Data Helper
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productrepository
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Api\ProductRepositoryInterface $productrepository,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->productrepository = $productrepository;
        $this->filesystem = $filesystem;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeManager = $storeManager;
        $this->_curl = $curl;
        parent::__construct($context);
    }
    /**
     * Get Product Id
     *
     * @return $this
     * @param string $productId
     */
    public function getProductById($productId)
    {
        return $this->productrepository->getById($productId);
    }
    /**
     * Get Store Config
     *
     * @return $this
     * @param string $storePath
     * @param string $storeId
     */
    public function getStoreConfig($storePath, $storeId = null)
    {
        return $this->_scopeConfig->getValue($storePath, ScopeInterface::SCOPE_STORE, $storeId);
    }
    /**
     * Get Bynder Domain
     *
     * @return $this
     */
    public function getBynderDomain()
    {
        return (string) $this->getStoreConfig(self::BYNDER_DOMAIN);
    }
    /**
     * Get Permanent Token
     *
     * @return $this
     */
    public function getPermanentToken()
    {
        return (string) $this->getStoreConfig(self::PERMANENT_TOKEN);
    }
    /**
     * Get Licence Token
     *
     * @return $this
     */
    public function getLicenceToken()
    {
        return (string) $this->getStoreConfig(self::LICENCE_TOKEN);
    }
    /**
     * Bynde Image Config
     *
     * @return $this
     */
    public function byndeimageconfig()
    {
        return (string) $this->getStoreConfig(self::RADIO_BUTTON);
    }
    /**
     * Get Product Sku Limit Config
     *
     * @return $this
     */
    public function getProductSkuLimitConfig()
    {
        return (string) $this->getStoreConfig(self::PRODUCT_SKU_LIMIT);
    }
    /**
     * Get Bynder Dom
     *
     * @return $this
     */
    public function getBynderDom()
    {
        return (string) $this->getConfig(self::BYNDER_DOMAIN);
    }
    /**
     * Get Permanen Token
     *
     * @return $this
     */
    public function getPermanenToken()
    {
        return (string) $this->getConfig(self::PERMANENT_TOKEN);
    }
    /**
     * Get Load Credential
     *
     * @return $this
     */
    public function getLoadCredential()
    {
        
        $this->bynderDomain = $this->getBynderDom();
        $this->permanent_token = $this->getPermanenToken();
        $this->by_redirecturl = $this->getRedirecturl();
        if (!empty($this->bynderDomain) && !empty($this->permanent_token) && !empty($this->by_redirecturl)) {
            return 1;
        } else {
            return "Bynder authentication failed | Please check your credential";
        }
    }
    /**
     * Get Redirecturl
     *
     * @return $this
     */
    public function getRedirecturl()
    {
        return (string) $this->getbaseurl() . "bynder/redirecturl";
    }
    /**
     * Get baseurl
     *
     * @return $this
     */
    public function getbaseurl()
    {
        $url = $this->_storeManager->getStore()->getBaseUrl();
        return $url;
    }
    /**
     * Get Config
     *
     * @return $this
     * @param string $path
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    /**
     * Get CheckBynder
     *
     * @return $this
     */
    public function getCheckBynder()
    {
        $fields = [
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'check-bynder-license');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'check-bynder-license', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get DerivativesImage
     *
     * @return $this
     * @param array $bynder_auth
     */
    public function getDerivativesImage($bynder_auth)
    {

        $fields = [
            'bynder_domain' => $bynder_auth['bynderDomain'],
            'redirectUri' => $bynder_auth['redirectUri'],
            'permanent_token' => $bynder_auth['token'],
            'databaseId' => $bynder_auth['og_media_ids'],
            'daatasetType' => $bynder_auth['dataset_types'],
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'magento-derivatives');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'magento-derivatives', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get LicenceKey
     *
     * @return $this
     */
    public function getLicenceKey()
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'get-license-key');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'get-license-key', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderChangemetadataAssets
     *
     * @return $this
     * @param string $product_url
     * @param string $url_data
     */
    public function getBynderChangemetadataAssets($product_url, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'change-metadata-magento');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'change-metadata-magento', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderChangemetadataAssetsDoc
     *
     * @return $this
     * @param string $product_url
     * @param string $url_data
     */
    public function getBynderChangemetadataAssetsDoc($product_url, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'change-metadata-magento-doc');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'change-metadata-magento-doc', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderChangemetadataAssetsVideo
     *
     * @return $this
     * @param string $product_url
     * @param string $url_data
     */
    public function getBynderChangemetadataAssetsVideo($product_url, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'change-metadata-magento-video');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'change-metadata-magento-video', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderDataCmsPage
     *
     * @return $this
     * @param string $CMSPageURL
     * @param string $url_data
     */
    public function getBynderDataCmsPage($CMSPageURL, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'cmspage_url' => $CMSPageURL,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'change-metadata-magento-cms-page');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'change-metadata-magento-cms-page', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderMetaProperites
     *
     * @return $this
     */
    public function getBynderMetaProperites()
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'get-bynder-meta-properites');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'get-bynder-meta-properites', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get ImageSyncWithProperties
     *
     * @return $this
     * @param string $sku_id
     * @param string $property_id
     */
    public function getImageSyncWithProperties($sku_id, $property_id)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'property_id' => $property_id
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'bynder-skudetails');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'bynder-skudetails', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get DataRemoveForMagento
     *
     * @return $this
     * @param string $sku_id
     * @param string $media_Id
     * @param string $metaProperty_id
     */
    public function getDataRemoveForMagento($sku_id, $media_Id, $metaProperty_id)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'media_id' => $media_Id,
            'property_id' => $metaProperty_id
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'sku-data-remove-for-magento');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'sku-data-remove-for-magento', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get DataRemoveForMagento
     *
     * @return $this
     * @param string $sku_id
     * @param string $media_Id
     * @param string $metaProperty_id
     */
    public function getAddedCompactviewSkuFromBynder($sku_id, $media_Id, $metaProperty_id)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'media_id' => $media_Id,
            'property_id' => $metaProperty_id
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'added-compactview-sku-from-bynder');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);
        //set curl header
        $this->_curl->addHeader("Content-Type", "application/json");
        //post request with url and data
        $this->_curl->post(self::API_CALLED . 'added-compactview-sku-from-bynder', $jsonData);
        //read response
        $response = $this->_curl->getBody();
        return $response;
    }
}
