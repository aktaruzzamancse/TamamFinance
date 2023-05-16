<?php
namespace Baytonia\TamamFinance\Observer;

use Magento\Framework\Event\ObserverInterface;

class ProcessShipment implements ObserverInterface
{
    const XML_CLIENT_ID = 'payment/tamam/client_id';
    const XML_CLIENT_SECRET = 'payment/tamam/client_secret';
    const XML_MERCHANT_ID = 'payment/tamam/merchant_id';
    const XML_PAYMENT_URL = 'payment/tamam/payment_url';

    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/aktar_debug_tannam.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $shipment = $observer->getEvent()->getShipment();

        $order = $shipment->getOrder();
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();

        $logger->info('paymentMethod '.$paymentMethod);

        if($paymentMethod == 'tamam'){
            // Token request 
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $payment_url = $this->scopeConfig->getValue(self::XML_PAYMENT_URL, $storeScope);
            $client_secret = $this->scopeConfig->getValue(self::XML_CLIENT_SECRET, $storeScope);
            
            $url = $payment_url.'/reportDelivery';
            $salt=$client_secret;
            $client_id=$this->scopeConfig->getValue(self::XML_CLIENT_ID, $storeScope);
            $merchant_id=$this->scopeConfig->getValue(self::XML_MERCHANT_ID, $storeScope);

            // $order_id="44444";
            // Set the data to send in the POST request
            $order_id = $order->getData('entity_id');
            $histories = $order->getStatusHistories();
            /** @var OrderStatusHistoryInterface $caseCreationComment */
            $latestHistoryComment = array_pop($histories);
            $transaction_id =  str_replace("Tamam transaction Id : ","",$latestHistoryComment->getComment());
            $delivery_date = date('d F Y, h:i:s ');
            $delivery_date =  date('y-m-d h:i:s', strtotime($delivery_date. ' + 5 days'));
            $data = array(
                'merchant'=> [
                    'merchant_id' => "$merchant_id",
                    "transaction_id"=> $transaction_id,
                    'order_id' => "$order_id"
                ],
                "delivery_date"=> "$delivery_date"
                
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
            // $transaction_id = $jsonData['response']['transaction_id']; 
            // $client_token = $jsonData['response']['client_token']; 
        }
        // your code for sms here
    }
}