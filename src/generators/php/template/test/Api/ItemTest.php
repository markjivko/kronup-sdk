<?php
/**
 * Item Test
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
 * Item Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ItemTest extends TestCase {
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
        $this->orgId = current($this->account->getRoleOrg())->getOrgId();

        // Set-up a new team
        $this->team = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($this->orgId, (new Model\RequestTeamCreate())->setTeamName("New team"));

        // Store the default channel
        $this->channel = $this->team->getChannels()[0];

        // Assign user to team
        $this->sdk
            ->api()
            ->teams()
            ->teamAssign($this->team->getId(), $this->account->getId(), $this->orgId);
    }

    /**
     * Tear-down
     */
    public function tearDown(): void {
        // Remove the team
        $this->sdk
            ->api()
            ->teams()
            ->teamDelete($this->team->getId(), $this->orgId);
    }

    /**
     * Read & Update
     */
    public function testCreateRead(): void {
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->orgId,
                (new Model\RequestValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\RequestValueItemCreate::PRIORITY_C)
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);

        // Get the list
        $items = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemList($this->team->getId(), $this->channel->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ValueItemsList::class, $items);
        $this->assertIsArray($items->getItems());
        $this->assertGreaterThan(0, count($items->getItems()));
    }

    /**
     * Delete item
     */
    public function testUpdateDelete() {
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->orgId,
                (new Model\RequestValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\RequestValueItemCreate::PRIORITY_C)
            );

        // Update the item
        $itemUpdated = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemUpdate(
                $this->team->getId(),
                $this->channel->getId(),
                $item->getId(),
                $this->orgId,
                (new Model\RequestValueItemUpdate())->setDigest("The new digest")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $itemUpdated);
        $this->assertEquals("The new digest", $itemUpdated->getDigest());

        // Delete the item
        $itemDeleted = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemDelete($this->team->getId(), $this->channel->getId(), $item->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ValueItem::class, $itemDeleted);
        $this->assertEquals($itemDeleted->getId(), $item->getId());

        // Expect to fail (item was removed)
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemRead($this->team->getId(), $this->channel->getId(), $item->getId(), $this->orgId);
    }

    /**
     * Advance error
     */
    public function testAdvanceError() {
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->orgId,
                (new Model\RequestValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\RequestValueItemCreate::PRIORITY_C)
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);

        // Advance the item
        $this->expectExceptionObject(new ApiException("You must add at least 1 Assumption", 403));
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $item->getId(), $this->orgId);
    }
}
