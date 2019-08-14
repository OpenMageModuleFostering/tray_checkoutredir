<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to suporte@tray.net.br so we can send you a copy immediately.
 *
 * @category   Tray
 * @package    Tray_CheckoutRedir
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Tray_CheckoutRedir_Model_Geral extends Tray_CheckoutRedir_Model_Standard
{
    protected $_code  = 'checkoutredir_geral';
    
    protected $_formBlockType = 'checkoutredir/form_geral';
    
    protected $_blockType = 'checkoutredir/geral';
    
    protected $_infoBlockType = 'checkoutredir/info_geral';
    
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('checkoutredir/standard/payment', array('_secure' => true, 'type' => 'geral'));
    }
}