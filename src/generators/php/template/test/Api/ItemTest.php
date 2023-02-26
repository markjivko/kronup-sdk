<?php
/**
 * Item Test
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

/**
 * Item Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ItemTest extends TestCase {
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
            ->teamCreate($this->orgId, (new Model\TeamCreateRequest())->setTeamName("New team"));

        // Store the default channel
        $this->channel = $this->team->getChannels()[0];

        // Assign user to team
        $this->sdk
            ->api()
            ->teams()
            ->teamAssign($this->team->getId(), $this->account->getId(), $this->orgId);
    }

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
    public function testItemReadAll(): void {
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->orgId,
                (new Model\ValueItemCreateRequest())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(Model\ValueItemCreateRequest::PRIORITY_C)
            );

        // Get the list
        $items = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemList($this->team->getId(), $this->channel->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ValueItemList::class, $items);
        $this->assertIsArray($items->getItems());
        $this->assertGreaterThan(0, count($items->getItems()));

        var_dump($items->getItems()[0]);
    }
}
