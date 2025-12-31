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
 * Get the status of a Google Pay transaction
 * Can be used to check authorization or capture status
 */
class GetGooglePayTransactionStatus extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        if (!isset($request->transaction_id)) {
            $this->setResponseJson([
                'error' => 'Missing transaction_id',
                'message' => 'You must provide a transaction_id'
            ]);
            return;
        }

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        try {
            $transaction = $client->getTransaction($request->transaction_id);

        } catch (ApiException $e) {
            $this->setResponseJson([
                'error' => 'API Error',
                'details' => $e->getResponseBody()
            ]);
            return;
        } catch (\Exception $e) {
            $this->setResponseJson([
                'error' => 'Error retrieving transaction',
                'message' => $e->getMessage()
            ]);
            return;
        }

        $this->setResponseJson([
            'success' => true,
            'transaction' => $transaction->jsonSerialize()
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
