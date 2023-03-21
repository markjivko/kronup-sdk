<?php
/**
 * Deep Context Test
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
class DeepContextTest extends TestCase {
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
                    ->setDigest("The digest information here")
                    ->setDetails("The details")
                    ->setPriority(4)
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

        // Add notion to task
        $task = $this->sdk
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
        $this->assertInstanceOf(Model\Task::class, $task);
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

        // Validate notion
        $this->assertIsArray($taskUpdated->getNotions());
        $this->assertGreaterThanOrEqual(1, count($taskUpdated->getNotions()));
        $this->assertInstanceOf(Model\Notion::class, $taskUpdated->getNotions()[0]);
        $this->assertEquals(0, count($taskUpdated->getNotions()[0]->listProps()));
        $this->assertEquals($this->notion->getValue(), $taskUpdated->getNotions()[0]->getValue());

        // Advance
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->valueItemAdvance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);
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
     * Read
     */
    public function testRead(): void {
        // Fetch the item from deep context
        $item = $this->sdk
            ->api()
            ->deepContext()
            ->deepContextRead($this->item->getId(), $this->orgId);

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
            ->deepContextSearch($this->orgId, "digest");

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
            ->deepContextDelete($this->item->getId(), $this->orgId);
        $this->assertTrue($deleted);

        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->deepContext()
            ->deepContextRead($this->item->getId(), $this->orgId);
    }
}
