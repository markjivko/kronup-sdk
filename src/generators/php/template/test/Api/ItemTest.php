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

        // Set-up a new team
        $this->team = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($this->orgId, (new Model\PayloadTeamCreate())->setTeamName("New team"));

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
        $deleted = $this->sdk
            ->api()
            ->teams()
            ->teamDelete($this->team->getId(), $this->orgId);
        $this->assertTrue($deleted);
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
                (new Model\PayloadValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\PayloadValueItemCreate::PRIORITY_COULD)
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Get the notifications
        $notifs = $this->sdk
            ->api()
            ->account()
            ->notificationList($this->orgId);
        $this->assertInstanceOf(Model\NotificationsList::class, $notifs);
        $this->assertEquals(0, count($notifs->listProps()));

        $this->assertIsArray($notifs->getNotifications());
        $this->assertGreaterThanOrEqual(1, count($notifs->getNotifications()));

        // Get the list
        $items = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemList($this->team->getId(), $this->channel->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ValueItemsList::class, $items);
        $this->assertEquals(0, count($items->listProps()));

        $this->assertIsArray($items->getItems());
        $this->assertGreaterThan(0, count($items->getItems()));
        $this->assertGreaterThanOrEqual(count($items->getItems()), $items->getTotal());
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
                (new Model\PayloadValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\PayloadValueItemCreate::PRIORITY_COULD)
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
                (new Model\PayloadValueItemUpdate())->setDigest("The new digest")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $itemUpdated);
        $this->assertEquals(0, count($itemUpdated->listProps()));

        $this->assertEquals("The new digest", $itemUpdated->getDigest());

        // Delete the item
        $deleted = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemDelete($this->team->getId(), $this->channel->getId(), $item->getId(), $this->orgId);
        $this->assertTrue($deleted);

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
                (new Model\PayloadValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\PayloadValueItemCreate::PRIORITY_COULD)
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Advance the item
        $this->expectExceptionObject(new ApiException("You must add at least 1 Assumption", 403));
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $item->getId(), $this->orgId);
    }
}
