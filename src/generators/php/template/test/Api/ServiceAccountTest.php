<?php
/**
 * Service Account Test
 *
 * @copyright (c) 2022-2023 kronup.com
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
     * Organization ID
     *
     * @var string
     */
    protected $orgId;

    /**
     * Team model
     *
     * @var Model\Team
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
            ->accountRead();

        // Get the first organization ID
        if (!count($this->account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->organizationCreate(
                    (new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc.")
                );
            $this->assertInstanceOf(Model\Organization::class, $organization);
            $this->assertEquals(0, count($organization->listProps()));
            $this->orgId = $organization->getId();
        } else {
            $this->orgId = current($this->account->getRoleOrg())->getOrgId();
        }
    }

    /**
     * Remove the organization
     */
    public function tearDown(): void {
        $deleted = $this->sdk
            ->api()
            ->organizations()
            ->organizationDelete($this->orgId);
        $this->assertTrue($deleted);
    }

    /**
     * Create, read, list, update, delete, use
     */
    public function testAll(): void {
        $serviceAccount = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountCreate(
                $this->orgId,
                (new Model\PayloadServiceAccountCreate())
                    ->setRoleOrg(Model\PayloadServiceAccountCreate::ROLE_ORG_MANAGER)
                    ->setUserName("New account name")
            );

        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccount);
        $this->assertEquals(0, count($serviceAccount->listProps()));

        $this->assertInstanceOf(Model\UserRoleOrg::class, $serviceAccount->getRoleOrg()[0]);
        $this->assertEquals(0, count($serviceAccount->getRoleOrg()[0]->listProps()));

        // Fetch the account
        $serviceAccountRead = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountRead($serviceAccount->getId(), $this->orgId);

        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccountRead);
        $this->assertEquals(0, count($serviceAccountRead->listProps()));

        $this->assertInstanceOf(Model\UserRoleOrg::class, $serviceAccountRead->getRoleOrg()[0]);
        $this->assertEquals(0, count($serviceAccountRead->getRoleOrg()[0]->listProps()));
        $this->assertEquals($serviceAccount->getServiceToken(), $serviceAccountRead->getServiceToken());

        // Fetch all
        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountList($this->orgId);
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));

        $this->assertIsArray($serviceAccountList->getServiceAccounts());
        $this->assertGreaterThanOrEqual(1, count($serviceAccountList->getServiceAccounts()));

        // Update
        $serviceAccountUpdated = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountUpdate(
                $serviceAccount->getId(),
                $this->orgId,
                (new Model\PayloadServiceAccountUpdate())->setUserName("New name")
            );
        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccountUpdated);
        $this->assertEquals(0, count($serviceAccountUpdated->listProps()));
        $this->assertEquals("New name", $serviceAccountUpdated->getUserName());

        // Let's use it!
        $sdkService = new Sdk($serviceAccount->getServiceToken());
        $remoteAccount = $sdkService
            ->api()
            ->account()
            ->accountRead();

        $this->assertInstanceOf(Model\Account::class, $remoteAccount);
        $this->assertEquals(0, count($remoteAccount->listProps()));
        $this->assertEquals($serviceAccount->getUserEmail(), $remoteAccount->getUserEmail());

        // Regenerate
        $regenerated = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountRegenerate($serviceAccount->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ServiceAccount::class, $regenerated);
        $this->assertEquals(0, count($regenerated->listProps()));
        $this->assertNotEquals($serviceAccount->getServiceToken(), $regenerated->getServiceToken());

        // Delete
        $deleted = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountDelete($serviceAccount->getId(), $this->orgId);
        $this->assertInstanceOf(Model\User::class, $deleted);
        $this->assertEquals(0, count($deleted->listProps()));

        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $serviceAccountRead = $this->sdk
            ->api()
            ->serviceAccounts()
            ->serviceAccountRead($serviceAccount->getId(), $this->orgId);
    }
}
