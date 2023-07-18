<?php
/**
 * Value Item Task Minute Test
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
class ItemTaskMinuteTest extends TestCase {
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
     * Task
     *
     * @var Model\Task
     */
    protected $task;

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

        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId());

        // Prepare the notion
        $this->notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue("notion-" . mt_rand(1, 999)));
        $this->assertInstanceOf(Model\Notion::class, $this->notion);
        $this->assertEquals(0, count($this->notion->listProps()));

        // Prepare the task
        $this->task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $this->task);
        $this->assertEquals(0, count($this->task->listProps()));
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
     * Test discoveries (create/update/delete)
     */
    public function testDiscovery() {
        $discoveryText = "A new discovery";
        $discoveryTextUpdated = "The new discovery";

        // Create discovery
        $discovery = $this->sdk
            ->api()
            ->tasks()
            ->discoveryCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                (new Model\PayloadTaskDiscoveryCreate())->setDetails($discoveryText)
            );
        $this->assertInstanceOf(Model\MinuteDiscovery::class, $discovery);
        $this->assertEquals(0, count($discovery->listProps()));

        $this->assertEquals($discoveryText, $discovery->getDetails());

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $this->assertEquals(
            $discoveryText,
            $task
                ->getMinute()
                ->getDiscoveries()[0]
                ->getDetails()
        );

        // Update discovery
        $discovery = $this->sdk
            ->api()
            ->tasks()
            ->discoveryUpdate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $task
                    ->getMinute()
                    ->getDiscoveries()[0]
                    ->getId(),
                (new Model\PayloadTaskDiscoveryUpdate())->setDetails($discoveryTextUpdated)
            );
        $this->assertInstanceOf(Model\MinuteDiscovery::class, $discovery);
        $this->assertEquals(0, count($discovery->listProps()));
        $this->assertEquals($discoveryTextUpdated, $discovery->getDetails());

        // Delete the discovery
        $deleted = $this->sdk
            ->api()
            ->tasks()
            ->discoveryDelete(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $task
                    ->getMinute()
                    ->getDiscoveries()[0]
                    ->getId()
            );
        $this->assertTrue($deleted);

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getMinute()->getDiscoveries());
        $this->assertEquals(0, count($task->getMinute()->getDiscoveries()));
    }

    /**
     * Test feedback (create/update/delete)
     */
    public function testFeedback() {
        $feedbackMessage = "My feedback";
        $feedbackMessageUpdated = "My updated feedback";
        $feedbackReply = "My reply";

        // Create feedback
        $feedback = $this->sdk
            ->api()
            ->tasks()
            ->feedbackCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                (new Model\PayloadTaskFeedbackCreate())
                    ->setMessage($feedbackMessage)
                    ->setIssue(Model\PayloadTaskFeedbackCreate::ISSUE_VALUE)
            );
        $this->assertInstanceOf(Model\MinuteFeedback::class, $feedback);
        $this->assertEquals(0, count($feedback->listProps()));
        $this->assertEquals($feedbackMessage, $feedback->getMessage());

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $this->assertEquals(
            $feedbackMessage,
            $task
                ->getMinute()
                ->getFeedback()[0]
                ->getMessage()
        );

        // Update feedback
        $feedback = $this->sdk
            ->api()
            ->tasks()
            ->feedbackUpdate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $task
                    ->getMinute()
                    ->getFeedback()[0]
                    ->getId(),
                (new Model\PayloadTaskFeedbackUpdate())
                    ->setMessage($feedbackMessageUpdated)
                    ->setIssue(Model\PayloadTaskFeedbackUpdate::ISSUE_MISC)
            );
        $this->assertInstanceOf(Model\MinuteFeedback::class, $feedback);
        $this->assertEquals(0, count($feedback->listProps()));
        $this->assertEquals($feedbackMessageUpdated, $feedback->getMessage());
        $this->assertEquals(Model\PayloadTaskFeedbackUpdate::ISSUE_MISC, $feedback->getIssue());

        // Reply
        $feedback = $this->sdk
            ->api()
            ->tasks()
            ->feedbackReply(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $task
                    ->getMinute()
                    ->getFeedback()[0]
                    ->getId(),
                (new Model\PayloadTaskFeedbackReply())->setReply($feedbackReply)
            );
        $this->assertInstanceOf(Model\MinuteFeedback::class, $feedback);
        $this->assertEquals(0, count($task->listProps()));
        $this->assertEquals($feedbackReply, $feedback->getReply());

        // Delete the feedback
        $deleted = $this->sdk
            ->api()
            ->tasks()
            ->feedbackDelete(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $task
                    ->getMinute()
                    ->getFeedback()[0]
                    ->getId()
            );
        $this->assertTrue($deleted);

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getMinute()->getFeedback());
        $this->assertEquals(0, count($task->getMinute()->getFeedback()));
    }
}
