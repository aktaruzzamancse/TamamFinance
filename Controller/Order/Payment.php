<?php

namespace Baytonia\TamamFinance\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\RequestInterface;

class Payment extends Action
{
    const XML_CLIENT_ID = 'payment/tamam/client_id';
    const XML_CLIENT_SECRET = 'payment/tamam/client_secret';
    const XML_MERCHANT_ID = 'payment/tamam/merchant_id';
    const XML_PAYMENT_URL = 'payment/tamam/payment_url';

    protected $checkoutSession;
    protected $_request;
    protected $_storeManager;
    protected $_urlInterface;
    protected $quoteFactory;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        RequestInterface $request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
        )
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->_request = $request;
        $this->_storeManager = $storeManager;
        $this->_urlInterface = $urlInterface;
        $this->quoteFactory = $quoteFactory;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/aktar_debug_tannam.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $post = $this->_request->getPostValue();

        $national_id = $post['national_id'];
        $dateOfBirth = $post['my-date'];
        // $logger->info('national_id '.$post['national_id']);
        // $logger->info('my-date '.$post['my-date']);

        $currentOrder = $this->currentOrder();
        $logger->info('currentOrder '.print_r($currentOrder->getData(),true));
        $quoteId = $currentOrder->getData('quote_id');
        $getCurrentQuote =  $this->quoteFactory->create()->load($quoteId);
        $shipping = $getCurrentQuote->getShippingAddress();
        $logger->info('Quote Data '.print_r($getCurrentQuote->getShippingAddress()->getData(),true));
        $customer_address = $shipping->getData('street');
        $customer_name = $shipping->getData('firstname');
        $email = $shipping->getData('email');
        $total_amount =  number_format((float)$currentOrder->getData('grand_total'), 1, '.', '');
        $phone = $shipping->getData('telephone');
        $order_id = $currentOrder->getData('entity_id');
        $randorder = rand();
        // $order_id = "N9VKZWRV7UCP1WEQRV6VWGL3U4UWMKO1IAB8WSTTDV94IDK1ZFLLMXE7QYT498TG";
        $items = $getCurrentQuote->getAllVisibleItems();
        $orderItems = [];
        foreach($items as $item) 
        {

            $orderItems[]=[
                "item_display_name"=> $item->getName(),
                "sku"=> $item->getSku(),
                "unit_price"=> number_format((float)$item->getPrice(), 1, '.', ''),
                "quantity"=> $item->getQty(),
                "vat_amount"=> number_format((float)$item->getTaxAmount(), 1, '.', '')/$item->getQty(),
                "item_desc"=> $item->getName()  
            ];   
        }
        //shipping fees
        if($currentOrder->getData('base_shipping_incl_tax') > 0){
            $orderItems[]=[
                "item_display_name"=> "Shipping & Handling",
                "sku"=> "shipping_incl_tax",
                "unit_price"=> number_format((float)$currentOrder->getData('base_shipping_incl_tax'), 1, '.', ''),
                "quantity"=> "1",
                "vat_amount"=> number_format((float)$currentOrder->getData('base_shipping_tax_amount'), 1, '.', ''),
                "item_desc"=> "Shipping & Handling"  
            ];  
        }
        //extra fees

        // if($currentOrder->getData('amextrafee_fee_amount') > 0){
        //     $orderItems[]=[
        //         "item_display_name"=> "Extra Fee",
        //         "sku"=> "amextrafee_fee_amount",
        //         "unit_price"=> number_format((float)$currentOrder->getData('amextrafee_fee_amount'), 1, '.', ''),
        //         "quantity"=> "1",
        //         "vat_amount"=> number_format((float)$currentOrder->getData('amextrafee_tax_amount'), 1, '.', ''),
        //         "item_desc"=> "Extra Fee"  
        //     ];  
        // }

        $extra_fees = $total_amount - $currentOrder->getData('subtotal_incl_tax') - $currentOrder->getData('base_shipping_incl_tax');

        if($extra_fees > 0){
            $orderItems[]=[
                "item_display_name"=> "Extra Fee",
                "sku"=> "amextrafee_fee_amount",
                "unit_price"=> number_format((float)$extra_fees, 1, '.', ''),
                "quantity"=> "1",
                "vat_amount"=> "0",
                "item_desc"=> "Extra Fee"  
            ];  
        }

        $logger->info('getShippingAddress '.print_r($orderItems,true));
        // Token request 
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $payment_url = $this->scopeConfig->getValue(self::XML_PAYMENT_URL, $storeScope);
        $client_secret = $this->scopeConfig->getValue(self::XML_CLIENT_SECRET, $storeScope);

        $url = $payment_url.'/requestToken';
        $salt=$client_secret;
        $client_id=$this->scopeConfig->getValue(self::XML_CLIENT_ID, $storeScope);
        $merchant_id=$this->scopeConfig->getValue(self::XML_MERCHANT_ID, $storeScope);
        // $order_id="44444";
        // Set the data to send in the POST request
        $data = array(
            'merchant_id' => "$merchant_id",
            'order_id' => "$order_id"
        );
        
        //Convert to JSON
        $json_data = json_encode($data);
        
        //Generate the signiture 
        $hashed_sig=hash_hmac('sha256',$json_data,$salt,true);
        $encoded_sig=base64_encode($hashed_sig);
        
        //Generate the Headers
        $request_headers=array(
            "X-Foo-Signature: $encoded_sig",
            'X-FOO-Signature-Type: S2S',
            "X-CLIENT-ID: $client_id",
            'Content-Type: application/json'
        );
        // Initialize cURL
        $curl = curl_init();
        
        // Set the options for the cURL request
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        // Send the POST request and get the response
        $response = curl_exec($curl);
        
        //Debug Parameters
        // echo "Request URL: ".curl_getinfo($curl, CURLINFO_EFFECTIVE_URL)."\n";
        // echo "Request Header: ".json_encode($request_headers)."\n";
        // echo "Request Body: ".$json_data."\n\n\n";
        // echo "Response Details: ".$response."\n";
        // Close the cURL session
        curl_close($curl);
        $logger->info('json '.print_r($response,true));
        $jsonData = json_decode($response,true);
        $transaction_id = $jsonData['response']['transaction_id']; 
        $client_token = $jsonData['response']['client_token']; 
       
        // Int order
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $payment_url = $this->scopeConfig->getValue(self::XML_PAYMENT_URL, $storeScope);
        $client_secret = $this->scopeConfig->getValue(self::XML_CLIENT_SECRET, $storeScope);
        
        $url = $payment_url.'/initiateOrder';
        $salt=$client_secret;
        $client_id=$this->scopeConfig->getValue(self::XML_CLIENT_ID, $storeScope);
        $merchant_id=$this->scopeConfig->getValue(self::XML_MERCHANT_ID, $storeScope);
        //  $order_id="44444";
        // Set the data to send in the POST request
       // https://stage.baytonia.com/checkout/onepage/success?tamam_transaction_id=Baytonia22c9ff8d-1aea-43da-8787-c864b4a07056
        $redirection_url = $this->_urlInterface->getUrl('checkout/onepage/success?');
        $data = array(
            "app_token"=> "$client_token",
            "transaction_id" => "$transaction_id",
            "redirection_url"=> "$redirection_url",
            "merchant"=> array(
                "app_id"=> "",
                "merchant_id" => "$merchant_id",
            ),
            "customer"=> array(
                "customer_name"=> "$customer_name",
                "customer_address" => "$customer_address",
                "national_id"=> "$national_id",
                "email" => "$email",
                "phone"=> "$phone",
                "dob" => "$dateOfBirth",
            ),
            "order"=> array(
                "order_id"=> "$order_id",
                "reference" => "",
                "total_amount"=> "$total_amount",
                "items"=> $orderItems
                
            ),
        );
        
        //Convert to JSON
        $json_data = json_encode($data);
        $logger->info('json '.$json_data);
        //Generate the signiture 
        $hashed_sig=hash_hmac('sha256',$json_data,$salt,true);
        $encoded_sig=base64_encode($hashed_sig);
        
        //Generate the Headers
        $request_headers=array(
            "X-Foo-Signature: $encoded_sig",
            'X-FOO-Signature-Type: S2S',
            "X-CLIENT-ID: $client_id",
            'Content-Type: application/json'
        );
        // Initialize cURL
        $curl = curl_init();
        
        // Set the options for the cURL request
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        // Send the POST request and get the response
        $response = curl_exec($curl);
        
        //Debug Parameters
        // echo "Request URL: ".curl_getinfo($curl, CURLINFO_EFFECTIVE_URL)."\n";
        // echo "Request Header: ".json_encode($request_headers)."\n";
        // echo "Request Body: ".$json_data."\n\n\n";
        // echo "Response Details: ".$response."\n";
        // Close the cURL session
        curl_close($curl);


        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/aktar.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
    
        $logger->info('json '.print_r($response,true));

         $jsonData = json_decode($response,true);
     
         $redirection_url = $jsonData['response']['redirection_url']; 
         if($redirection_url){
            $order = $this->checkoutSession->getLastRealOrder();
            $comment = 'Tamam transaction Id : '.$jsonData['response']['transaction_id'];
            $order->addCommentToStatusHistory($comment);
            $order->save();
         }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)
            ->setData([
                'response' => $response
            ]);
    }

    protected function currentOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }
}
