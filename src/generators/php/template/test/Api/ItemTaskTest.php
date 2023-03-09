<?php
/**
 * Value Item Task Test
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
 * Task Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ItemTaskTest extends TestCase {
    /**
     * kronup sdk
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

        // Add value item
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

        // Add an assumption
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

        // Advance to validation
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Validate assumption with experiment
        $this->sdk
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

        // Advance to execution
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);
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
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\RequestTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $task->getMinute());
        $this->assertEquals(0, count($task->getMinute()->listProps()));

        // Read
        $taskRead = $this->sdk
            ->api()
            ->tasks()
            ->taskRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $taskRead);
        $this->assertEquals(0, count($taskRead->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $taskRead->getMinute());
        $this->assertEquals(0, count($taskRead->getMinute()->listProps()));

        $this->assertEquals($task->getDigest(), $taskRead->getDigest());

        // Task List - must include minute
        $taskList = $this->sdk
            ->api()
            ->tasks()
            ->taskList($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);
        $this->assertInstanceOf(Model\TasksList::class, $taskList);
        $this->assertEquals(0, count($taskList->listProps()));

        $this->assertIsArray($taskList->getTasks());
        $this->assertGreaterThan(0, count($taskList->getTasks()));
        $this->assertInstanceOf(Model\Task::class, $taskList->getTasks()[0]);
        $this->assertEquals(0, count($taskList->getTasks()[0]->listProps()));

        // Item list - tasks must not have minutes
        $itemList = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemList($this->team->getId(), $this->channel->getId(), $this->orgId);
        $this->assertInstanceOf(Model\ValueItemsList::class, $itemList);
        $this->assertEquals(0, count($itemList->listProps()));

        $this->assertIsArray($itemList->getItems());
        $this->assertGreaterThan(0, count($itemList->getItems()));
        $this->assertIsArray($itemList->getItems()[0]->getTasks());
        $this->assertGreaterThan(0, count($itemList->getItems()[0]->getTasks()));
        $this->assertInstanceOf(Model\Task::class, $itemList->getItems()[0]->getTasks()[0]);
        $this->assertEquals(
            0,
            count(
                $itemList
                    ->getItems()[0]
                    ->getTasks()[0]
                    ->listProps()
            )
        );
    }

    /**
     * Update & delete
     */
    public function testUpdateDelete(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\RequestTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Update task
        $taskUpdated = $this->sdk
            ->api()
            ->tasks()
            ->taskUpdate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId,
                (new Model\RequestTaskUpdate())->setDigest("New task title")->setState(Model\RequestTaskUpdate::STATE_D)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $taskUpdated);
        $this->assertEquals(0, count($taskUpdated->listProps()));

        $this->assertEquals("New task title", $taskUpdated->getDigest());
        $this->assertEquals(Model\RequestTaskUpdate::STATE_D, $taskUpdated->getState());
        $this->assertInstanceOf(Model\Minute::class, $taskUpdated->getMinute());
        $this->assertEquals(0, count($taskUpdated->getMinute()->listProps()));

        // Advance
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Delete not allowed in deep context
        $this->expectExceptionObject(new ApiException("Forbidden", 403));
        $this->sdk
            ->api()
            ->tasks()
            ->taskDelete(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId
            );
    }

    /**
     * Create minute
     */
    public function testMinuteCreateRead(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\RequestTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $task->getMinute());
        $this->assertEquals(0, count($task->getMinute()->listProps()));
    }

    /**
     * Assign user to task
     */
    public function testAssign(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\RequestTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Assign the task
        $taskAssigned = $this->sdk
            ->api()
            ->tasks()
            ->taskAssign(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->account->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\Task::class, $taskAssigned);
        $this->assertEquals(0, count($taskAssigned->listProps()));

        $this->assertIsString($taskAssigned->getAssigneeId());
        $this->assertEquals($this->account->getId(), $taskAssigned->getAssigneeId());
    }
}
