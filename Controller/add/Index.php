<?php
/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 *  DamConsultants_Bynder
 */
namespace DamConsultants\Bynder\Controller\Add;
/**
 * Class Index
 *
 * @package DamConsultants\Bynder\Controller\Add
 */
class Index extends \Magento\Framework\App\Action\Action
{
    protected $resource;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->_resource = $resource->getConnection();
        return parent::__construct($context);
    }

    public function execute()
    {
        $img_data_post = $this->getRequest()->getPost("img_data");
        $doc_url_post = $this->getRequest()->getPost("doc_url");
        $video_url_post = $this->getRequest()->getPost("video_url");

        if ($this->getRequest()->isAjax()) {
            if ((isset($img_data_post) && !empty($img_data_post)) ||
                (isset($doc_url_post) && !empty($doc_url_post)) ||
                (isset($video_url_post) && !empty($video_url_post))) {
                $video_json = "";
                if (isset($video_url_post) && !empty($video_url_post)) {
                    $video_url = $video_url_post;
                    $video_json = json_encode($video_url);
                }
                $doc_json = "";
                if (isset($doc_url_post) && !empty($doc_url_post)) {
                    $doc_url = $doc_url_post;
                    $doc_json = json_encode($doc_url);
                }
                $images_json = "";
                if (isset($img_data_post) && !empty($img_data_post)) {
                    $img_data = $img_data_post;
                    $images_json = json_encode($img_data);
                }
                $tableName = $this->_resource->getTableName("bynder_data_product");
                $data=['images_json' => $images_json,
                        'doc_json'  => $doc_json,
                        'video_url' =>  $video_json];
                $this->_resource->insert($tableName, $data);
                $lastInsertId = $this->_resource->lastInsertId(); 
                return $this->getResponse()->setBody($lastInsertId);
                //print_r($lastInsertId);
                
            }
        }
        return $this->getResponse()->setBody(0);
        
    }
}
