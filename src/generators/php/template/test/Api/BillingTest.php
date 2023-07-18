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

    const PAYLOAD_SUB_UPDATED = '{"meta":{"test_mode":true,"event_name":"subscription_updated"},"data":{"type":"subscriptions","id":"91872","attributes":{"store_id":27802,"customer_id":873367,"order_id":907595,"order_item_id":876161,"product_id":77320,"variant_id":80045,"product_name":"Startup","variant_name":"Monthly","user_name":"Mark Jivko","user_email":"markjivko@gmail.com","status":"active","status_formatted":"Active","card_brand":"visa","card_last_four":"4242","pause":null,"cancelled":false,"trial_ends_at":null,"billing_anchor":1,"urls":{"update_payment_method":"https:\/\/kronup.lemonsqueezy.com\/subscription\/91872\/payment-details?expires=1688308379&signature=ba0d4fb3c56998fddd10c0776008eb156d7d14b9c5edc411c1cbf9d67428e951"},"renews_at":"2023-08-01T14:32:56.000000Z","ends_at":null,"created_at":"2023-07-01T14:32:57.000000Z","updated_at":"2023-07-01T14:32:59.000000Z","test_mode":true},"relationships":{"store":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/store","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/store"}},"customer":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/customer","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/customer"}},"order":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/order","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/order"}},"order-item":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/order-item","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/order-item"}},"product":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/product","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/product"}},"variant":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/variant","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/variant"}},"subscription-invoices":{"links":{"related":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/subscription-invoices","self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872\/relationships\/subscription-invoices"}}},"links":{"self":"https:\/\/api.lemonsqueezy.com\/v1\/subscriptions\/91872"}}}';

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
    }

    /**
     * Generate signature
     */
    protected function getSignature($payload) {
        return hash_hmac("sha256", $payload, getenv("KRONUP_BILLING_SECRET"));
    }

    /**
     * Test signature algorithm
     */
    public function testSignature(): void {
        $this->assertEquals(
            "b8b74e708a8e1eccd4d29349ef95abeff88d174ce7368dcbcf919adb86ce3332",
            $this->getSignature(self::PAYLOAD_SUB_UPDATED)
        );
    }
}
