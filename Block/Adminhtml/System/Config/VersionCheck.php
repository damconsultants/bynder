<?php

namespace DamConsultants\Bynder\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;

class VersionCheck extends Field
{
    /**
     * @var Curl
     */
    protected $curl;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * Construct
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('DamConsultants_Bynder::system/config/version_message.phtml');
    }

    /**
     * Constructor
     *
     * @param Curl $curl
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Backend\Block\Template\Context $context
     * @param ModuleListInterface $moduleList
     * @param array $data
     */
    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList;
        parent::__construct($context, $data);
    }

    /**
     * Get latest version
     *
     * @return mixed
     */
    public function getLatestVersion()
    {
        $repoUrl = 'https://api.github.com/repos/damconsultants/bynder/releases/latest';
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_USERAGENT, 'Magento2');
        $this->curl->get($repoUrl);
       
        $response = $this->curl->getBody();
        
        if ($response) {
            $data = json_decode($response, true);
            
            return isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : null;
        }
        return null;
    }

    /**
     * Render
     *
     * @param AbstractElement $element
     * @return mixed
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get current version
     *
     * @return mixed
     */
    public function getCurrentVersion()
    {
        $moduleInfo = $this->moduleList->getOne('DamConsultants_Bynder');
        return $moduleInfo['setup_version'] ?? 'N/A';
    }

    /**
     * Is update available
     *
     * @return mixed
     */
    public function isUpdateAvailable()
    {
        $latest = $this->getLatestVersion();
       
        $current = $this->getCurrentVersion();
        return $latest && $current && version_compare($current, $latest, '<');
    }

    /**
     * Get change log url
     *
     * @return mixed
     */
    public function getChangeLogUrl()
    {
        return 'https://github.com/damconsultants/bynder/releases';
    }

    /**
     * Get element html
     *
     * @param AbstractElement $element
     * @return mixed
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->toHtml();
    }
}
