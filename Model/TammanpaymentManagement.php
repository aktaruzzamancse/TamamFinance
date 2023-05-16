<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Baytonia\TamamFinance\Model;

class TammanpaymentManagement implements \Baytonia\TamamFinance\Api\TammanpaymentManagementInterface
{
    protected $order;
    public function __construct(
    \Magento\Sales\Api\Data\OrderInterface $order
    )
    {
    $this->order = $order;
    }
    /**
     * {@inheritdoc}
     */
    public function postTammanpayment($items,$po_id,$merchant_id,$order_id,$transaction_id,$total_amount,$total_vat_amount,$po_created_at)
    {

        $order = $this->order->load($order_id);
        $histories = $order->getStatusHistories();
        /** @var OrderStatusHistoryInterface $caseCreationComment */
        $latestHistoryComment = array_pop($histories);
        if(empty($latestHistoryComment)){
            $response  =['massage' => "Please Provide correct information"]; 
            header("Content-Type: application/json; charset=utf-8");
            $this->response = json_encode($response);
            print_r($this->response,false);
            die();
        }
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/Tamman.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        //$logger->info('Debug Order '.json_encode($order->getData()));
        $storeMerchantId = "Baytonia";
        $currentOrderTransactionId = str_replace("Tamam transaction Id : ","",$latestHistoryComment->getComment());
        // $logger->info('Debug latestHistoryComment '.json_encode($latestHistoryComment->getData()));
        // return 'hello api POST return the $param ' . $po_id.' '.$merchant_id;
        if($storeMerchantId == $merchant_id && $currentOrderTransactionId == $transaction_id){
            $response = ['PO_id' => $po_id,
                     'merchant_id' => $merchant_id,
                     'order_id' => $order_id,
                     'transaction_id' => $transaction_id,
                     'accept' => true,
                     'accepted_at' => $latestHistoryComment->getCreatedAt()
                    ];
           
        }else {
            $response  =['massage' => "Please Provide correct information"];
        }
        header("Content-Type: application/json; charset=utf-8");
        $this->response = json_encode($response);
        print_r($this->response,false);
        die();
        
    }
    /**
     * {@inheritdoc}
     */
    public function postTammanpaymentStatusCallback($transaction_id,$status_code,$message,$order_id,$message_ar,$hold_period,$order_status)
    {


        $order = $this->order->load($order_id);
        $histories = $order->getStatusHistories();
        
        /** @var OrderStatusHistoryInterface $caseCreationComment */
        $latestHistoryComment = array_pop($histories);
        if(empty($latestHistoryComment)){
            $response  =['massage' => "Please Provide correct information"]; 
            header("Content-Type: application/json; charset=utf-8");
            $this->response = json_encode($response);
            print_r($this->response,false);
            die();
        }
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/Tamman.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        //$logger->info('Debug Order '.json_encode($order->getData()));
        $storeMerchantId = "Baytonia";
        $currentOrderTransactionId = str_replace("Tamam transaction Id : ","",$latestHistoryComment->getComment());
        // $logger->info('Debug latestHistoryComment '.json_encode($latestHistoryComment->getData()));
        // return 'hello api POST return the $param ' . $po_id.' '.$merchant_id;
        if($currentOrderTransactionId == $transaction_id){
            $response = ['merchant_id' => $storeMerchantId,
                        'order_id' => $order_id,
                        'transaction_id' => $transaction_id,
                        'accept' => true,
                        'accepted_at' => $latestHistoryComment->getCreatedAt()
                        ];
                
            }else {
                $response  =['massage' => "Please Provide correct information"];
            }
         if($status_code=='TM200'){
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
         }
         if($status_code=='TM318'){
            $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
         }     
        $comment = "Tamam Status Code : ".$status_code." Message ".$message;
        $order->addCommentToStatusHistory($comment);
        $order->save();
        header("Content-Type: application/json; charset=utf-8");
        $this->response = json_encode($response);
        print_r($this->response,false);
        die();
        
    }
}

    