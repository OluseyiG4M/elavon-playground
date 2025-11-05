<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\Controller;

use Gear4music\ElavonPlayground\V1\EPG\ApiException;
use Gear4music\ElavonPlayground\V1\EPG\Client;
use Gear4music\JAPI\AuthSpec\Insecure;
use Gear4music\JAPI\AuthSpecInterface;
use Gear4music\JAPI\ExtendedController;
use Gear4music\JAPI\RequestValidatorSpec\NoValidation;

class CreateGooglePayPaymentEpg extends ExtendedController
{
    public function dispatch(): void
    {
        $request = $this->getRequestJson();

        $client = new Client(
            $this->getEnvironment()->getVar('EPG_HOST'),
            $this->getEnvironment()->getVar('EPG_USERNAME'),
            $this->getEnvironment()->getVar('EPG_PASSWORD')
        );

        // Validate that we have the required Google Pay token
        if (!isset($request->payment_data->paymentMethodData->tokenizationData->token)) {
            $this->setResponseJson(['error' => 'Invalid Google Pay payment data: missing token']);
            return;
        }

        $paymentTokenJson = $request->payment_data->paymentMethodData->tokenizationData->token;

        // Extract billing information from Google Pay data if available
        $billingInfo = $request->payment_data->paymentMethodData->info ?? null;
        $billingAddress = $billingInfo->billingAddress ?? null;

        // Extract cardholder name
        $holderName = $billingInfo->cardDetails ?? null;
        $holderName = is_string($holderName) ? $holderName : null;

        // Use provided billing details or fall back to defaults
        $fullName = $billingAddress->name ?? 'Oluseyi Test';
        $street1 = $billingAddress->address1 ?? '1 Test Street';
        $street2 = $billingAddress->address2 ?? null;
        $city = $billingAddress->locality ?? 'Leeds';
        $region = $billingAddress->administrativeArea ?? 'West Yorkshire';
        $postalCode = $billingAddress->postalCode ?? 'LS1 1AA';
        $countryCode = $billingAddress->countryCode ?? 'GBR';
        $email = $request->email ?? 'test@example.com';
        $phone = $billingAddress->phoneNumber ?? null;

        $customFields = isset($request->custom_fields) ? (array)$request->custom_fields : null;

        try {
            $response = $client->createGooglePayPayment(
                $paymentTokenJson,
                $request->order_number,
                $holderName,
                $fullName,
                $street1,
                $street2,
                $city,
                $region,
                $postalCode,
                $countryCode,
                $email,
                $phone,
                $customFields
            );
        } catch (ApiException $e) {
            $this->setResponseJson([
                'error' => 'API Error',
                'details' => $e->getResponseBody()
            ]);
            return;
        } catch (\Exception $e) {
            $this->setResponseJson([
                'error' => 'Error creating Google Pay payment',
                'message' => $e->getMessage()
            ]);
            return;
        }

        $responseData = [
            'success' => true,
            'payment' => $response->jsonSerialize()
        ];

        if (method_exists($response, 'getId')) {
            $responseData['payment_id'] = $response->getId();
        }

        if (method_exists($response, 'getHref')) {
            $responseData['payment_href'] = $response->getHref();
        }

        $this->setResponseJson($responseData);
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
