<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

class GetGooglePayPaymentEpg extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        if (empty($request->payment_id)) {
            $this->setResponseJson(['error' => 'payment_id is required']);
            return;
        }

        try {
            $response = $client->getGooglePayPayment($request->payment_id);
        } catch (\Exception $e) {
            $this->setResponseJson(['error' => $e->getMessage()]);
            return;
        }

        $this->setResponseJson(['payment' => $response->jsonSerialize()]);
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