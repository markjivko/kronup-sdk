<?php
/**
 * Deep Context Test
 *
 * @copyright (c) 2022-2023 kronup.io
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
class DeepContextTest extends TestCase {
    /**
     * Kronup sdk
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
                    ->setHeading("The heading information here")
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

        // Add notion to task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$this->notion->getId()])
            );
        $this->assertInstanceOf(Model\Task::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $this->sdk
            ->api()
            ->experiences()
            ->evaluate($this->notion->getId(), mt_rand(1, 10));

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
        $this->assertInstanceOf(Model\Task::class, $taskUpdated);
        $this->assertEquals(0, count($taskUpdated->listProps()));

        $this->assertEquals("New task title", $taskUpdated->getHeading());
        $this->assertEquals(Model\PayloadTaskUpdate::STATE_DONE, $taskUpdated->getState());

        // Fetch the expanded state
        $taskRead = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $taskRead);
        $this->assertEquals(0, count($taskRead->listProps()));
        $this->assertInstanceOf(Model\Minute::class, $taskRead->getMinute());
        $this->assertEquals(0, count($taskRead->getMinute()->listProps()));

        // Validate notion
        $this->assertIsArray($taskRead->getNotions());
        $this->assertGreaterThanOrEqual(1, count($taskRead->getNotions()));
        $this->assertInstanceOf(Model\Notion::class, $taskRead->getNotions()[0]);
        $this->assertEquals(0, count($taskRead->getNotions()[0]->listProps()));
        $this->assertEquals($this->notion->getValue(), $taskRead->getNotions()[0]->getValue());

        // Advance
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $this->item);
        $this->assertEquals(0, count($this->item->listProps()));
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
     * Read
     */
    public function testRead(): void {
        // Fetch the item from deep context
        $item = $this->sdk
            ->api()
            ->deepContext()
            ->read($this->item->getId());

        $this->assertInstanceOf(Model\ValueItemExpanded::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Assumptions
        $this->assertIsArray($item->getAssumptions());
        $this->assertInstanceOf(Model\Assumption::class, $item->getAssumptions()[0]);
        $this->assertEquals(0, count($item->getAssumptions()[0]->listProps()));
        $this->assertInstanceOf(Model\Experiment::class, $item->getAssumptions()[0]->getExperiment());
        $this->assertEquals(
            0,
            count(
                $item
                    ->getAssumptions()[0]
                    ->getExperiment()
                    ->listProps()
            )
        );

        // Tasks
        $this->assertIsArray($item->getTasks());
        $this->assertInstanceOf(Model\TaskExpanded::class, $item->getTasks()[0]);
        $this->assertEquals(0, count($item->getTasks()[0]->listProps()));

        // Task minutes
        $this->assertInstanceOf(Model\Minute::class, $item->getTasks()[0]->getMinute());
        $this->assertEquals(
            0,
            count(
                $item
                    ->getTasks()[0]
                    ->getMinute()
                    ->listProps()
            )
        );

        // Task notions
        $this->assertIsArray($item->getTasks()[0]->getNotions());
        $this->assertGreaterThanOrEqual(1, count($item->getTasks()[0]->getNotions()));
        $this->assertInstanceOf(Model\Notion::class, $item->getTasks()[0]->getNotions()[0]);
        $this->assertEquals(
            0,
            count(
                $item
                    ->getTasks()[0]
                    ->getNotions()[0]
                    ->listProps()
            )
        );
        $this->assertEquals(
            $this->notion->getValue(),
            $item
                ->getTasks()[0]
                ->getNotions()[0]
                ->getValue()
        );

        // Assert valid dates in the past
        $this->assertIsString($item->getUpdatedAt());
        $this->assertGreaterThan(0, strlen($item->getUpdatedAt()));
        $itemTime = strtotime($item->getUpdatedAt());
        $this->assertGreaterThan(0, $itemTime);

        $this->assertIsString($item->getCreatedAt());
        $this->assertGreaterThan(0, strlen($item->getCreatedAt()));
        $itemTime = strtotime($item->getCreatedAt());
        $this->assertGreaterThan(0, $itemTime);
    }

    /**
     * Search functionality
     */
    public function testSearch(): void {
        $search = $this->sdk
            ->api()
            ->deepContext()
            ->search("heading");

        $this->assertInstanceOf(Model\DeepContextList::class, $search);
        $this->assertEquals(0, count($search->listProps()));

        $this->assertIsArray($search->getItems());
        $this->assertGreaterThanOrEqual(1, count($search->getItems()));

        // Get the first item
        $item = $search->getItems()[0];

        // Tasks
        $this->assertIsArray($item->getTasks());
        $this->assertInstanceOf(Model\Task::class, $item->getTasks()[0]);
        $this->assertEquals(0, count($item->getTasks()[0]->listProps()));

        // Task notions
        $this->assertIsArray($item->getTasks()[0]->getNotionIds());
        $this->assertGreaterThanOrEqual(1, count($item->getTasks()[0]->getNotionIds()));
    }

    /**
     * Delete
     */
    public function testDelete(): void {
        $deleted = $this->sdk
            ->api()
            ->deepContext()
            ->delete($this->item->getId());
        $this->assertTrue($deleted);

        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->deepContext()
            ->read($this->item->getId());
    }
}
