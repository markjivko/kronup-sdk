<?php
/**
 * Feature Task Test
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
 * Task Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class FeatureTaskTest extends TestCase {
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
     * Team model
     *
     * @var Model\TeamExpanded
     */
    protected $team;

    /**
     * Channel model
     *
     * @var Model\Channel
     */
    protected $channel;

    /**
     * Feature
     *
     * @var Model\Feature
     */
    protected $feature;

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

        // Add feature
        $this->feature = $this->sdk
            ->api()
            ->features()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadFeatureCreate())
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
                $this->feature->getId(),
                (new Model\PayloadAssmCreate())->setHeading("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Advance to validation
        $this->sdk
            ->api()
            ->features()
            ->advance($this->team->getId(), $this->channel->getId(), $this->feature->getId());

        // Validate assumption with experiment
        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $this->team->getId(),
                $this->channel->getId(),
                $this->feature->getId(),
                $assm->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Advance to execution
        $this->sdk
            ->api()
            ->features()
            ->advance($this->team->getId(), $this->channel->getId(), $this->feature->getId());

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
                $this->feature->getId(),
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
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $read);
        $this->assertEquals(0, count($read->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $read->getMinute());
        $this->assertEquals(0, count($read->getMinute()->listProps()));

        $this->assertEquals($task->getHeading(), $read->getHeading());

        // Task List - must include minute
        $taskList = $this->sdk
            ->api()
            ->tasks()
            ->list($this->team->getId(), $this->channel->getId(), $this->feature->getId());
        $this->assertInstanceOf(Model\TasksList::class, $taskList);
        $this->assertEquals(0, count($taskList->listProps()));

        $this->assertIsArray($taskList->getTasks());
        $this->assertGreaterThan(0, count($taskList->getTasks()));
        $this->assertGreaterThanOrEqual(count($taskList->getTasks()), $taskList->getTotal());

        $this->assertInstanceOf(Model\Task::class, $taskList->getTasks()[0]);
        $this->assertEquals(0, count($taskList->getTasks()[0]->listProps()));

        // Features list - tasks must not have minutes
        $featureList = $this->sdk
            ->api()
            ->features()
            ->list($this->team->getId(), $this->channel->getId());
        $this->assertInstanceOf(Model\FeaturesList::class, $featureList);
        $this->assertEquals(0, count($featureList->listProps()));

        $this->assertIsArray($featureList->getFeatures());
        $this->assertGreaterThan(0, count($featureList->getFeatures()));
        $this->assertInstanceOf(Model\FeatureLite::class, $featureList->getFeatures()[0]);
        $this->assertEquals(0, count($featureList->getFeatures()[0]->listProps()));
        $this->assertIsArray($featureList->getFeatures()[0]->getTasks());
        $this->assertGreaterThan(0, count($featureList->getFeatures()[0]->getTasks()));
        $this->assertInstanceOf(Model\Task::class, $featureList->getFeatures()[0]->getTasks()[0]);
        $this->assertEquals(
            0,
            count(
                $featureList
                    ->getFeatures()[0]
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
                $this->feature->getId(),
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
                $this->feature->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())
                    ->setHeading("New task title")
                    ->setState(Model\PayloadTaskUpdate::STATE_DONE)
            );
        $this->assertInstanceOf(Model\Task::class, $taskUpdated);
        $this->assertEquals(0, count($taskUpdated->listProps()));

        $this->assertEquals("New task title", $taskUpdated->getHeading());
        $this->assertEquals(Model\PayloadTaskUpdate::STATE_DONE, $taskUpdated->getState());

        // Re-fetch the feature
        $this->feature = $this->sdk
            ->api()
            ->features()
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId());

        // Advance
        $this->sdk
            ->api()
            ->features()
            ->advance($this->team->getId(), $this->channel->getId(), $this->feature->getId());

        // Task can no longer be loaded (now in deep context)
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
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
                $this->feature->getId(),
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
                $this->feature->getId(),
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
                $this->feature->getId(),
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
                $this->feature->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->feature->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$this->notion->getId()])
            );
        $this->assertInstanceOf(Model\Task::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));
        $this->assertIsArray($taskNotion->getNotionIds());
        $this->assertEquals(1, count($taskNotion->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(1, count($task->getNotions()));

        $this->assertInstanceOf(Model\Notion::class, $task->getNotions()[0]);
        $this->assertEquals(0, count($task->getNotions()[0]->listProps()));

        $this->assertEquals($this->notion->getValue(), $task->getNotions()[0]->getValue());

        // Remove the notion
        $taskEmptyNotions = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->feature->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([])
            );
        $this->assertInstanceOf(Model\Task::class, $taskEmptyNotions);
        $this->assertEquals(0, count($taskEmptyNotions->listProps()));
        $this->assertIsArray($taskEmptyNotions->getNotionIds());
        $this->assertEquals(0, count($taskEmptyNotions->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $this->assertIsArray($task->getNotions());
        $this->assertEquals(0, count($task->getNotions()));
    }

    /**
     * Attempt to assign more notions than allowed
     */
    public function testNotionsLimit(): void {
        $task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->feature->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task two")->setDetails("Details of task two")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Prepare the notions
        $notionIds = [];
        for ($i = 1; $i <= 26; $i++) {
            $notion = $this->sdk
                ->api()
                ->notions()
                ->create((new Model\PayloadNotionCreate())->setValue(uniqid()));
            $this->assertInstanceOf(Model\Notion::class, $notion);
            $this->assertEquals(0, count($notion->listProps()));
            $notionIds[] = $notion->getId();
        }

        try {
            $this->sdk
                ->api()
                ->tasks()
                ->update(
                    $this->team->getId(),
                    $this->channel->getId(),
                    $this->feature->getId(),
                    $task->getId(),
                    (new Model\PayloadTaskUpdate())->setNotionIds($notionIds)
                );
            $this->fail("Shold have failed updating task notions");
        } catch (\Exception $exc) {
            $this->assertInstanceOf(ApiException::class, $exc);
            $this->assertEquals("limit-reached", $exc->getResponseObject()["id"]);
        }

        // Remove the notions
        foreach ($notionIds as $notionId) {
            $deleted = $this->sdk
                ->api()
                ->notions()
                ->delete($notionId);
            $this->assertTrue($deleted);
        }

        // Remove the task
        $taskDeleted = $this->sdk
            ->api()
            ->tasks()
            ->delete($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
        $this->assertTrue($taskDeleted);
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
                $this->feature->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->feature->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$notion->getId()])
            );
        $this->assertInstanceOf(Model\Task::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));

        $this->assertIsArray($taskNotion->getNotionIds());
        $this->assertEquals(1, count($taskNotion->getNotionIds()));

        // Read the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
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
            ->read($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getNotions());
        $this->assertEquals(0, count($task->getNotions()));
    }
}
