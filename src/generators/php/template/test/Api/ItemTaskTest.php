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

        // Add value item
        $this->item = $this->sdk
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

        // Add an assumption
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->assumptionCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\PayloadAssmCreate())->setDigest("X can be done")
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
            ->assumptionExperiment(
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

        // Advance to execution
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Prepare the notion
        $this->notion = $this->sdk
            ->api()
            ->notions()
            ->notionCreate($this->orgId, (new Model\PayloadNotionCreate())->setValue("notion-" . mt_rand(1, 999)));
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
            ->teamDelete($this->team->getId(), $this->orgId);
        $this->assertTrue($deleted);

        // Remove the notion
        $deletedNotion = $this->sdk
            ->api()
            ->notions()
            ->notionDelete($this->notion->getId(), $this->orgId);
        $this->assertTrue($deletedNotion);
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
                (new Model\PayloadTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
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
        $this->assertGreaterThanOrEqual(count($taskList->getTasks()), $taskList->getTotal());

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
                (new Model\PayloadTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
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
                (new Model\PayloadTaskUpdate())
                    ->setDigest("New task title")
                    ->setState(Model\PayloadTaskUpdate::STATE_DONE)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $taskUpdated);
        $this->assertEquals(0, count($taskUpdated->listProps()));

        $this->assertEquals("New task title", $taskUpdated->getDigest());
        $this->assertEquals(Model\PayloadTaskUpdate::STATE_DONE, $taskUpdated->getState());
        $this->assertInstanceOf(Model\Minute::class, $taskUpdated->getMinute());
        $this->assertEquals(0, count($taskUpdated->getMinute()->listProps()));

        // Re-fetch the item
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemRead($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Advance
        $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Task can no longer be loaded (now in deep context)
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->tasks()
            ->taskRead(
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
                (new Model\PayloadTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
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
                (new Model\PayloadTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
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
            ->taskCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\PayloadTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->sdk
            ->api()
            ->tasks()
            ->taskNotionAdd(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->notion->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\Task::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));

        $this->assertIsArray($taskNotion->getNotionIds());
        $this->assertEquals(1, count($taskNotion->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId
            );
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
            ->taskNotionRemove(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->notion->getId(),
                $this->orgId
            );
        $this->assertTrue($removed);

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId
            );
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
            ->notionCreate($this->orgId, (new Model\PayloadNotionCreate())->setValue("new-notion-" . mt_rand(1, 999)));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));

        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->orgId,
                (new Model\PayloadTaskCreate())->setDigest("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->sdk
            ->api()
            ->tasks()
            ->taskNotionAdd(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $notion->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\Task::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));

        $this->assertIsArray($taskNotion->getNotionIds());
        $this->assertEquals(1, count($taskNotion->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId
            );
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
            ->notionDelete($notion->getId(), $this->orgId);
        $this->assertTrue($deleted);

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->taskRead(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(0, count($task->getNotions()));
    }
}
