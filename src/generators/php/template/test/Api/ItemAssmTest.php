<?php
/**
 * Value Item Assumption Test
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
 * Assumption Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ItemAssmTest extends TestCase {
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
     * Value item
     *
     * @var Model\ValueItem
     */
    protected $item;

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
            $this->orgId = $organization->getId();
        } else {
            $this->orgId = current($this->account->getRoleOrg())->getOrgId();
        }

        // Set-up a new team
        $this->team = $this->sdk
            ->api()
            ->teams()
            ->create($this->orgId, (new Model\PayloadTeamCreate())->setTeamName("New team"));

        // Store the default channel
        $this->channel = $this->team->getChannels()[0];

        // Assign user to team
        $this->sdk
            ->api()
            ->teams()
            ->assign($this->team->getId(), $this->account->getId(), $this->orgId);

        // Store the value item
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->orgId,
                (new Model\PayloadValueItemCreate())
                    ->setDigest("The digest")
                    ->setDetails("The details")
                    ->setPriority(4)
            );
    }

    /**
     * Tear-down
     */
    public function tearDown(): void {
        // Remove the team
        $deleted = $this->sdk
            ->api()
            ->teams()
            ->delete($this->team->getId(), $this->orgId);
        $this->assertTrue($deleted);
    }

    /**
     * Create & Read
     */
    public function testCreateRead(): void {
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\PayloadAssmCreate())->setDigest("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Read
        $assmRead = $this->sdk
            ->api()
            ->assumptions()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $assm->getId(), $this->orgId);
        $this->assertInstanceOf(Model\Assumption::class, $assmRead);
        $this->assertEquals(0, count($assmRead->listProps()));

        $this->assertEquals($assm->getDigest(), $assmRead->getDigest());

        // List
        $assmList = $this->sdk
            ->api()
            ->assumptions()
            ->list($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);
        $this->assertInstanceOf(Model\AssumptionsList::class, $assmList);
        $this->assertEquals(0, count($assmList->listProps()));

        $this->assertIsArray($assmList->getAssumptions());
        $this->assertGreaterThan(0, count($assmList->getAssumptions()));
        $this->assertGreaterThanOrEqual(count($assmList->getAssumptions()), $assmList->getTotal());
    }

    /**
     * Update & delete
     */
    public function testUpdateDelete(): void {
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\PayloadAssmCreate())->setDigest("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Update assumption
        $assmUpdated = $this->sdk
            ->api()
            ->assumptions()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId,
                (new Model\PayloadAssmUpdate())->setDigest("New assumption")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assmUpdated);
        $this->assertEquals(0, count($assmUpdated->listProps()));
        $this->assertEquals("New assumption", $assmUpdated->getDigest());

        // Advance
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Validate assumption with experiment
        $assmUpdatedExp = $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId,
                (new Model\PayloadAssmExperiment())
                    ->setDigest("Experiment digest")
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );
        $this->assertInstanceOf(Model\Assumption::class, $assmUpdatedExp);
        $this->assertEquals(0, count($assmUpdatedExp->listProps()));

        $this->assertInstanceOf(Model\Experiment::class, $assmUpdatedExp->getExperiment());
        $this->assertEquals(0, count($assmUpdatedExp->getExperiment()->listProps()));

        $this->assertEquals("Experiment digest", $assmUpdatedExp->getExperiment()->getDigest());
        $this->assertEquals("Experiment details", $assmUpdatedExp->getExperiment()->getDetails());
        $this->assertTrue($assmUpdatedExp->getExperiment()->getConfirmed());
        $this->assertEquals(Model\PayloadAssmExperiment::STATE_DONE, $assmUpdatedExp->getExperiment()->getState());

        // Delete
        $deleted = $this->sdk
            ->api()
            ->assumptions()
            ->delete($this->team->getId(), $this->channel->getId(), $this->item->getId(), $assm->getId(), $this->orgId);
        $this->assertTrue($deleted);

        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->assumptions()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $assm->getId(), $this->orgId);
    }
}
