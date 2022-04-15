<?php

namespace DamConsultants\Bynder\Observer;

use Magento\Framework\Event\ObserverInterface;

class OptionObserver implements ObserverInterface
{

   public function execute(\Magento\Framework\Event\Observer $observer)
   {
     $observer->getBlock()->setTemplate('DamConsultants_Bynder::helper/gallery.phtml');
   }
}