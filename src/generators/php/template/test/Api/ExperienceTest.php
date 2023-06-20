<?php
/**
 * Experience Test
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
use Kronup\Sdk\ApiException;

/**
 * Notion Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ExperienceTest extends TestCase {
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
     * Notion model
     */
    protected $notion;

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

        // Create the notion
        $this->notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue(uniqid()));
        $this->assertInstanceOf(Model\Notion::class, $this->notion);
        $this->assertEquals(0, count($this->notion->listProps()));
    }

    /**
     * Tear-down
     */
    public function tearDown(): void {
        $deleted = $this->sdk
            ->api()
            ->notions()
            ->delete($this->notion->getId());
        $this->assertTrue($deleted);
    }

    /**
     * Create & Read
     */
    public function testCreateReadEvaluate(): void {
        // Evaluate self
        for ($i = 1; $i <= 10; $i++) {
            $grade = mt_rand(1, 10);
            $experience = $this->sdk
                ->api()
                ->experiences()
                ->evaluate($this->notion->getId(), $grade);
            $this->assertInstanceOf(Model\Experience::class, $experience);
            $this->assertEquals(0, count($experience->listProps()));
        }

        // Grade was overwritten
        $myExperience = $this->sdk
            ->api()
            ->experiences()
            ->read($this->notion->getId());
        $this->assertInstanceOf(Model\Experience::class, $myExperience);
        $this->assertEquals(0, count($myExperience->listProps()));
        $this->assertEquals($grade, $myExperience->getSelfEval()->getAverage());
        $this->assertEquals(1, $myExperience->getSelfEval()->getCount());
        $this->assertEquals(1, count($myExperience->getSelfEval()->getRecent()));

        // Fetch teh service accounts
        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->list();
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));
        $this->assertIsArray($serviceAccountList->getServiceAccounts());

        // Prepare the service account
        $serviceAccount =
            0 !== count($serviceAccountList->getServiceAccounts())
                ? $serviceAccountList->getServiceAccounts()[0]
                : $this->sdk
                    ->api()
                    ->serviceAccounts()
                    ->create(
                        (new Model\PayloadServiceAccountCreate())
                            ->setRoleOrg(Model\PayloadServiceAccountCreate::ROLE_ORG_ADMIN)
                            ->setUserName("New service account name")
                    );
        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceAccount);
        $this->assertEquals(0, count($serviceAccount->listProps()));
        $serviceSdk = new Sdk($serviceAccount->getServiceToken());

        // Evaluate peer
        for ($i = 1; $i <= 10; $i++) {
            $grade = mt_rand(1, 5);
            $experience = $serviceSdk
                ->api()
                ->experiences()
                ->evaluatePeer($this->notion->getId(), $grade, $this->account->getId());
            $this->assertInstanceOf(Model\Experience::class, $experience);
            $this->assertEquals(0, count($experience->listProps()));
        }

        // Close the service account
        $deleted = $this->sdk
            ->api()
            ->serviceAccounts()
            ->close($serviceAccount->getId());
        $this->assertInstanceOf(Model\ServiceAccount::class, $deleted);
        $this->assertEquals(0, count($deleted->listProps()));
        $this->assertNotEquals($serviceAccount->getServiceToken(), $deleted->getServiceToken());

        // Fetch the experience once more
        $myExperience = $this->sdk
            ->api()
            ->experiences()
            ->read($this->notion->getId());
        $this->assertInstanceOf(Model\Experience::class, $myExperience);
        $this->assertEquals(0, count($myExperience->listProps()));
        $this->assertEquals($grade, $myExperience->getPeerEval()->getAverage());
        $this->assertEquals(1, $myExperience->getPeerEval()->getCount());
        $this->assertEquals(1, count($myExperience->getPeerEval()->getRecent()));

        // Find all
        $xpList = $this->sdk
            ->api()
            ->experiences()
            ->list();
        $this->assertInstanceOf(Model\ExperiencesList::class, $xpList);
        $this->assertEquals(0, count($xpList->listProps()));

        // Assert array
        $this->assertIsArray($xpList->getExperiences());
        $this->assertGreaterThanOrEqual(1, count($xpList->getExperiences()));
        $this->assertGreaterThanOrEqual(count($xpList->getExperiences()), $xpList->getTotal());

        // Validate notions are expanded
        foreach ($xpList->getExperiences() as $xp) {
            $this->assertInstanceOf(Model\Experience::class, $xp);
            $this->assertEquals(0, count($xp->listProps()));

            $this->assertInstanceOf(Model\Notion::class, $xp->getNotion());
            $this->assertEquals(0, count($xp->getNotion()->listProps()));
        }

        // Find one
        $xpRead = $this->sdk
            ->api()
            ->experiences()
            ->read($this->notion->getId());
        $this->assertInstanceOf(Model\Experience::class, $xpRead);
        $this->assertEquals(0, count($xpRead->listProps()));

        $this->assertInstanceOf(Model\Notion::class, $xpRead->getNotion());
        $this->assertEquals(0, count($xpRead->getNotion()->listProps()));
    }

    /**
     * Removing notion deletes it from task as well
     */
    public function testRemoveNotion(): void {
        // Prepare the notion
        $notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue("new-notion-" . mt_rand(1, 999)));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));

        // Evaluate self
        $experience = $this->sdk
            ->api()
            ->experiences()
            ->evaluate($notion->getId(), mt_rand(1, 10));
        $this->assertInstanceOf(Model\Experience::class, $experience);
        $this->assertEquals(0, count($experience->listProps()));

        // Delete the notion
        $deleted = $this->sdk
            ->api()
            ->notions()
            ->delete($notion->getId());
        $this->assertTrue($deleted);

        // Fetch the experience
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->experiences()
            ->read($notion->getId());
    }
}
