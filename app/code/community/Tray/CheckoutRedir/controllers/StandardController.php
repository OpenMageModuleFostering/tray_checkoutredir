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

class Tray_CheckoutRedir_StandardController extends Mage_Core_Controller_Front_Action 
{

    /**
     * Order instance
     */
    protected $_order;

    
    public function paymentAction()
    {             
       $this->loadLayout();
       $this->renderLayout();       
    }
    
    public function returnAction()
    {
       $this->loadLayout();
       $this->renderLayout();
    }
    
    public function paymentbackendAction() 
    {
        $this->loadLayout();
        $this->renderLayout();

        $hash = explode("/order/", $this->getRequest()->getOriginalRequest()->getRequestUri());
        $hashdecode = explode(":", Mage::getModel('core/encryption')->decrypt($hash[1]));

        $order = Mage::getModel('sales/order')
                ->getCollection()
                ->addFieldToFilter('increment_id', $hashdecode[0])
                ->addFieldToFilter('quote_id', $hashdecode[1])
                ->getFirstItem();

        if ($order) {
            $session = Mage::getSingleton('checkout/session');
            $session->setLastQuoteId($order->getData('quote_id'));
            $session->setLastOrderId($order->getData('entity_id'));
            $session->setLastSuccessQuoteId($order->getData('quote_id'));
            $session->setLastRealOrderId($order->getData('increment_id'));
            $session->setCheckoutRedirQuoteId($order->getData('quote_id'));
            $this->_redirect('checkoutredir/standard/payment/type/geral');
        } else {
            Mage::getSingleton('checkout/session')->addError('URL informada é inválida!');
            $this->_redirect('checkout/cart');
        }
    }

    public function errorAction()
    {
       $this->loadLayout();
       $this->renderLayout();
    }
    
    /**
     *  Get order
     *
     *  @return	  Mage_Sales_Model_Order
     */
    public function getOrder() {
        
        if ($this->_order == null) {
            
        }
        
        return $this->_order;
    }

    protected function _expireAjax() {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    /**
     * Get singleton with checkout standard order transaction information
     *
     * @return Tray_CheckoutRedir_Model_Api
     */
    public function getApi() 
    {
        return Mage::getSingleton('checkoutredir/'.$this->getRequest()->getParam("type"));
    }

    /**
     * When a customer chooses Tray on Checkout/Payment page
     *
     */
    public function redirectAction() 
    {
        
        $type = $this->getRequest()->getParam('type', false);
        
        $session = Mage::getSingleton('checkout/session');

        $session->setCheckoutRedirQuoteId($session->getQuoteId());
        
        $this->getResponse()->setHeader("Content-Type", "text/html; charset=ISO-8859-1", true);

        $this->getResponse()->setBody($this->getLayout()->createBlock('checkoutredir/redirect')->toHtml());

        $session->unsQuoteId();
    }

    /**
     * When a customer cancel payment from traycheckout .
     */
    public function cancelAction() 
    {
        
        $session = Mage::getSingleton('checkout/session');

        $session->setQuoteId($session->getCheckoutRedirQuoteId(true));

        // cancel order
        if ($session->getLastRealOrderId()) {

            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());

            if ($order->getId()) {
                $order->cancel()->save();
            }
        }

        $this->_redirect('checkout/cart');
    }
    
    private function getUrlPostCheckoutRedir($sandbox)
    {
         if ($sandbox == '1')
         {
        	return "http://api.sandbox.checkout.tray.com.br/api/v1/transactions/get_by_token";
         } else {
			return "http://api.checkout.tray.com.br/api/v1/transactions/get_by_token";
         }
    }
    
    /**
     * when checkout returns
     * The order information at this point is in POST
     * variables.  However, you don't want to "process" the order until you
     * get validation from the return post.
     */
    public function successAction() 
    {
        $_type = $this->getRequest()->getParam('type', false);
        $token = $this->getApi()->getConfigData('token');; 

		$urlPost = $this->getUrlPostCheckoutRedir($this->getApi()->getConfigData('sandbox'));

        $dados_post = $this->getRequest()->getPost();
         
        $order_number_conf = utf8_encode(str_replace($this->getApi()->getConfigData('prefixo'),'',$dados_post['transaction']['order_number']));
        $transaction_token= $dados_post['transaction']['transaction_token']; 

        ob_start(); 
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $urlPost); 
        curl_setopt($ch, CURLOPT_POST, 1); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, array("token"=>trim($transaction_token), "type_response"=>"J")); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array( "Expect:")); 
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_exec ($ch); 

        /* XML ou Json de retorno */ 
        $resposta = ob_get_contents(); 
        ob_end_clean(); 

        /* Capturando o http code para tratamento dos erros na requisi��o*/ 
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch); 
        $arrResponse = json_decode($resposta,TRUE);

        $xml = simplexml_load_string($resposta);
        if($httpCode != "200" ){
            $codigo_erro = $xml->codigo;
            $descricao_erro = $xml->descricao;
            if ($codigo_erro == ''){
                $codigo_erro = '0000000';
            }
            if ($descricao_erro == ''){
                $descricao_erro = 'Erro Desconhecido';
            }
            $this->_redirect('checkoutredir/standard/error', array('_secure' => true , 'descricao' => urlencode(utf8_encode($descricao_erro)),'codigo' => urlencode($codigo_erro)));
        }else{
        	
            $transaction = $arrResponse['data_response']['transaction'];
            $order_number = str_replace($this->getApi()->getConfigData('prefixo'),'',$transaction['order_number']);
        	if($order_number != $order_number_conf) {
        		$codigo_erro = '0000000';
                $descricao_erro = "Pedido: " . $order_number_conf . " não corresponte com a pedido consultado: ".$order_number."!";
                $this->_redirect('checkoutredir/standard/error', array('_secure' => true , 'descricao' => urlencode(utf8_encode($descricao_erro)),'codigo' => urlencode($codigo_erro)));
        	}
            
            if (isset($transaction['status_id'])) {
                $comment .= " " . $transaction['status_id'];
            }

            if (isset($transaction['status_name'])) {
                $comment .= " - " . $transaction['status_name'];
            }
            echo "Pedido: $order_number - $comment - ID: ".$dados_post['transaction']['transaction_id'];
            $order = Mage::getModel('sales/order');

            $order->loadByIncrementId($order_number);
            
            if ($order->getId()) {

                if ($transaction['price_original'] != $order->getGrandTotal()) {
                    
                    $frase = 'Total pago à Tray é diferente do valor original.';

                    $order->addStatusToHistory(
                            $order->getStatus(), //continue setting current order status
                            Mage::helper('checkoutredir')->__($frase), true
                    );

                    $order->sendOrderUpdateEmail(true, $frase);
                } else {
                    $cod_status = $transaction['status_id'];

                    switch ($cod_status){
                        case '4': 
                        case '5':
                        case '88':
                                $order->addStatusToHistory(
                                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage::helper('checkoutredir')->__('Tray enviou automaticamente o status: %s', $comment)
                                    
                                );
                            break;
                        case '6':
                                $items = $order->getAllItems();

                                $thereIsVirtual = false;

                                foreach ($items as $itemId => $item) {
                                    if ($item["is_virtual"] == "1" || $item["is_downloadable"] == "1") {
                                        $thereIsVirtual = true;
                                    }
                                }

                                // what to do - from admin
                                $toInvoice = $this->getApi()->getConfigData('acaopadraovirtual') == "1" ? true : false;

                                if ($thereIsVirtual && !$toInvoice) {

                                    $frase = 'Tray - Aprovado. Pagamento (fatura) confirmado automaticamente.';

                                    $order->addStatusToHistory(
                                            $order->getStatus(), //continue setting current order status
                                            Mage::helper('checkoutredir')->__($frase), true
                                    );

                                    $order->sendOrderUpdateEmail(true, $frase);
                                } else {
									
                                    if (!$order->canInvoice()) {
                                    	$isHolded = ( $order->getStatus() == Mage_Sales_Model_Order::STATE_HOLDED );

										$status = ($isHolded) ? Mage_Sales_Model_Order::STATE_PROCESSING :  $order->getStatus();
										$frase  = ($isHolded) ? 'Tray - Aprovado. Confirmado automaticamente o pagamento do pedido.' : 'Erro ao criar pagamento (fatura).';
										
                                        //when order cannot create invoice, need to have some logic to take care
                                        $order->addStatusToHistory(
                                            $status, //continue setting current order status
                                            Mage::helper('checkoutredir')->__( $frase )
                                        );

                                    } else {

	                                	//need to save transaction id
                                    	$order->getPayment()->setTransactionId($dados_post['transaction']['transaction_id']);
                                    
                                        //need to convert from order into invoice
                                        $invoice = $order->prepareInvoice();

                                        if ($this->getApi()->canCapture()) {
                                            $invoice->register()->capture();
                                        }

                                        Mage::getModel('core/resource_transaction')
                                                ->addObject($invoice)
                                                ->addObject($invoice->getOrder())
                                                ->save();

                                        $frase = 'Pagamento (fatura) ' . $invoice->getIncrementId() . ' foi criado. Tray - Aprovado. Confirmado automaticamente o pagamento do pedido.';

                                        if ($thereIsVirtual) {

                                            $order->addStatusToHistory(
                                                $order->getStatus(), Mage::helper('checkoutredir')->__($frase), true
                                            );

                                        } else {

                                            $order->addStatusToHistory(
                                                Mage_Sales_Model_Order::STATE_PROCESSING, //update order status to processing after creating an invoice
                                                Mage::helper('checkoutredir')->__($frase), true
                                            );
                                        }

                                        $invoice->sendEmail(true, $frase);
                                    }
                                }
                            break;
                        case '24':
                                $order->addStatusToHistory(
                                    Mage_Sales_Model_Order::STATE_HOLDED, Mage::helper('checkoutredir')->__('Tray enviou automaticamente o status: %s', $comment)
                                );
                            break;
                        case '7':
                        case '89':                        	
                                $frase = 'Tray - Cancelado. Pedido cancelado automaticamente (transação foi cancelada, pagamento foi negado, pagamento foi estornado ou ocorreu um chargeback).';

                                $order->addStatusToHistory(
                                    Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('checkoutredir')->__($frase), true
                                );

                                $order->sendOrderUpdateEmail(true, $frase);

                                $order->cancel();
                            break;
                        case '87':
                                $order->addStatusToHistory(
                                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, Mage::helper('checkoutredir')->__('Tray enviou automaticamente o status: %s', $comment)
                                );
                            break;
                    }
                }
                $order->save();
            }
        }
    }

}