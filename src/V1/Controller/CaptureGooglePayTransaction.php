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
 * Capture a previously authorized Google Pay transaction
 * Requires the transaction_id from the authorization step
 */
class CaptureGooglePayTransaction extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        if (!isset($request->transaction_id)) {
            $this->setResponseJson([
                'error' => 'Missing transaction_id',
                'message' => 'You must provide the transaction_id from the authorization'
            ]);
            return;
        }

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        try {
            // Capture the transaction
            // We can optionally specify a different amount if partial capture is needed
            $capturedTransaction = $client->captureTransaction(
                $request->transaction_id,
                $request->amount ?? null,
                $request->currency_code ?? null
            );

        } catch (ApiException $e) {
            $this->setResponseJson([
                'error' => 'API Error',
                'details' => $e->getResponseBody()
            ]);
            return;
        } catch (\Exception $e) {
            $this->setResponseJson([
                'error' => 'Error capturing transaction',
                'message' => $e->getMessage()
            ]);
            return;
        }

        $this->setResponseJson([
            'success' => true,
            'mode' => 'capture',
            'transaction' => $capturedTransaction->jsonSerialize()
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
