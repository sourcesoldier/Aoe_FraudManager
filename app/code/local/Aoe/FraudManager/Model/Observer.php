<?php

class Aoe_FraudManager_Model_Observer
{
    public function addOrderConditions(Varien_Event_Observer $observer)
    {
        /** @var Aoe_FraudManager_Helper_Data $helper */
        $helper = Mage::helper('Aoe_FraudManager/Data');

        /** @var Aoe_FraudManager_Model_Rule_Condition_Interface $parent */
        $parent = $observer->getParent();
        if (!$parent instanceof Aoe_FraudManager_Model_Rule_Condition_Interface) {
            return;
        }

        /** @var Varien_Object $container */
        $container = $observer->getContainer();
        if (!$container instanceof Varien_Object) {
            return;
        }

        $conditions = $container->getConditions();
        if (!is_array($conditions)) {
            $conditions = [];
        }

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_Attribute $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_Attribute');
        $conditionName = $helper->__($condition->getName());
        foreach ($condition->getAttributeOptions() as $attribute => $label) {
            $conditions[$conditionName][$condition->getType() . '|' . $attribute] = $label;
        }

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_BillingAddress_Attribute $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_BillingAddress_Attribute');
        $conditionName = $helper->__($condition->getName());
        foreach ($condition->getAttributeOptions() as $attribute => $label) {
            $conditions[$conditionName][$condition->getType() . '|' . $attribute] = $label;
        }

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_ShippingAddress_Attribute $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_ShippingAddress_Attribute');
        $conditionName = $helper->__($condition->getName());
        foreach ($condition->getAttributeOptions() as $attribute => $label) {
            $conditions[$conditionName][$condition->getType() . '|' . $attribute] = $label;
        }

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_Address_Compare $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_Address_Compare');
        $conditionName = $helper->__($condition->getName());
        foreach ($condition->getAttributeOptions() as $attribute => $label) {
            $conditions[$conditionName][$condition->getType() . '|' . $attribute] = $label;
        }

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_Item_Found $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_Item_Found');
        $conditions[$condition->getType()] = $helper->__($condition->getName());

        $container->setConditions($conditions);
    }

    public function addOrderItemConditions(Varien_Event_Observer $observer)
    {
        /** @var Aoe_FraudManager_Helper_Data $helper */
        $helper = Mage::helper('Aoe_FraudManager/Data');

        /** @var Aoe_FraudManager_Model_Rule_Condition_Interface $parent */
        $parent = $observer->getData('parent');
        if (!$parent instanceof Aoe_FraudManager_Model_Rule_Condition_Interface) {
            return;
        }

        /** @var Varien_Object $container */
        $container = $observer->getData('container');
        if (!$container instanceof Varien_Object) {
            return;
        }

        $conditions = $container->getData('conditions');
        if (!is_array($conditions)) {
            $conditions = [];
        }

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_Item_Combine $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_Item_Combine');
        $conditions[$condition->getType()] = $helper->__($condition->getName());

        /** @var Aoe_FraudManager_Model_Rule_Condition_Order_Item_Attribute $condition */
        $condition = Mage::getModel('Aoe_FraudManager/Rule_Condition_Order_Item_Attribute');
        $conditionName = $helper->__($condition->getName());
        foreach ($condition->getAttributeOptions() as $attribute => $label) {
            $conditions[$conditionName][$condition->getType() . '|' . $attribute] = $label;
        }

        $container->setData('conditions', $conditions);
    }

    public function checkQuoteSubmitBefore(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        if (!$order instanceof Mage_Sales_Model_Order) {
            return;
        }

        /** @var Aoe_FraudManager_Helper_BlacklistRule $helper */
        $helper = Mage::helper('Aoe_FraudManager/BlacklistRule');
        if (!$helper->isActive($order->getStoreId())) {
            return;
        }

        /** @var Aoe_FraudManager_Resource_BlacklistRule_Collection $rules */
        $rules = Mage::getSingleton('Aoe_FraudManager/BlacklistRule')
            ->getCollection()
            ->filterValidForOrder($order);

        $messages = [];
        $notifications = [];
        foreach ($rules as $rule) {
            /** @var Aoe_FraudManager_Model_BlacklistRule $rule */
            if ($rule->validate($order)) {
                $notification = sprintf('Preventing order due to rules check: %s / %s / %s', $rule->getName(), $order->getIncrementId(), $order->getQuoteId());
                $notifications[] = $notification;
                Mage::log($notification, Zend_Log::WARN, 'fraud.log');

                $message = $rule->getMessage();
                if (empty($message)) {
                    $message = $helper->getDefaultMessage($order->getStoreId());
                }

                $messages[] = $helper->__($helper->filterMessage($message, $order->getStoreId()));

                if ($rule->getStopProcessing()) {
                    break;
                }
            }
        }

        if (count($notifications)) {
            try {
                $helper->notify(implode("\n", $notifications), ['order' => $order], $order->getStoreId());
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        if (count($messages)) {
            throw new Mage_Payment_Model_Info_Exception(nl2br(implode("\n", $messages)));
        }
    }

    public function checkQuoteSubmitSuccess(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        if (!$order instanceof Mage_Sales_Model_Order) {
            return;
        }

        /** @var Aoe_FraudManager_Helper_HoldRule $helper */
        $helper = Mage::helper('Aoe_FraudManager/HoldRule');
        if (!$helper->isActive($order->getStoreId())) {
            return;
        }

        // Exit early if the order cannot be held
        if (!$order->canHold()) {
            return;
        }

        /** @var Aoe_FraudManager_Resource_HoldRule_Collection $rules */
        $rules = Mage::getSingleton('Aoe_FraudManager/HoldRule')
            ->getCollection()
            ->filterValidForOrder($order);

        $notifications = [];
        foreach ($rules as $rule) {
            /** @var Aoe_FraudManager_Model_HoldRule $rule */
            if ($rule->validate($order)) {
                $notification = sprintf('Holding order due to rules check: %s / %s / %s', $rule->getName(), $order->getIncrementId(), $order->getQuoteId());
                $notifications[] = $notification;
                Mage::log($notification, Zend_Log::INFO, 'fraud.log');

                if ($order->canHold()) {
                    $order->setHoldBeforeState($order->getState());
                    $order->setHoldBeforeStatus($order->getStatus());
                }

                $status = $rule->getStatus();
                /** @var Mage_Sales_Model_Order_Config $salesConfig */
                $salesConfig = Mage::getSingleton('sales/order_config');
                $allowedStatuses = $salesConfig->getStateStatuses(Mage_Sales_Model_Order::STATE_HOLDED, false);
                if (!in_array($status, $allowedStatuses)) {
                    $status = true;
                }

                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, $status, sprintf('Holding order due to rules check: %s', $rule->getName()));

                if ($rule->getStopProcessing()) {
                    break;
                }
            }
        }

        if (count($notifications)) {
            try {
                $helper->notify(implode("\n", $notifications), ['order' => $order], $order->getStoreId());
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $order->save();
    }
}
