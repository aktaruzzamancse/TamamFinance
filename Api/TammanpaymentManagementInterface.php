<?php

declare(strict_types=1);

namespace Baytonia\TamamFinance\Api;

interface TammanpaymentManagementInterface
{

    /**
     * POST for tammanpayment api
     * @param mixed $items
     * @param string $po_id
     * @param string $merchant_id
     * @param string $order_id
     * @param string $transaction_id
     * @param string $total_amount
     * @param string $total_vat_amount
     * @param string $po_created_at
     * @return string[]
     */
    public function postTammanpayment($items,$po_id,$merchant_id,$order_id,$transaction_id,$total_amount,$total_vat_amount,$po_created_at);

    /**
     * POST for postTammanpaymentStatusCallback api
     * @param string $transaction_id
     * @param string $status_code
     * @param string $message
     * @param string $order_id
     * @param string $message_ar
     * @param string $hold_period
     * @param string $order_status
     * @return string[]
     */
    public function postTammanpaymentStatusCallback($transaction_id,$status_code,$message,$order_id,$message_ar,$hold_period,$order_status);
}

