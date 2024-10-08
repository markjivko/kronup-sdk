<?php
/**
 * Notion Test
 *
 * @copyright (c) 2022-2023 kronup.com
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
class NotionTest extends TestCase {
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
     * Notion id
     *
     * @var string
     */
    protected $notionId = null;

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
     * Tear-down
     */
    public function tearDown(): void {
        if (null !== $this->notionId) {
            $deleted = $this->sdk
                ->api()
                ->notions()
                ->delete($this->notionId);
            $this->assertTrue($deleted);
        }
    }

    /**
     * Create & Read
     */
    public function testCreateRead(): void {
        $notionValue = uniqid();
        $notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue($notionValue));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));
        $this->notionId = $notion->getId();

        // Find notion by id
        $notionRead = $this->sdk
            ->api()
            ->notions()
            ->read($notion->getId());
        $this->assertInstanceOf(Model\Notion::class, $notionRead);
        $this->assertEquals(0, count($notionRead->listProps()));
        $this->assertEquals($notion->getValue(), $notionRead->getValue());

        // Must not fail on create
        $notionRefetched = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue($notionValue));
        $this->assertInstanceOf(Model\Notion::class, $notionRefetched);
        $this->assertEquals(0, count($notionRefetched->listProps()));
        $this->assertEquals($notion->getValue(), $notionRefetched->getValue());
    }

    public function testInvalidNotion() {
        $this->expectExceptionObject(new ApiException("Bad Request", 400));
        $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue(str_repeat("x", 65)));
    }

    /**
     * Update & Delete
     */
    public function testUpdateDelete(): void {
        $notionValue = uniqid();
        $notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue($notionValue));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));

        // Update the value
        $notionUpdated = $this->sdk
            ->api()
            ->notions()
            ->update($notion->getId(), (new Model\PayloadNotionUpdate())->setValue($notionValue . "-updated"));
        $this->assertInstanceOf(Model\Notion::class, $notionUpdated);
        $this->assertEquals(0, count($notionUpdated->listProps()));
        $this->assertEquals($notionValue . "-updated", $notionUpdated->getValue());

        // Prepare the list
        $notionList = $this->sdk
            ->api()
            ->notions()
            ->search(substr($notionValue, 0, 4), 1, 10);
        $this->assertInstanceOf(Model\NotionsList::class, $notionList);
        $this->assertEquals(0, count($notionList->listProps()));

        $this->assertGreaterThanOrEqual(count($notionList->getNotions()), $notionList->getTotal());

        // Delete the notion
        $deleted = $this->sdk
            ->api()
            ->notions()
            ->delete($notion->getId());
        $this->assertTrue($deleted);

        // Attempt to read it again
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->notions()
            ->read($notion->getId());
    }
}
