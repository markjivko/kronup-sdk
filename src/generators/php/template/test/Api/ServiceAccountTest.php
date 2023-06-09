<?php
/**
 * Service Account Test
 *
 * @copyright (c) 2022-2023 kronup.io
 * @license   MIT
 * @package   kronup
 * @author    Mark Jivko
 */

namespace Kronup\Test\Local\Api;
!class_exists("\Kronup\Sdk") && exit();

use Kronup\Sdk;
use Kronup\Model;
use PHPUnit\Framework\TestCase;
use Kronup\Sdk\ApiException;

/**
 * Service Account Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ServiceAccountTest extends TestCase {
    /**
     * kronup SDK
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
     * Team model
     *
     * @var Model\TeamExpanded
     */
    protected $team;

    /**
     * Channel model
     *
     * @var Model\Channel
     */
    protected $channel;

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
     * Remove the organization
     */
    public function tearDown(): void {
        $deleted = $this->sdk
            ->api()
            ->organizations()
            ->delete($this->sdk->config()->getOrgId());
        $this->assertTrue($deleted);
    }

    /**
     * Create, read, list, update, delete, use
     */
    public function testAll(): void {
        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->list();
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));
        $this->assertIsArray($serviceAccountList->getServiceAccounts());

        $serviceAccount =
            0 !== count($serviceAccountList->getServiceAccounts())
                ? $serviceAccountList->getServiceAccounts()[0]
                : $this->sdk
                    ->api()
                    ->serviceAccounts()
                    ->create(
                        (new Model\PayloadServiceAccountCreate())
                            ->setRoleOrg(Model\PayloadServiceAccountCreate::ROLE_ORG_ADMIN)
                            ->setUserName("New account name")
                    );

        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccount);
        $this->assertEquals(0, count($serviceAccount->listProps()));

        $this->assertIsString($serviceAccount->getRoleOrg());

        // Fetch the account
        $serviceAccountRead = $this->sdk
            ->api()
            ->serviceAccounts()
            ->read($serviceAccount->getId());

        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccountRead);
        $this->assertEquals(0, count($serviceAccountRead->listProps()));

        $this->assertIsString($serviceAccountRead->getRoleOrg());
        $this->assertEquals($serviceAccount->getServiceToken(), $serviceAccountRead->getServiceToken());

        // Fetch all
        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->list();
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));

        $this->assertIsArray($serviceAccountList->getServiceAccounts());
        $this->assertGreaterThanOrEqual(1, count($serviceAccountList->getServiceAccounts()));

        // Update
        $serviceAccountUpdated = $this->sdk
            ->api()
            ->serviceAccounts()
            ->update($serviceAccount->getId(), (new Model\PayloadServiceAccountUpdate())->setUserName("New name"));
        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccountUpdated);
        $this->assertEquals(0, count($serviceAccountUpdated->listProps()));
        $this->assertEquals("New name", $serviceAccountUpdated->getUserName());

        // Let's use it!
        $sdkService = new Sdk($serviceAccount->getServiceToken());
        $remoteAccount = $sdkService
            ->api()
            ->account()
            ->read();

        $this->assertInstanceOf(Model\Account::class, $remoteAccount);
        $this->assertEquals(0, count($remoteAccount->listProps()));
        $this->assertEquals($serviceAccount->getId(), $remoteAccount->getId());

        $remoteEventList = $sdkService
            ->api()
            ->account()
            ->eventList();
        $this->assertInstanceOf(Model\EventsList::class, $remoteEventList);
        $this->assertEquals(0, count($remoteEventList->listProps()));

        // Regenerate
        $regenerated = $this->sdk
            ->api()
            ->serviceAccounts()
            ->regenerate($serviceAccount->getId());
        $this->assertInstanceOf(Model\ServiceAccount::class, $regenerated);
        $this->assertEquals(0, count($regenerated->listProps()));
        $this->assertNotEquals($serviceAccount->getServiceToken(), $regenerated->getServiceToken());

        // Delete
        $deleted = $this->sdk
            ->api()
            ->serviceAccounts()
            ->close($serviceAccount->getId());
        $this->assertInstanceOf(Model\ServiceAccount::class, $deleted);
        $this->assertEquals(0, count($deleted->listProps()));
        $this->assertNotEquals($serviceAccount->getServiceToken(), $deleted->getServiceToken());

        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $serviceAccountRead = $this->sdk
            ->api()
            ->serviceAccounts()
            ->read($serviceAccount->getId());
    }
}
