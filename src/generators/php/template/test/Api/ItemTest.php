<?php
/**
 * Item Test
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
     * Team model
     *
     * @var Model\TeamExtended
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

        // Set-up a new team
        $this->team = $this->sdk
            ->api()
            ->teams()
            ->create((new Model\PayloadTeamCreate())->setTeamName("New team"));

        // Store the default channel
        $this->channel = $this->team->getChannels()[0];

        // Assign user to team
        $this->sdk
            ->api()
            ->teams()
            ->assign($this->team->getId(), $this->account->getId());
    }

    /**
     * Tear-down
     */
    public function tearDown(): void {
        // Remove the team
        $deleted = $this->sdk
            ->api()
            ->teams()
            ->delete($this->team->getId());
        $this->assertTrue($deleted);
    }

    /**
     * Read & Update
     */
    public function testCreateRead(): void {
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadValueItemCreate())
                    ->setHeading("The heading")
                    ->setDetails("The details")
                    ->setPriority(4)
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Get the events
        $notifs = $this->sdk
            ->api()
            ->account()
            ->eventList();
        $this->assertInstanceOf(Model\EventsList::class, $notifs);
        $this->assertEquals(0, count($notifs->listProps()));

        $this->assertIsArray($notifs->getEvents());
        $this->assertGreaterThanOrEqual(1, count($notifs->getEvents()));

        // Get the list
        $items = $this->sdk
            ->api()
            ->valueItems()
            ->list($this->team->getId(), $this->channel->getId());
        $this->assertInstanceOf(Model\ValueItemsList::class, $items);
        $this->assertEquals(0, count($items->listProps()));

        $this->assertIsArray($items->getItems());
        $this->assertGreaterThan(0, count($items->getItems()));
        $this->assertGreaterThanOrEqual(count($items->getItems()), $items->getTotal());

        $this->assertInstanceOf(Model\ValueItemLite::class, $items->getItems()[0]);
        $this->assertEquals(0, count($items->getItems()[0]->listProps()));
    }

    /**
     * Delete item
     */
    public function testUpdateDelete() {
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadValueItemCreate())
                    ->setHeading("The heading")
                    ->setDetails("The details")
                    ->setPriority(4)
            );

        // Update the item
        $itemUpdated = $this->sdk
            ->api()
            ->valueItems()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $item->getId(),
                (new Model\PayloadValueItemUpdate())->setHeading("The new heading")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $itemUpdated);
        $this->assertEquals(0, count($itemUpdated->listProps()));

        $this->assertEquals("The new heading", $itemUpdated->getHeading());

        // Delete the item
        $deleted = $this->sdk
            ->api()
            ->valueItems()
            ->delete($this->team->getId(), $this->channel->getId(), $item->getId());
        $this->assertTrue($deleted);

        // Expect to fail (item was removed)
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $item->getId());
    }

    /**
     * Advance error
     */
    public function testAdvanceError() {
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadValueItemCreate())
                    ->setHeading("The heading")
                    ->setDetails("The details")
                    ->setPriority(4)
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Advance the item
        $this->expectExceptionObject(new ApiException("You must add at least 1 Assumption", 403));
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $item->getId());
    }
}
