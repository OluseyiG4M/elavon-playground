<?php

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\ApiException;
use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

class CreateCardPaymentOrderAndSession extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        error_log("CreateCardPaymentOrderAndSession - Incoming request: " . json_encode($request));

        if (!isset($request->amount) || !isset($request->order_number)) {
            $this->setResponseJson([
                'success' => false,
                'error' => 'Missing required parameters',
                'error_details' => 'amount and order_number are required'
            ]);
            return;
        }

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );


        $amount = (float) $request->amount;
        $currencyCode = $request->currency_code ?? 'GBP';
        $orderNumber = $request->order_number;
        $email = $request->email ?? 'customer@example.com';
        $doCapture = $request->do_capture ?? true;

        $name = $request->name ?? 'Test Customer';
        $address1 = $request->address1 ?? '1 Test Street';
        $address2 = $request->address2 ?? '';
        $city = $request->city ?? 'Leeds';
        $postCode = $request->post_code ?? 'LS1 1AA';
        $countryCode = $request->country_code ?? 'GBR';
        $phone = $request->phone ?? '012345678001';

        try {
            error_log("CreateCardPaymentOrderAndSession - Creating order for amount: {$amount} {$currencyCode}");

            $order = $client->createOrder(
                $amount,
                $currencyCode,
                0.0,
                0.0,
                $orderNumber,
                $name,
                $address1,
                $address2,
                $city,
                $postCode,
                $countryCode,
                $email,
                $phone,
                [
                    [
                        'name' => 'Test Product',
                        'quantity' => 1,
                        'price' => $amount,
                    ],
                ]
            );

            error_log("CreateCardPaymentOrderAndSession - Order created: " . $order->getId());

            $session = $client->createPaymentSessionWithCard(
                $order->getHref(),
                $this->getEnvironment()->getVar('ELAVON_ACCOUNT_ID'),
                'http://localhost:8080',
                $name,
                $address1,
                $address2,
                $city,
                $postCode,
                $countryCode,
                $email,
                $phone,
                $doCapture
            );

            error_log("CreateCardPaymentOrderAndSession - Session created: " . $session->getId());

        } catch (ApiException $e) {
            error_log("CreateCardPaymentOrderAndSession - API Exception: " . $e->getMessage());
            error_log("CreateCardPaymentOrderAndSession - API Response Body: " . print_r($e->getResponseBody(), true));

            $this->setResponseJson([
                'success' => false,
                'error' => 'API Error',
                'error_message' => $e->getMessage(),
                'details' => $e->getResponseBody()
            ]);
            return;
        } catch (\Exception $e) {
            error_log("CreateCardPaymentOrderAndSession - General Exception: " . $e->getMessage());
            error_log("CreateCardPaymentOrderAndSession - Trace: " . $e->getTraceAsString());

            $this->setResponseJson([
                'success' => false,
                'error' => 'Error creating order and session',
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return;
        }

        $this->setResponseJson([
            'success' => true,
            'mode' => $doCapture ? 'capture' : 'authorization',
            'order' => $order->jsonSerialize(),
            'session' => $session->jsonSerialize()
        ]);
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