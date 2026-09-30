<?php
/**
 * DamConsultants
 *
 * DamConsultants_Bynder
 */

namespace DamConsultants\Bynder\Controller\BynderIndex;

use DamConsultants\Bynder\Helper\Data;
use Laminas\Uri\UriFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\MediaGallerySynchronizationApi\Api\SynchronizeInterface;

class Index extends Action
{
    /**
     * @var Data
     */
    protected $b_datahelper;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var DriverFile
     */
    protected $driverFile;

    /**
     * @var SynchronizeInterface
     */
    protected $synchronize;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param File $file
     * @param DriverFile $driverFile
     * @param Data $bynderData
     * @param SynchronizeInterface $synchronize
     * @param CurlFactory $curlFactory
     */
    public function __construct(
        Context $context,
        File $file,
        DriverFile $driverFile,
        Data $bynderData,
        SynchronizeInterface $synchronize,
        CurlFactory $curlFactory
    ) {
        $this->b_datahelper = $bynderData;
        $this->file = $file;
        $this->driverFile = $driverFile;
        $this->synchronize = $synchronize;
        $this->curlFactory = $curlFactory;
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $res_array = [
            "status"  => 0,
            "data"    => [],
            "message" => "Something went wrong. Please try again. | please logout and login again"
        ];

        $img_data_post = $this->getRequest()->getPost("img_data");
        $dir_path_post = $this->getRequest()->getPost("dir_path");

        if (!$this->getRequest()->isAjax()) {
            $res_array["message"] = "Invalid request.";
            return $this->getResponse()->setBody(json_encode($res_array));
        }

        if (!isset($img_data_post) || !is_array($img_data_post) || count($img_data_post) <= 0) {
            $res_array["message"] = "No images selected.";
            return $this->getResponse()->setBody(json_encode($res_array));
        }

        if (!isset($dir_path_post) || empty($dir_path_post)) {
            $res_array["message"] = "Directory path missing.";
            return $this->getResponse()->setBody(json_encode($res_array));
        }

        try {
            $img_dir = BP . '/pub/media/wysiwyg/' . trim($dir_path_post, '/');
            if (!$this->file->fileExists($img_dir, false)) {
                $this->file->mkdir($img_dir, 0755, true);
            }

            $syncPaths = [];
            foreach ($img_data_post as $item) {
                $item_url = trim($item);
                $item_url = str_replace(['?undefined', '&undefined'], '', $item_url);
                if (empty($item_url)) {
                    continue;
                }

                $basename = $this->getBasenameFromUrl($item_url);

                $download = $this->downloadFile($item_url);
                if ($download === null) {
                    continue;
                }

                $extension = $this->getExtensionFromContentType($download['content_type']);

                $fileInfo = $this->file->getPathInfo($basename);
                if (empty($fileInfo['extension'])) {
                    $basename .= '.' . $extension;
                }

                $file_name = strtolower($basename);
                $img_path = $img_dir . '/' . $file_name;

                $this->file->write($img_path, $download['body']);
                $this->driverFile->changePermissions($img_path, 0644);

                $syncPaths[] = str_replace(BP . '/pub/media/', '', $img_path);
            }

            if (!empty($syncPaths)) {
                $this->synchronize->execute($syncPaths);
            }

            $res_array["status"] = 1;
            $res_array["message"] = "Assets uploaded successfully.";
        } catch (\Exception $e) {
            $res_array["status"] = 0;
            $res_array["message"] = $e->getMessage();
        }

        return $this->getResponse()->setBody(json_encode($res_array));
    }

    /**
     * Build a safe file basename from a URL
     *
     * @param string $url
     * @return string
     */
    private function getBasenameFromUrl(string $url): string
    {
        $path = '';
        try {
            $path = (string) UriFactory::factory($url)->getPath();
        } catch (\Exception $e) {
            $path = '';
        }

        $fileInfo = $this->file->getPathInfo($path);
        $basename = $fileInfo['basename'] ?? '';

        if (empty($basename)) {
            $basename = uniqid() . '.jpg';
        }

        $basename = urldecode($basename);

        return preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $basename);
    }

    /**
     * Download a remote file using Magento's HTTP client
     *
     * @param string $url
     * @return array|null ['body' => string, 'content_type' => string] or null on failure
     */
    private function downloadFile(string $url): ?array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(60);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $curl->setOption(CURLOPT_MAXREDIRS, 5);
        $curl->setOption(CURLOPT_SSL_VERIFYPEER, true);

        try {
            $curl->get($url);
        } catch (\Exception $e) {
            return null;
        }

        $body = $curl->getBody();
        if ($curl->getStatus() != 200 || empty($body)) {
            return null;
        }

        $contentType = '';
        foreach ($curl->getHeaders() as $name => $value) {
            if (strtolower((string) $name) === 'content-type') {
                $contentType = is_array($value) ? (string) end($value) : (string) $value;
                break;
            }
        }

        return [
            'body'         => $body,
            'content_type' => strtolower($contentType),
        ];
    }

    /**
     * Map a Content-Type header to a file extension
     *
     * @param string $contentType
     * @return string
     */
    private function getExtensionFromContentType(string $contentType): string
    {
        if (strpos($contentType, 'png') !== false) {
            return 'png';
        }
        if (strpos($contentType, 'gif') !== false) {
            return 'gif';
        }
        if (strpos($contentType, 'webp') !== false) {
            return 'webp';
        }
        return 'jpg';
    }

    /**
     * Load Credential
     *
     * @return void
     */
    public function loadcredential()
    {
        $this->b_datahelper->getLoadCredential();
    }
}
