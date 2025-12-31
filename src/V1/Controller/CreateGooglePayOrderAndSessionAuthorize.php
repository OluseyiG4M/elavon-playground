<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\ApiException;
use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

/**
 * Create Google Pay order and session with AUTHORIZATION only (do_capture = false)
 * This creates an authorization that can be captured later
 */
class CreateGooglePayOrderAndSessionAuthorize extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        try {
            // Create the order
            $order = $client->createOrder(
                $request->amount,
                $request->currency_code ?? 'GBP',
                0.0, // delivery price
                0.0, // tax amount
                $request->order_number,
                'Oluseyi Test',
                '1 Test Street',
                '',
                'Leeds',
                'LS1 1AA',
                'GBR', // ISO3 country code
                $request->email ?? 'oluseyi-test@example.com',
                '0123456789',
                [
                    [
                        'name' => 'Test Product',
                        'quantity' => 1,
                        'price' => $request->amount,
                    ],
                ]
            );

            // Create payment session with Google Pay enabled - AUTHORIZATION only
            $session = $client->createPaymentSessionWithGooglePay(
                $order->getHref(),
                $this->getEnvironment()->getVar('ELAVON_ACCOUNT_ID'),
                'http://localhost:8080',
                'Oluseyi Test',
                '1 Test Street',
                '',
                'Leeds',
                'LS1 1AA',
                'GBR',
                $request->email ?? 'oluseyi-test@example.com',
                '0123456789',
                false // do_capture = false - AUTHORIZATION ONLY
            );
        } catch (ApiException $e) {
            $this->setResponseJson([
                'error' => 'API Error',
                'details' => $e->getResponseBody()
            ]);
            return;
        } catch (\Exception $e) {
            $this->setResponseJson([
                'error' => 'Error creating order and session',
                'message' => $e->getMessage()
            ]);
            return;
        }

        $this->setResponseJson([
            'success' => true,
            'mode' => 'authorization',
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
