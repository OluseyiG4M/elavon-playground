<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\ApiException;
use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

class ProcessGooglePayDirect extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        // Log the incoming request for debugging
        error_log("ProcessGooglePayDirect - Incoming request: " . json_encode($request));

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        // Extract Google Pay token
        if (!isset($request->payment_data->paymentMethodData->tokenizationData->token)) {
            error_log("ProcessGooglePayDirect - Missing Google Pay token");
            $this->setResponseJson([
                'success' => false,
                'error' => 'Missing Google Pay token',
                'error_details' => 'payment_data.paymentMethodData.tokenizationData.token not found'
            ]);
            return;
        }

        $paymentToken = $request->payment_data->paymentMethodData->tokenizationData->token;
        error_log("ProcessGooglePayDirect - Payment token extracted: " . substr($paymentToken, 0, 50) . "...");

        try {
            // Convert payment_data to array recursively
            $paymentDataArray = json_decode(json_encode($request->payment_data), true);

            error_log("ProcessGooglePayDirect - Calling processGooglePayDirect with amount: " . $request->amount);

            $transaction = $client->processGooglePayDirect(
                $paymentToken,
                (float)$request->amount,
                $request->currency_code ?? 'GBP',
                $request->order_number,
                $request->email ?? 'customer@example.com',
                $paymentDataArray
            );

            error_log("ProcessGooglePayDirect - Transaction successful: " . $transaction->getId());

            $this->setResponseJson([
                'success' => true,
                'transaction' => $transaction->jsonSerialize(),
                'transaction_id' => $transaction->getId(),
                'transaction_state' => $transaction->getState(),
                'amount' => $request->amount,
                'shipping_option' => $request->shipping_option ?? 'standard-free',
                'shipping_address' => $request->shipping_address ?? null
            ]);

        } catch (ApiException $e) {
            error_log("ProcessGooglePayDirect - API Exception: " . $e->getMessage());
            error_log("ProcessGooglePayDirect - API Response Body: " . print_r($e->getResponseBody(), true));

            $this->setResponseJson([
                'success' => false,
                'error' => 'API Error',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'details' => $e->getResponseBody(),
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            error_log("ProcessGooglePayDirect - General Exception: " . $e->getMessage());
            error_log("ProcessGooglePayDirect - Trace: " . $e->getTraceAsString());

            $this->setResponseJson([
                'success' => false,
                'error' => 'Payment Processing Error',
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
