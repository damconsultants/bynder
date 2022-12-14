<?php

/**
 * the dam consultants Software.
 *
 * @category  the dam consultants
 * @package   DamConsultants_Bynder
 * @author    the dam consultants
 */

namespace DamConsultants\Bynder\Controller\Adminhtml\Index;

class Grid extends \Magento\Backend\App\Action
{
    protected $resultPageFactory = false;

     /**
      * Edit constructor.
      * @param \Magento\Backend\App\Action\Context $context
      * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
      */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('Bynder Sync. Action Log')));

        return $resultPage;
    }
}
