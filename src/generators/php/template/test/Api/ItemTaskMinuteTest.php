<?php
/**
 * Value Item Task Minute Test
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
class ItemTaskMinuteTest extends TestCase {
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

        // Add value item
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->orgId,
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
                $this->orgId,
                (new Model\PayloadAssmCreate())->setHeading("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Advance to validation
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Validate assumption with experiment
        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm->getId(),
                $this->orgId,
                (new Model\PayloadAssmExperiment())
                    ->setHeading("Experiment heading")
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Advance to execution
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $this->item->getId(), $this->orgId);

        // Prepare the notion
        $this->notion = $this->sdk
            ->api()
            ->notions()
            ->create($this->orgId, (new Model\PayloadNotionCreate())->setValue("notion-" . mt_rand(1, 999)));
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
                $this->orgId,
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
            ->delete($this->team->getId(), $this->orgId);
        $this->assertTrue($deleted);

        // Remove the notion
        $deletedNotion = $this->sdk
            ->api()
            ->notions()
            ->delete($this->notion->getId(), $this->orgId);
        $this->assertTrue($deletedNotion);
    }

    /**
     * Test discoveries (create/update/delete)
     */
    public function testDiscovery() {
        $discoveryText = "A new discovery";
        $discoveryTextUpdated = "The new discovery";

        // Create discovery
        $task = $this->sdk
            ->api()
            ->tasks()
            ->discoveryCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $this->orgId,
                (new Model\PayloadTaskDiscoveryCreate())->setDetails($discoveryText)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $task->getMinute());
        $this->assertEquals(0, count($task->getMinute()->listProps()));

        $this->assertIsArray($task->getMinute()->getDiscoveries());
        $this->assertEquals(1, count($task->getMinute()->getDiscoveries()));

        $this->assertInstanceOf(Model\MinuteDiscovery::class, $task->getMinute()->getDiscoveries()[0]);
        $this->assertEquals(
            0,
            count(
                $task
                    ->getMinute()
                    ->getDiscoveries()[0]
                    ->listProps()
            )
        );

        $this->assertEquals(
            $discoveryText,
            $task
                ->getMinute()
                ->getDiscoveries()[0]
                ->getDetails()
        );

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $this->orgId
            );
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
        $task = $this->sdk
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
                $this->orgId,
                (new Model\PayloadTaskDiscoveryUpdate())->setDetails($discoveryTextUpdated)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $this->assertEquals(
            $discoveryTextUpdated,
            $task
                ->getMinute()
                ->getDiscoveries()[0]
                ->getDetails()
        );

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
                    ->getId(),
                $this->orgId
            );
        $this->assertTrue($deleted);

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $this->orgId
            );
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
        $feedbackReplyUpdated = "My updated reply";

        // Create feedback
        $task = $this->sdk
            ->api()
            ->tasks()
            ->feedbackCreate(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $this->orgId,
                (new Model\PayloadTaskFeedbackCreate())
                    ->setMessage($feedbackMessage)
                    ->setIssue(Model\PayloadTaskFeedbackCreate::ISSUE_VALUE)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertInstanceOf(Model\Minute::class, $task->getMinute());
        $this->assertEquals(0, count($task->getMinute()->listProps()));

        $this->assertIsArray($task->getMinute()->getFeedback());
        $this->assertEquals(1, count($task->getMinute()->getFeedback()));

        $this->assertInstanceOf(Model\MinuteFeedback::class, $task->getMinute()->getFeedback()[0]);
        $this->assertEquals(
            0,
            count(
                $task
                    ->getMinute()
                    ->getFeedback()[0]
                    ->listProps()
            )
        );

        $this->assertEquals(
            $feedbackMessage,
            $task
                ->getMinute()
                ->getFeedback()[0]
                ->getMessage()
        );

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $this->orgId
            );
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
        $task = $this->sdk
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
                $this->orgId,
                (new Model\PayloadTaskFeedbackUpdate())
                    ->setMessage($feedbackMessageUpdated)
                    ->setIssue(Model\PayloadTaskFeedbackUpdate::ISSUE_MISC)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertEquals(
            $feedbackMessageUpdated,
            $task
                ->getMinute()
                ->getFeedback()[0]
                ->getMessage()
        );
        $this->assertEquals(
            Model\PayloadTaskFeedbackUpdate::ISSUE_MISC,
            $task
                ->getMinute()
                ->getFeedback()[0]
                ->getIssue()
        );

        // Reply
        $task = $this->sdk
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
                $this->orgId,
                (new Model\PayloadTaskFeedbackReply())->setReply($feedbackReply)
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $this->assertEquals(
            $feedbackReply,
            $task
                ->getMinute()
                ->getFeedback()[0]
                ->getReply()
        );

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
                    ->getId(),
                $this->orgId
            );
        $this->assertTrue($deleted);

        // Fetch the task again
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $this->task->getId(),
                $this->orgId
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        $this->assertIsArray($task->getMinute()->getFeedback());
        $this->assertEquals(0, count($task->getMinute()->getFeedback()));
    }
}
