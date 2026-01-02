<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\ApiException;
use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

class VoidGooglePayTransaction extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        error_log("VoidGooglePayTransaction - Incoming request: " . json_encode($request));

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );


        if (!isset($request->transaction_id)) {
            error_log("VoidGooglePayTransaction - Missing transaction_id");
            $this->setResponseJson([
                'success' => false,
                'error' => 'Missing required parameter',
                'error_details' => 'transaction_id is required'
            ]);
            return;
        }

        $transactionId = $request->transaction_id;
        $orderReference = $request->order_reference ?? null;

        error_log("VoidGooglePayTransaction - Voiding transaction: " . $transactionId);

        try {
            $voidTransaction = $client->voidTransaction(
                $transactionId,
                $orderReference
            );

            error_log("VoidGooglePayTransaction - Void successful: " . $voidTransaction->getId());

            $this->setResponseJson([
                'success' => true,
                'void_transaction' => $voidTransaction->jsonSerialize(),
                'void_transaction_id' => $voidTransaction->getId(),
                'void_transaction_state' => $voidTransaction->getState(),
                'parent_transaction_id' => $transactionId,
                'message' => 'Transaction voided successfully'
            ]);

        } catch (ApiException $e) {
            error_log("VoidGooglePayTransaction - API Exception: " . $e->getMessage());
            error_log("VoidGooglePayTransaction - API Response Body: " . print_r($e->getResponseBody(), true));

            $this->setResponseJson([
                'success' => false,
                'error' => 'Void failed',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'details' => $e->getResponseBody(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            error_log("VoidGooglePayTransaction - General Exception: " . $e->getMessage());
            error_log("VoidGooglePayTransaction - Trace: " . $e->getTraceAsString());

            $this->setResponseJson([
                'success' => false,
                'error' => 'Void Processing Error',
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
