<?php

/**
 * @author Lee Saferite <lee.saferite@aoe.com>
 * @since  2014-11-06
 */
class Aoe_FraudManager_Model_HoldRule extends Aoe_FraudManager_Model_Rule_Abstract
{
    protected function _construct()
    {
        $this->_setResourceModel('Aoe_FraudManager/HoldRule', 'Aoe_FraudManager/HoldRule_Collection');
    }
}