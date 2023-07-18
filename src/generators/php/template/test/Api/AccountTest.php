<?php
/**
 * Account Test
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
 * Account Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class AccountTest extends TestCase {
    /**
     * Kronup SDK
     *
     * @var \Kronup\Sdk
     */
    protected $sdk;

    /**
     * Set-up
     */
    public function setUp(): void {
        $this->sdk = new Sdk(getenv("KRONUP_API_KEY"));

        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Get the first organization ID
        if (!count($account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->create((new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc."));
            $this->assertInstanceOf(Model\Organization::class, $organization);
            $this->assertEquals(0, count($organization->listProps()));
        }
    }

    /**
     * Read & Update
     */
    public function testAccountReadUpdate(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->read();
        $this->assertInstanceOf(Model\Account::class, $account);
        $this->assertEquals(0, count($account->listProps()));

        $this->assertInstanceOf(Model\AccountRoleOrg::class, $account->getRoleOrg()[0]);
        $this->assertEquals(0, count($account->getRoleOrg()[0]->listProps()));

        // Validate organizations list
        $this->assertIsArray($account->getOrgs());
        $this->assertGreaterThanOrEqual(1, count($account->getOrgs()));
        $this->assertInstanceOf(Model\Organization::class, $account->getOrgs()[0]);
        $this->assertEquals(0, count($account->getOrgs()[0]->listProps()));

        $userName = $account->getUserName();

        // Update the account
        $updatedAccount = $this->sdk
            ->api()
            ->account()
            ->update((new Model\PayloadAccountUpdate())->setUserName("$userName 2"));
        $this->assertInstanceOf(Model\Account::class, $updatedAccount);
        $this->assertEquals(0, count($updatedAccount->listProps()));

        $this->assertEquals("$userName 2", $updatedAccount->getUserName());

        // Revert the changes
        $this->sdk
            ->api()
            ->account()
            ->update((new Model\PayloadAccountUpdate())->setUserName($userName));
    }
}
