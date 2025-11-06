<?php

declare(strict_types=1);

namespace Gear4music\ElavonPlayground\V1\EPG;

use Gear4music\ElavonPlayground\V1\EPG\ElavonPlayground\AccountsApi;
use Gear4music\ElavonPlayground\V1\EPG\ElavonPlayground\GooglePayPaymentsApi;
use Gear4music\ElavonPlayground\V1\EPG\ElavonPlayground\OrdersApi;
use Gear4music\ElavonPlayground\V1\EPG\ElavonPlayground\PaymentSessionsApi;
use Gear4music\ElavonPlayground\V1\EPG\ElavonPlayground\TransactionsApi;
use Gear4music\ElavonPlayground\V1\EPG\Model\Blik;
use Gear4music\ElavonPlayground\V1\EPG\Model\Contact;
use Gear4music\ElavonPlayground\V1\EPG\Model\FailureWrapper;
use Gear4music\ElavonPlayground\V1\EPG\Model\GooglePayPayment;
use Gear4music\ElavonPlayground\V1\EPG\Model\GooglePayPaymentInput;
use Gear4music\ElavonPlayground\V1\EPG\Model\HppType;
use Gear4music\ElavonPlayground\V1\EPG\Model\Order;
use Gear4music\ElavonPlayground\V1\EPG\Model\OrderInput;
use Gear4music\ElavonPlayground\V1\EPG\Model\OrderItem;
use Gear4music\ElavonPlayground\V1\EPG\Model\OrderItemType;
use Gear4music\ElavonPlayground\V1\EPG\Model\PaymentMethod;
use Gear4music\ElavonPlayground\V1\EPG\Model\PaymentMethodOrigin;
use Gear4music\ElavonPlayground\V1\EPG\Model\PaymentSession;
use Gear4music\ElavonPlayground\V1\EPG\Model\PaymentSessionInput;
use Gear4music\ElavonPlayground\V1\EPG\Model\PositiveAmountAndCurrency;
use Gear4music\ElavonPlayground\V1\EPG\Model\SaleTransaction;
use Gear4music\ElavonPlayground\V1\EPG\Model\ShopperInteraction;
use Gear4music\ElavonPlayground\V1\EPG\Model\Transaction;
use Gear4music\ElavonPlayground\V1\EPG\Model\TransactionType;
use GuzzleHttp\Promise\PromiseInterface;

class Client
{
    const ACCEPT_JSON = 'application/json';
    const API_VERSION = 1;

    private TransactionsApi $transactionsApi;
    private OrdersApi $ordersApi;
    private PaymentSessionsApi $paymentSessionsApi;
    private AccountsApi $accountsApi;
    private GooglePayPaymentsApi $googlePayPaymentsApi;

    private string $host;

    public function __construct(string $host, string $merchantID, string $secretKey)
    {
        $this->host = $host;
        $configuration = Configuration::getDefaultConfiguration()
            ->setUsername($merchantID)
            ->setPassword($secretKey)
            ->setHost($host);
        $this->transactionsApi = new TransactionsApi(
            new \GuzzleHttp\Client(),
            $configuration
        );
        $this->ordersApi = new OrdersApi(
            new \GuzzleHttp\Client(),
            $configuration
        );
        $this->paymentSessionsApi = new PaymentSessionsApi(
            new \GuzzleHttp\Client(),
            $configuration
        );
        $this->accountsApi = new AccountsApi(
            new \GuzzleHttp\Client(),
            $configuration
        );
        $this->googlePayPaymentsApi = new GooglePayPaymentsApi(
            new \GuzzleHttp\Client(),
            $configuration
        );
    }

    /**
     * @param float $amount
     * @param string $currencyCode
     * @param string $blikCode
     * @param string $orderNumber
     * @param string $email
     * @return Transaction|FailureWrapper
     * @throws \Exception
     */
    public function createBlikTransaction(
        float  $amount, // Amount as float
        string $currencyCode,
        string $blikCode, // 6 digit Blik code
        string $orderNumber,
        string $email // customer email
    ): Transaction|FailureWrapper
    {
        $transaction = new SaleTransaction([
            'type' => TransactionType::SALE,
            'total' => new PositiveAmountAndCurrency([
                'amount' => $amount,
                'currency_code' => $currencyCode,
            ]),
            'order_reference' => $orderNumber,
            'shopper_interaction' => ShopperInteraction::ECOMMERCE,
            'shopper_email_address' => $email,
            'blik' => new Blik([
                'code' => $blikCode,
            ]),
            'do_capture' => true,
            'do_send_receipt' => true // Send email receipt to customer automatically
        ]);
        // For some reason the generated code overwrites this in the constructor with an invalid default value
        // Set it again here
        $transaction->setType(TransactionType::SALE);

        return $this->transactionsApi->createTransaction(
            $transaction
        );
    }

    public function createBlikTransactionAsync(
        float  $amount,
        string $currencyCode,
        string $blikCode,
        string $orderNumber,
        string $email // customer email
    ): PromiseInterface
    {
        $transaction = new SaleTransaction([
            'type' => TransactionType::SALE,
            'total' => new PositiveAmountAndCurrency([
                'amount' => $amount,
                'currency_code' => $currencyCode,
            ]),
            'order_reference' => $orderNumber,
            'shopper_interaction' => ShopperInteraction::ECOMMERCE,
            'shopper_email_address' => $email,
            'blik' => new Blik([
                'code' => $blikCode,
            ]),
            'do_capture' => true,
            'do_send_receipt' => true
        ]);
        // For some reason the generated code overwrites this in the constructor with an invalid default value
        // Set it again here
        $transaction->setType(TransactionType::SALE);

        return $this->transactionsApi->createTransactionAsync(
            $transaction
        );
    }

    /**
     * @param string $transactionId
     * @return Transaction
     * @throws \Exception
     */
    public function getTransaction(string $transactionId): Transaction
    {
        $response = $this->transactionsApi->retrieveTransaction($transactionId);
        if ($response instanceof FailureWrapper) {
            throw new \Exception(
                sprintf(
                    "Error: Code: %s, Desc: %s",
                    $response->getFailures()[0]->getCode(),
                    $response->getFailures()[0]->getDescription(),
                ),
                $response->getStatus()
            );
        } else {
            return $response;
        }
    }


    /**
     * @throws ApiException
     * @throws \Exception
     */
    public function createOrder(
        float $amount,
        string $currencyCode, // ISO3 currency code
        float $deliveryPrice,
        float $taxAmount,
        string $orderNumber,
        string $name, // Address details to populate shipTo field. BillTo populated in the payment session request
        string $address1,
        string $address2,
        string $city,
        string $postCode,
        string $countryCode, // ISO3 country code! (differs from PMG's ISO2)
        string $email, // Customer email
        string $phone, // Customer phone number
        array $goodsData = [
            [
                'name' => 'Guitar',
                'quantity' => 1,
                'price' => 100.00,
            ],
            [
                'name' => 'Drumsticks',
                'quantity' => 2,
                'price' => 10.00,
            ],
        ]
    ): Order
    {
        $amount = new PositiveAmountAndCurrency([
            'amount' => $amount,
            'currency_code' => $currencyCode,
        ]);

        $items = [];
        foreach ($goodsData as $item) {
            $total = new PositiveAmountAndCurrency([
                'amount' => $item['quantity'] * $item['price'],
                'currency_code' => $currencyCode,
            ]);
            $unitPrice = new PositiveAmountAndCurrency([
                'amount' => $item['price'],
                'currency_code' => $currencyCode,
            ]);
            $items[] = new OrderItem([
                'total' => $total,
                'unit_price' => $unitPrice,
                'quantity' => $item['quantity'],
                'description' => $item['name'],
                'type' => OrderItemType::GOODS,
            ]);
        }

        $items[] = new OrderItem([
            'total' => new PositiveAmountAndCurrency([
                'amount' => $deliveryPrice,
                'currency_code' => $currencyCode,
            ]),
            'description' => 'Delivery',
            'type' => OrderItemType::SHIPPING,
        ]);

        $items[] = new OrderItem([
            'total' => new PositiveAmountAndCurrency([
                'amount' => $taxAmount,
                'currency_code' => $currencyCode,
            ]),
            'description' => 'Tax',
            'type' => OrderItemType::TAX,
        ]);

        $shipTo = new Contact([
            'full_name' => $name,
            'street1' => $address1,
            'street2' => $address2,
            'city' => $city,
            'postal_code' => $postCode,
            'country_code' => $countryCode,
            'email' => $email,
            'primary_phone' => $phone,
        ]);

        $orderInput = new OrderInput([
            'total' => $amount,
            'description' => 'Musical instruments and music equipment',
            'items' => $items,
            'ship_to' => $shipTo,
            'shopper_email_address' => $email,
            'order_reference' => $orderNumber,
        ]);

        $response = $this->ordersApi->createOrder(
            self::ACCEPT_JSON,
            self::API_VERSION,
            self::ACCEPT_JSON,
            $orderInput
        );
        if ($response instanceof FailureWrapper) {
            throw new \Exception(
                sprintf(
                    "Error: Code: %s, Desc: %s",
                    $response->getFailures()[0]->getCode(),
                    $response->getFailures()[0]->getDescription(),
                ),
                $response->getStatus()
            );
        } else {
            return $response;
        }
    }

    /**
     * @param string $orderHref
     * @param string $account
     * @param string $originUrl
     * @param string $name
     * @param string $address1
     * @param string $address2
     * @param string $city
     * @param string $postCode
     * @param string $countryCode
     * @param string $phone
     * @return PaymentSession
     * @throws ApiException
     * @throws \Exception
     */
    public function createPaymentSession(
        string $orderHref, // Order href returned by createOrder endpoint
        string $account, // Account ID
        string $originUrl, // Top level URL from which the payment session was initiated
        string $name, // Billing Address
        string $address1,
        string $address2,
        string $city,
        string $postCode,
        string $countryCode, // ISO3
        string $email,
        string $phone
    ): PaymentSession
    {
        $billTo = new Contact([
            'full_name' => $name,
            'street1' => $address1,
            'street2' => $address2,
            'city' => $city,
            'postal_code' => $postCode,
            'country_code' => $countryCode,
            'primary_phone' => $phone,
            'email' => $email,
        ]);

        $paymentSessionInput = new PaymentSessionInput([
            'order' => $orderHref,
            'account' => $this->host . '/accounts/' . $account, // build Elavon href for our account - will be needed for separate AV/G4M accounts
            'origin_url' => $originUrl,
            'do_create_transaction' => true,
            'bill_to' => $billTo,
            'allowed_payment_methods' => [PaymentMethod::CARD, PaymentMethod::BLIK], // For now in our actual implementation we can restrict this to Blik only
            'allowed_payment_method_origins' => [PaymentMethod::CARD, PaymentMethod::BLIK],
            'hpp_type' => HppType::LIGHTBOX,
            'shopper_email_address' => $email,
        ]);

        $response = $this->paymentSessionsApi->createPaymentSession(
            self::ACCEPT_JSON,
            self::API_VERSION,
            self::ACCEPT_JSON,
            $paymentSessionInput
        );
        if ($response instanceof FailureWrapper) {
            throw new \Exception(
                sprintf(
                    "Error: Code: %s, Desc: %s",
                    $response->getFailures()[0]->getCode(),
                    $response->getFailures()[0]->getDescription(),
                ),
                $response->getStatus()
            );
        } else {
            return $response;
        }
    }

    /**
     * @param string $sessionId
     * @return PaymentSession
     * @throws ApiException
     * @throws \Exception
     */
    public function getSession(string $sessionId): PaymentSession
    {
        $response = $this->paymentSessionsApi->retrievePaymentSession($sessionId);
        if ($response instanceof FailureWrapper) {
            throw new \Exception(
                sprintf(
                    "Error: Code: %s, Desc: %s",
                    $response->getFailures()[0]->getCode(),
                    $response->getFailures()[0]->getDescription(),
                ),
                $response->getStatus()
            );
        } else {
            return $response;
        }
    }

    /**
     * Create a payment session with Google Pay enabled (similar to Blik)
     *
     * @param string $orderHref Order href returned by createOrder endpoint
     * @param string $account Account ID
     * @param string $originUrl Top level URL from which the payment session was initiated
     * @param string $name Billing Address
     * @param string $address1
     * @param string $address2
     * @param string $city
     * @param string $postCode
     * @param string $countryCode ISO3
     * @param string $email
     * @param string $phone
     * @param bool $enableGooglePay Whether to enable Google Pay as a payment method
     * @return PaymentSession
     * @throws ApiException
     * @throws \Exception
     */
    public function createPaymentSessionWithGooglePay(
        string $orderHref,
        string $account,
        string $originUrl,
        string $name,
        string $address1,
        string $address2,
        string $city,
        string $postCode,
        string $countryCode,
        string $email,
        string $phone,
    ): PaymentSession
    {
        $billTo = new Contact([
            'full_name' => $name,
            'street1' => $address1,
            'street2' => $address2,
            'city' => $city,
            'postal_code' => $postCode,
            'country_code' => $countryCode,
            'primary_phone' => $phone,
            'email' => $email,
        ]);

        // Configure allowed payment methods
        $allowedPaymentMethods = [PaymentMethod::CARD];
        $allowedPaymentMethodOrigins = [PaymentMethodOrigin::GOOGLE_PAY];

        $paymentSessionInput = new PaymentSessionInput([
            'order' => $orderHref,
            'account' => $this->host . '/accounts/' . $account,
            'origin_url' => $originUrl,
            'do_create_transaction' => true,
            'bill_to' => $billTo,
            'allowed_payment_methods' => $allowedPaymentMethods,
            'allowed_payment_method_origins' => $allowedPaymentMethodOrigins,
            'hpp_type' => HppType::LIGHTBOX,
            'shopper_email_address' => $email,
        ]);

        $response = $this->paymentSessionsApi->createPaymentSession(
            self::ACCEPT_JSON,
            self::API_VERSION,
            self::ACCEPT_JSON,
            $paymentSessionInput
        );

        if ($response instanceof FailureWrapper) {
            throw new \Exception(
                sprintf(
                    "Error: Code: %s, Desc: %s",
                    $response->getFailures()[0]->getCode(),
                    $response->getFailures()[0]->getDescription(),
                ),
                $response->getStatus()
            );
        }

        return $response;
    }

    /**
     * Retrieve a Google Pay payment by ID
     *
     * @param string $paymentId The Google Pay payment ID
     * @return GooglePayPayment
     * @throws ApiException
     * @throws \Exception
     */
    public function getGooglePayPayment(string $paymentId): GooglePayPayment
    {
        $response = $this->googlePayPaymentsApi->retrieveGooglePayPayment(
            $paymentId,
            self::ACCEPT_JSON,
            self::API_VERSION
        );

        if ($response instanceof FailureWrapper) {
            throw new \Exception(
                sprintf(
                    "Error: Code: %s, Desc: %s",
                    $response->getFailures()[0]->getCode(),
                    $response->getFailures()[0]->getDescription(),
                ),
                $response->getStatus()
            );
        }

        return $response;
    }
}
