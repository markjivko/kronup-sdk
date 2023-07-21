<?php
/**Account
 * Invitation Test
 *
 * @copyright (c) 2022-2023 kronup.io
 * @license   MIT
 * @package   Kronup
 * @author    Mark Jivko
 */

namespace Kronup\Test\Local\Api;
!class_exists("\Kronup\Sdk") && exit();

use Kronup\Sdk;
use Kronup\Model;
use PHPUnit\Framework\TestCase;

/**
 * Invitation Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class BillingTest extends TestCase {
    /**
     * Kronup SDK
     *
     * @var \Kronup\Sdk
     */
    protected $sdk;

    /**
     * Account model
     *
     * @var Model\Account
     */
    protected $account;

    /**
     * Organization signing secret
     */
    protected $orgSecret = "";

    const PAYLOAD_SUBSCRIPTION_CREATED = [
        "data" => [
            "id" => "999",
            "type" => "subscriptions",
            "links" => [
                "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999"
            ],
            "attributes" => [
                "urls" => [
                    "update_payment_method" =>
                        "https://kronup.lemonsqueezy.com/subscription/999/payment-details?expires=1689954710&signature=d83ba6a87c4b03338ed89ea3b061110de8849736552cceff235bb6851006a4fb"
                ],
                "pause" => null,
                "status" => "active",
                "ends_at" => null,
                "order_id" => 666,
                "store_id" => 111,
                "cancelled" => false,
                "renews_at" => "2024-07-20T15:51:45.000000Z",
                "test_mode" => true,
                "user_name" => "John Doe",
                "card_brand" => "visa",
                "created_at" => "2023-07-20T15:51:47.000000Z",
                "product_id" => 222,
                "updated_at" => "2023-07-20T15:51:48.000000Z",
                "user_email" => "hello@kronup.com",
                "variant_id" => 333,
                "customer_id" => 444,
                "product_name" => "Studio",
                "variant_name" => "Yearly",
                "order_item_id" => 555,
                "trial_ends_at" => null,
                "billing_anchor" => 20,
                "card_last_four" => "4242",
                "status_formatted" => "Active"
            ],
            "relationships" => [
                "order" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/order",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/order"
                    ]
                ],
                "store" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/store",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/store"
                    ]
                ],
                "product" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/product",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/product"
                    ]
                ],
                "variant" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/variant",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/variant"
                    ]
                ],
                "customer" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/customer",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/customer"
                    ]
                ],
                "order-item" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/order-item",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/order-item"
                    ]
                ],
                "subscription-invoices" => [
                    "links" => [
                        "self" =>
                            "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/subscription-invoices",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/subscription-invoices"
                    ]
                ]
            ]
        ],
        "meta" => [
            "test_mode" => true,
            "event_name" => "subscription_created",
            "custom_data" => [
                "org_id" => "64b952191d3894afcdbf1d87",
                "org_sn" => "2b29fc63d210221b7a946e9f32ed5656b8cf1f3138beb6024861feba1b099788"
            ]
        ]
    ];

    const PAYLOAD_SUBSCRIPTION_UPDATED = [
        "data" => [
            "id" => "999",
            "type" => "subscriptions",
            "links" => [
                "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999"
            ],
            "attributes" => [
                "urls" => [
                    "update_payment_method" =>
                        "https://kronup.lemonsqueezy.com/subscription/999/payment-details?expires=1689954709&signature=9109198003d1b983be8b662283bfcae3d4a9b6b5e0a9dab627aa21337f6a1eeb"
                ],
                "pause" => null,
                "status" => "active",
                "ends_at" => null,
                "order_id" => 666,
                "store_id" => 111,
                "cancelled" => false,
                "renews_at" => "2024-07-20T15:51:45.000000Z",
                "test_mode" => true,
                "user_name" => "John Doe",
                "card_brand" => "visa",
                "created_at" => "2023-07-20T15:51:47.000000Z",
                "product_id" => 222,
                "updated_at" => "2023-07-20T15:51:49.000000Z",
                "user_email" => "hello@kronup.com",
                "variant_id" => 333,
                "customer_id" => 444,
                "product_name" => "Studio",
                "variant_name" => "Yearly",
                "order_item_id" => 555,
                "trial_ends_at" => null,
                "billing_anchor" => 20,
                "card_last_four" => "4242",
                "status_formatted" => "Active"
            ],
            "relationships" => [
                "order" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/order",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/order"
                    ]
                ],
                "store" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/store",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/store"
                    ]
                ],
                "product" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/product",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/product"
                    ]
                ],
                "variant" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/variant",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/variant"
                    ]
                ],
                "customer" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/customer",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/customer"
                    ]
                ],
                "order-item" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/order-item",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/order-item"
                    ]
                ],
                "subscription-invoices" => [
                    "links" => [
                        "self" =>
                            "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/subscription-invoices",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/subscription-invoices"
                    ]
                ]
            ]
        ],
        "meta" => [
            "test_mode" => true,
            "event_name" => "subscription_updated",
            "custom_data" => [
                "org_id" => "64b952191d3894afcdbf1d87",
                "org_sn" => "2b29fc63d210221b7a946e9f32ed5656b8cf1f3138beb6024861feba1b099788"
            ]
        ]
    ];

    const PAYLOAD_SUBSCRIPTION_CANCELLED = [
        "data" => [
            "id" => "999",
            "type" => "subscriptions",
            "links" => [
                "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999"
            ],
            "attributes" => [
                "urls" => [
                    "update_payment_method" =>
                        "https://kronup.lemonsqueezy.com/subscription/999/payment-details?expires=1689961889&signature=de18c6de6e2f229aaf7a88eefba0a66b67835c6799a2e556d6f900d4013139ca"
                ],
                "pause" => null,
                "status" => "cancelled",
                "ends_at" => "2024-07-20T15:51:45.000000Z",
                "order_id" => 666,
                "store_id" => 111,
                "cancelled" => true,
                "renews_at" => "2024-07-20T15:51:45.000000Z",
                "test_mode" => true,
                "user_name" => "John Doe",
                "card_brand" => "visa",
                "created_at" => "2023-07-20T15:51:47.000000Z",
                "product_id" => 222,
                "updated_at" => "2023-07-20T17:51:29.000000Z",
                "user_email" => "hello@kronup.com",
                "variant_id" => 333,
                "customer_id" => 444,
                "product_name" => "Studio",
                "variant_name" => "Yearly",
                "order_item_id" => 555,
                "trial_ends_at" => null,
                "billing_anchor" => 20,
                "card_last_four" => "4242",
                "status_formatted" => "Cancelled"
            ],
            "relationships" => [
                "order" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/order",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/order"
                    ]
                ],
                "store" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/store",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/store"
                    ]
                ],
                "product" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/product",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/product"
                    ]
                ],
                "variant" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/variant",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/variant"
                    ]
                ],
                "customer" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/customer",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/customer"
                    ]
                ],
                "order-item" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/order-item",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/order-item"
                    ]
                ],
                "subscription-invoices" => [
                    "links" => [
                        "self" =>
                            "https://api.lemonsqueezy.com/v1/subscriptions/999/relationships/subscription-invoices",
                        "related" => "https://api.lemonsqueezy.com/v1/subscriptions/999/subscription-invoices"
                    ]
                ]
            ]
        ],
        "meta" => [
            "test_mode" => true,
            "event_name" => "subscription_updated",
            "custom_data" => [
                "org_id" => "64b952191d3894afcdbf1d87",
                "org_sn" => "2b29fc63d210221b7a946e9f32ed5656b8cf1f3138beb6024861feba1b099788"
            ]
        ]
    ];

    const PAYLOAD_PAYMENT_SUCCESS = [
        "data" => [
            "id" => "171898",
            "type" => "subscription-invoices",
            "links" => [
                "self" => "https://api.lemonsqueezy.com/v1/subscription-invoices/171898"
            ],
            "attributes" => [
                "tax" => 22572,
                "urls" => [
                    "invoice_url" =>
                        "https://app.lemonsqueezy.com/my-orders/a72f6d37-3ff6-4419-a93d-4a3e56e504f2/subscription-invoice/171898?signature=6734a705eded370f4216629effc23e4f8280d3019071f1ece1ffbc3cb98e0e43"
                ],
                "total" => 141372,
                "status" => "paid",
                "tax_usd" => 22572,
                "currency" => "USD",
                "refunded" => false,
                "store_id" => 111,
                "subtotal" => 118800,
                "test_mode" => true,
                "total_usd" => 141372,
                "card_brand" => "visa",
                "created_at" => "2023-07-20T15:51:49.000000Z",
                "updated_at" => "2023-07-20T15:52:17.000000Z",
                "refunded_at" => null,
                "subtotal_usd" => 118800,
                "currency_rate" => "1.00000000",
                "tax_formatted" => "$225.72",
                "billing_reason" => "initial",
                "card_last_four" => "4242",
                "discount_total" => 0,
                "subscription_id" => 999,
                "total_formatted" => "$1,413.72",
                "status_formatted" => "Paid",
                "discount_total_usd" => 0,
                "subtotal_formatted" => "$1,188.00",
                "discount_total_formatted" => "$0.00"
            ],
            "relationships" => [
                "store" => [
                    "links" => [
                        "self" => "https://api.lemonsqueezy.com/v1/subscription-invoices/171898/relationships/store",
                        "related" => "https://api.lemonsqueezy.com/v1/subscription-invoices/171898/store"
                    ]
                ],
                "subscription" => [
                    "links" => [
                        "self" =>
                            "https://api.lemonsqueezy.com/v1/subscription-invoices/171898/relationships/subscription",
                        "related" => "https://api.lemonsqueezy.com/v1/subscription-invoices/171898/subscription"
                    ]
                ]
            ]
        ],
        "meta" => [
            "test_mode" => true,
            "event_name" => "subscription_payment_success"
        ]
    ];

    /**
     * Set-up
     */
    public function setUp(): void {
        $this->sdk = new Sdk(getenv("KRONUP_API_KEY"));

        // Fetch account data
        $this->account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Get the first organization ID
        if (!count($this->account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->create((new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc."));
            $this->assertInstanceOf(Model\Organization::class, $organization);
            $this->assertEquals(0, count($organization->listProps()));
            $this->sdk->config()->setOrgId($organization->getId());
        } else {
            $this->sdk->config()->setOrgId(current($this->account->getRoleOrg())->getOrgId());
        }

        // (ADMIN) Fetch organization secret
        $this->orgSecret = $this->sdk
            ->api()
            ->organizations()
            ->getSecret($this->sdk->config()->getOrgId());
        $this->assertIsString($this->orgSecret);
        $this->assertGreaterThan(1, strlen($this->orgSecret));
    }

    /**
     * Test signature algorithm
     */
    public function testWebhook(): void {
        // Clean slate
        $subscription = $this->sdk
            ->api()
            ->billing()
            ->subscriptionRead();
        $this->assertNull($subscription);

        // List available price plans
        $pricePlanList = $this->sdk
            ->api()
            ->billing()
            ->planList();
        $this->assertInstanceOf(Model\PricePlanList::class, $pricePlanList);
        $this->assertEquals(0, count($pricePlanList->listProps()));

        // Must re-fetch the plans
        if (!$pricePlanList->getTotal()) {
            $pricePlanList = $this->sdk
                ->api()
                ->billing()
                ->planRefetch();
        }

        $this->assertGreaterThan(0, $pricePlanList->getTotal());
        $this->assertIsArray($pricePlanList->getPricePlans());

        // Select the first price plan
        $pricePlan = $pricePlanList->getPricePlans()[0];
        $this->assertInstanceOf(Model\PricePlan::class, $pricePlan);
        $this->assertEquals(0, count($pricePlan->listProps()));

        // Update price plan
        $pricePlanUpdated = $this->sdk
            ->api()
            ->billing()
            ->planUpdate($pricePlan->getId(), (new Model\PayloadPricePlanUpdate())->setUsersMax(6));
        $this->assertInstanceOf(Model\PricePlan::class, $pricePlanUpdated);
        $this->assertEquals(0, count($pricePlanUpdated->listProps()));

        // List all price plans
        $pricePlanList = $this->sdk
            ->api()
            ->billing()
            ->planList();
        $this->assertInstanceOf(Model\PricePlanList::class, $pricePlanList);
        $this->assertEquals(0, count($pricePlanList->listProps()));
        $this->assertGreaterThan(0, $pricePlanList->getTotal());
        $this->assertIsArray($pricePlanList->getPricePlans());

        // Confirm change
        $this->assertEquals($pricePlanUpdated->getUsersMax(), $pricePlanList->getPricePlans()[0]->getUsersMax());

        // Create a subscription
        $response = $this->lemonRequest(self::PAYLOAD_SUBSCRIPTION_CREATED, $pricePlanUpdated);
        $this->assertTrue($response);
        $subscription = $this->sdk
            ->api()
            ->billing()
            ->subscriptionRead();
        $this->assertInstanceOf(Model\SubscriptionNullable::class, $subscription);
        $this->assertEquals(0, count($subscription->listProps()));

        // Create a subscription
        $response = $this->lemonRequest(self::PAYLOAD_SUBSCRIPTION_UPDATED, $pricePlanUpdated);
        $this->assertTrue($response);
        $subscription = $this->sdk
            ->api()
            ->billing()
            ->subscriptionRead();
        $this->assertInstanceOf(Model\SubscriptionNullable::class, $subscription);
        $this->assertEquals(0, count($subscription->listProps()));

        // Cancel the subscription
        $response = $this->lemonRequest(self::PAYLOAD_SUBSCRIPTION_CANCELLED, $pricePlanUpdated);
        $this->assertTrue($response);
        $subscription = $this->sdk
            ->api()
            ->billing()
            ->subscriptionRead();
        $this->assertInstanceOf(Model\SubscriptionNullable::class, $subscription);
        $this->assertEquals(0, count($subscription->listProps()));
        $this->assertEquals("cancelled", $subscription->getStatus());

        // List invoices
        $invoiceList = $this->sdk
            ->api()
            ->billing()
            ->invoiceList();
        $this->assertInstanceOf(Model\InvoiceList::class, $invoiceList);
        $this->assertEquals(0, count($invoiceList->listProps()));
        $this->assertEquals(0, $invoiceList->getTotal());

        // Create an invoice
        $response = $this->lemonRequest(self::PAYLOAD_PAYMENT_SUCCESS, $pricePlanUpdated);
        $this->assertTrue($response);

        // Re-fetch invoices
        $invoiceList = $this->sdk
            ->api()
            ->billing()
            ->invoiceList();
        $this->assertInstanceOf(Model\InvoiceList::class, $invoiceList);
        $this->assertEquals(0, count($invoiceList->listProps()));
        $this->assertGreaterThanOrEqual(1, $invoiceList->getTotal());

        // Validate invoice
        $invoice = $invoiceList->getInvoices()[0];
        $this->assertInstanceOf(Model\Invoice::class, $invoice);
        $this->assertEquals(0, count($invoice->listProps()));

        // Remove the organization
        $deleted = $this->sdk
            ->api()
            ->organizations()
            ->delete($this->sdk->config()->getOrgId());
        $this->assertTrue($deleted);
    }

    /**
     * Prepare the kronup signature
     *
     * @param string $orgSecret Organization Secret
     * @param string $orgId     Organization ID
     * @param string $variantId Variant ID
     */
    protected function kronupSignature($orgSecret, $orgId, $variantId) {
        return hash_hmac("sha256", "$orgId.$variantId", $orgSecret);
    }

    /**
     * Generate LemonSqueezy signature for the provided raw body string
     *
     * @param string $rawBody
     */
    protected function lemonSignature($rawBody) {
        return hash_hmac("sha256", $rawBody, getenv("KRONUP_BILLING_SECRET"));
    }

    /**
     * Simulate a request sent by LemonSqueezy with the provided payload
     *
     * @param array           $payload   Payload
     * @param Model\PricePlan $pricePlan Price Plan model
     */
    protected function lemonRequest($payload, $pricePlan) {
        $eventName = $payload["meta"]["event_name"];

        // Set the store ID
        $payload["data"]["attributes"]["store_id"] = getenv("KRONUP_BILLING_STORE_ID");

        // Subscription event
        if (preg_match('%^subscription_(?:created|updated)$%', $eventName)) {
            // Store the product and variant
            $payload["data"]["attributes"]["product_id"] = $pricePlan->getProductId();
            $payload["data"]["attributes"]["variant_id"] = $pricePlan->getVariantId();

            // Prepare the custom data
            $payload["meta"]["custom_data"] = [
                "org_id" => $this->sdk->config()->getOrgId(),
                "org_sn" => $this->kronupSignature(
                    $this->orgSecret,
                    $this->sdk->config()->getOrgId(),
                    $payload["data"]["attributes"]["variant_id"]
                )
            ];
        }

        // Prepare the body
        $rawBody = json_encode($payload);

        // Prepare the signature
        $signature = $this->lemonSignature($rawBody);

        // Initialize the request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->sdk->config()->getHost()}/billing/webhook");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Event-Name: {$eventName}",
            "X-Signature: {$signature}"
        ]);

        // Fetch the result
        $jsonResult = curl_exec($ch);

        return @json_decode($jsonResult, true);
    }
}
