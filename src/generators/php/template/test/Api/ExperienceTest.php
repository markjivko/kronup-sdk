<?php
/**
 * Experience Test
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
 * Notion Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ExperienceTest extends TestCase {
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
            ->accountRead();

        // Get the first organization ID
        $this->orgId = current($this->account->getRoleOrg())->getOrgId();

        // Create the notion
        $this->notion = $this->sdk
            ->api()
            ->notions()
            ->notionCreate($this->orgId, (new Model\RequestNotionCreate())->setValue(uniqid()));
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
            ->notionDelete($this->notion->getId(), $this->orgId);
        $this->assertTrue($deleted);
    }

    /**
     * Create & Read
     */
    public function testCreateReadEvaluate(): void {
        // Evaluate self
        for ($i = 1; $i <= 11; $i++) {
            $experience = $this->sdk
                ->api()
                ->experiences()
                ->experienceEvaluateSelf($this->notion->getId(), mt_rand(1, 10), $this->orgId);
            $this->assertInstanceOf(Model\Experience::class, $experience);
            $this->assertEquals(0, count($experience->listProps()));
        }

        // Evaluate peer
        for ($i = 1; $i <= 11; $i++) {
            $experience = $this->sdk
                ->api()
                ->experiences()
                ->experienceEvaluatePeer($this->notion->getId(), $this->account->getId(), mt_rand(1, 10), $this->orgId);
            $this->assertInstanceOf(Model\Experience::class, $experience);
            $this->assertEquals(0, count($experience->listProps()));
        }

        // Find all
        $xpList = $this->sdk
            ->api()
            ->experiences()
            ->experienceList($this->account->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ExperienceList::class, $xpList);
        $this->assertEquals(0, count($xpList->listProps()));

        // Assert array
        $this->assertIsArray($xpList->getExperiences());
        $this->assertEquals(1, count($xpList->getExperiences()));

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
            ->experienceRead($this->notion->getId(), $this->account->getId(), $this->orgId);
        $this->assertInstanceOf(Model\Experience::class, $xpRead);
        $this->assertEquals(0, count($xpRead->listProps()));

        $this->assertInstanceOf(Model\Notion::class, $xpRead->getNotion());
        $this->assertEquals(0, count($xpRead->getNotion()->listProps()));
    }

    // @TODO Test removed experience when deleting notion
    // @TODO Test removed notion from task when deleting notion
    // @TODO Test delete
}
