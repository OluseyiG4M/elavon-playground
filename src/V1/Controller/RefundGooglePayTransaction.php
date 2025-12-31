<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\ApiException;
use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

class RefundGooglePayTransaction extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        error_log("RefundGooglePayTransaction - Incoming request: " . json_encode($request));

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        if (!isset($request->transaction_id)) {
            error_log("RefundGooglePayTransaction - Missing transaction_id");
            $this->setResponseJson([
                'success' => false,
                'error' => 'Missing required parameter',
                'error_details' => 'transaction_id is required'
            ]);
            return;
        }

        if (!isset($request->amount)) {
            error_log("RefundGooglePayTransaction - Missing amount");
            $this->setResponseJson([
                'success' => false,
                'error' => 'Missing required parameter',
                'error_details' => 'amount is required'
            ]);
            return;
        }

        $transactionId = $request->transaction_id;
        $amount = (float)$request->amount;
        $currencyCode = $request->currency_code ?? 'GBP';
        $orderReference = $request->order_reference ?? null;
        $email = $request->email ?? null;

        error_log("RefundGooglePayTransaction - Refunding transaction: " . $transactionId . " for amount: " . $amount);

        try {
            $refundTransaction = $client->refundTransaction(
                $transactionId,
                $amount,
                $currencyCode,
                $orderReference,
                $email
            );

            error_log("RefundGooglePayTransaction - Refund successful: " . $refundTransaction->getId());

            $this->setResponseJson([
                'success' => true,
                'refund_transaction' => $refundTransaction->jsonSerialize(),
                'refund_transaction_id' => $refundTransaction->getId(),
                'refund_transaction_state' => $refundTransaction->getState(),
                'parent_transaction_id' => $transactionId,
                'refund_amount' => $amount,
                'currency_code' => $currencyCode,
                'message' => 'Transaction refunded successfully'
            ]);

        } catch (ApiException $e) {
            error_log("RefundGooglePayTransaction - API Exception: " . $e->getMessage());
            error_log("RefundGooglePayTransaction - API Response Body: " . print_r($e->getResponseBody(), true));

            $this->setResponseJson([
                'success' => false,
                'error' => 'Refund failed',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'details' => $e->getResponseBody(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            error_log("RefundGooglePayTransaction - General Exception: " . $e->getMessage());
            error_log("RefundGooglePayTransaction - Trace: " . $e->getTraceAsString());

            $this->setResponseJson([
                'success' => false,
                'error' => 'Refund Processing Error',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function buildAuthSpec(): AuthSpecInterface
    {
        return new Insecure();
    }

    #[\Override]
    public function buildRequestValidatorSpec()
    {
        return new NoValidation();
    }
}
