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

        // Store the value item
        $this->item = $this->sdk
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
     * Create & Read
     */
    public function testCreateRead(): void {
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\RequestAssmCreate())->setDigest("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Read
        $assmRead = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\Assumption::class, $assmRead);
        $this->assertEquals(0, count($assmRead->listProps()));

        $this->assertEquals($assm->getDigest(), $assmRead->getDigest());

        // List
        $assmList = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionList($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);
        $this->assertInstanceOf(Model\AssumptionsList::class, $assmList);
        $this->assertEquals(0, count($assmList->listProps()));

        $this->assertIsArray($assmList->getAssumptions());
        $this->assertGreaterThan(0, count($assmList->getAssumptions()));
    }

    /**
     * Update & delete
     */
    public function testUpdateDelete(): void {
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\RequestAssmCreate())->setDigest("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Update assumption
        $assmUpdated = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionUpdate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId,
                (new Model\RequestAssmUpdate())->setDigest("New assumption")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assmUpdated);
        $this->assertEquals(0, count($assmUpdated->listProps()));
        $this->assertEquals("New assumption", $assmUpdated->getDigest());

        // Advance
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Validate assumption with experiment
        $assmUpdatedExp = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionValidate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId,
                (new Model\RequestAssmValidate())
                    ->setDigest("Experiment digest")
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\RequestAssmValidate::STATE_D)
            );
        $this->assertInstanceOf(Model\Assumption::class, $assmUpdatedExp);
        $this->assertEquals(0, count($assmUpdatedExp->listProps()));

        $this->assertInstanceOf(Model\AssumptionExperiment::class, $assmUpdatedExp->getExperiment());
        $this->assertEquals(0, count($assmUpdatedExp->getExperiment()->listProps()));

        $this->assertEquals("Experiment digest", $assmUpdatedExp->getExperiment()->getDigest());
        $this->assertEquals("Experiment details", $assmUpdatedExp->getExperiment()->getDetails());
        $this->assertTrue($assmUpdatedExp->getExperiment()->getConfirmed());
        $this->assertEquals(Model\RequestAssmValidate::STATE_D, $assmUpdatedExp->getExperiment()->getState());

        // Delete
        $deleted = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionDelete(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId
            );
        $this->assertTrue($deleted);

        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->assumptions()
            ->assumptionRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId
            );
    }
}
