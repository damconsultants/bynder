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
namespace DamConsultants\Bynder\Controller\Update;
/**
 * Class Index
 *
 * @package DamConsultants\Bynder\Controller\Update
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
        $ck_id = $this->getRequest()->getPost("ck_id");
        if ($this->getRequest()->isAjax()) {
            if (isset($ck_id) && !empty($ck_id)) {
                $db_id = $ck_id;
                $tableName = $this->_resource->getTableName("bynder_data_product");
                $select = $this->_resource->select()
                    ->from(['c'=>$tableName], ['*'])
                    ->where('c.bynder_id=?', (int)$db_id);
                $result = $this->_resource->fetchRow($select);
                if ($result && is_array($result) == 1) {
                    /* Image */
                    if (isset($img_data_post) && !empty($img_data_post)) {
                        $img_data = $img_data_post;
                        if (isset($result["images_json"])
                            && !empty($result["images_json"])
                        ) {
                            $db_json = json_decode($result["images_json"]);
                            $img_data = array_merge($db_json, $img_data);
                        }
                        $images_json = json_encode($img_data);
                        $image = ['images_json'=>$images_json];
                        $where = ['bynder_id=?'=>(int)$db_id];
                        $this->_resource->update($tableName, $image, $where);
                    }
                    /* Video */
                    if (isset($video_url_post) && !empty($video_url_post)) {
                        $img_data = $video_url_post;
                        if (isset($result["video_url"])
                            && !empty($result["video_url"])
                        ) {
                            $db_json = json_decode($result["video_url"]);
                            $img_data = array_merge($db_json, $img_data);
                        }
                        $video_json = json_encode($img_data);
                        $video = ['video_url'=>$video_json];
                        $where = ['bynder_id=?'=>(int)$db_id];
                        $this->_resource->update($tableName, $video, $where);
                    }
                    /* Document */
                    if (isset($doc_url_post) && !empty($doc_url_post)) {
                        $img_data = $doc_url_post;
                        if (isset($result["doc_json"])
                            && !empty($result["doc_json"])
                        ) {
                            $db_json = json_decode($result["doc_json"]);
                            $img_data = array_merge($db_json, $img_data);
                        }
                        $doc_json = json_encode($img_data);
                        $doc = ['doc_json'=>$doc_json];
                        $where = ['bynder_id=?'=>(int)$db_id];
                        $this->_resource->update($tableName, $doc, $where);
                    }
                }
                return $this->getResponse()->setBody($db_id);
            }
        }
        return $this->getResponse()->setBody(0);
    }
}
