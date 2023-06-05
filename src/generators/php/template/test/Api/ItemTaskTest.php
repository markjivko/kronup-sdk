<?php
/**
 * Value Item Task Test
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
     * Notion
     *
     * @var Model\Notion
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

        // Add value item
        $this->item = $this->sdk
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

        // Add an assumption
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadAssmCreate())->setHeading("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Advance to validation
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId());

        // Validate assumption with experiment
        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Advance to execution
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId());

        // Prepare the notion
        $this->notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue("notion-" . mt_rand(1, 999)));
        $this->assertInstanceOf(Model\Notion::class, $this->notion);
        $this->assertEquals(0, count($this->notion->listProps()));
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

        // Remove the notion
        $deletedNotion = $this->sdk
            ->api()
            ->notions()
            ->delete($this->notion->getId());
        $this->assertTrue($deletedNotion);
    }

    /**
     * Create & Read
     */
    public function testCreateRead(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $task->getMinute());
        $this->assertEquals(0, count($task->getMinute()->listProps()));

        // Read
        $read = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $read);
        $this->assertEquals(0, count($read->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $read->getMinute());
        $this->assertEquals(0, count($read->getMinute()->listProps()));

        $this->assertEquals($task->getHeading(), $read->getHeading());

        // Task List - must include minute
        $taskList = $this->sdk
            ->api()
            ->tasks()
            ->list($this->team->getId(), $this->channel->getId(), $this->item->getId());
        $this->assertInstanceOf(Model\TasksList::class, $taskList);
        $this->assertEquals(0, count($taskList->listProps()));

        $this->assertIsArray($taskList->getTasks());
        $this->assertGreaterThan(0, count($taskList->getTasks()));
        $this->assertGreaterThanOrEqual(count($taskList->getTasks()), $taskList->getTotal());

        $this->assertInstanceOf(Model\Task::class, $taskList->getTasks()[0]);
        $this->assertEquals(0, count($taskList->getTasks()[0]->listProps()));

        // Item list - tasks must not have minutes
        $itemList = $this->sdk
            ->api()
            ->valueItems()
            ->list($this->team->getId(), $this->channel->getId());
        $this->assertInstanceOf(Model\ValueItemsList::class, $itemList);
        $this->assertEquals(0, count($itemList->listProps()));

        $this->assertIsArray($itemList->getItems());
        $this->assertGreaterThan(0, count($itemList->getItems()));
        $this->assertInstanceOf(Model\ValueItemLite::class, $itemList->getItems()[0]);
        $this->assertEquals(0, count($itemList->getItems()[0]->listProps()));
        $this->assertIsArray($itemList->getItems()[0]->getTasks());
        $this->assertGreaterThan(0, count($itemList->getItems()[0]->getTasks()));
        $this->assertInstanceOf(Model\TaskLite::class, $itemList->getItems()[0]->getTasks()[0]);
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
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Update task
        $taskUpdated = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())
                    ->setHeading("New task title")
                    ->setState(Model\PayloadTaskUpdate::STATE_DONE)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $taskUpdated);
        $this->assertEquals(0, count($taskUpdated->listProps()));

        $this->assertEquals("New task title", $taskUpdated->getHeading());
        $this->assertEquals(Model\PayloadTaskUpdate::STATE_DONE, $taskUpdated->getState());
        $this->assertInstanceOf(Model\Minute::class, $taskUpdated->getMinute());
        $this->assertEquals(0, count($taskUpdated->getMinute()->listProps()));

        // Re-fetch the item
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId());

        // Advance
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId());

        // Task can no longer be loaded (now in deep context)
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
    }

    /**
     * Create minute
     */
    public function testMinuteCreateRead(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
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
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Assign the task
        $taskAssigned = $this->sdk
            ->api()
            ->tasks()
            ->assign(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->account->getId()
            );
        $this->assertInstanceOf(Model\Task::class, $taskAssigned);
        $this->assertEquals(0, count($taskAssigned->listProps()));

        $this->assertIsString($taskAssigned->getAssigneeUserId());
        $this->assertEquals($this->account->getId(), $taskAssigned->getAssigneeUserId());
    }

    /**
     * Notion add and remove
     */
    public function testNotions(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->sdk
            ->api()
            ->tasks()
            ->notionAdd(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->notion->getId()
            );
        $this->assertInstanceOf(Model\Task::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));

        $this->assertIsArray($taskNotion->getNotionIds());
        $this->assertEquals(1, count($taskNotion->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(1, count($task->getNotions()));

        $this->assertInstanceOf(Model\Notion::class, $task->getNotions()[0]);
        $this->assertEquals(0, count($task->getNotions()[0]->listProps()));

        $this->assertEquals($this->notion->getValue(), $task->getNotions()[0]->getValue());

        // Remove the notion
        $removed = $this->sdk
            ->api()
            ->tasks()
            ->notionRemove(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->notion->getId()
            );
        $this->assertTrue($removed);

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(0, count($task->getNotions()));
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

        $task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->sdk
            ->api()
            ->tasks()
            ->notionAdd(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $notion->getId()
            );
        $this->assertInstanceOf(Model\Task::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));

        $this->assertIsArray($taskNotion->getNotionIds());
        $this->assertEquals(1, count($taskNotion->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(1, count($task->getNotions()));

        $this->assertInstanceOf(Model\Notion::class, $task->getNotions()[0]);
        $this->assertEquals(0, count($task->getNotions()[0]->listProps()));

        $this->assertEquals($notion->getValue(), $task->getNotions()[0]->getValue());

        // Delete the notion
        $deleted = $this->sdk
            ->api()
            ->notions()
            ->delete($notion->getId());
        $this->assertTrue($deleted);

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(0, count($task->getNotions()));
    }
}
