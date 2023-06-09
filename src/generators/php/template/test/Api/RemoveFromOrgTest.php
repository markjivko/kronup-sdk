<?php
/**
 * Remove from Organization Test
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
 * Remove from Organization Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class RemoveFromOrgTest extends TestCase {
    /**
     * kronup SDK
     *
     * @var \Kronup\Sdk
     */
    protected $sdk;

    /**
     * kronup SDK ran by the service account
     *
     * @var \Kronup\Sdk
     */
    protected $serviceSdk;

    /**
     * Account model
     *
     * @var Model\Account
     */
    protected $account;

    /**
     * Service account
     *
     * @var Model\ServiceAccount
     */
    protected $serviceAccount;

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

        // Remove organization if found
        if (count($this->account->getRoleOrg())) {
            $this->sdk->config()->setOrgId(current($this->account->getRoleOrg())->getOrgId());
            $deleted = $this->sdk
                ->api()
                ->organizations()
                ->delete($this->sdk->config()->getOrgId());
            $this->assertTrue($deleted);
        }

        // Create a new one
        $organization = $this->sdk
            ->api()
            ->organizations()
            ->create((new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc."));
        $this->assertInstanceOf(Model\Organization::class, $organization);
        $this->assertEquals(0, count($organization->listProps()));
        $this->sdk->config()->setOrgId($organization->getId());

        // Fetch teh service accounts
        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->list();
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));
        $this->assertIsArray($serviceAccountList->getServiceAccounts());

        // Prepare a service account
        $this->serviceAccount =
            0 !== count($serviceAccountList->getServiceAccounts())
                ? $serviceAccountList->getServiceAccounts()[0]
                : $this->sdk
                    ->api()
                    ->serviceAccounts()
                    ->create(
                        (new Model\PayloadServiceAccountCreate())
                            ->setRoleOrg(Model\PayloadServiceAccountCreate::ROLE_ORG_ADMIN)
                            ->setUserName("Service name")
                    );
        $this->assertInstanceOf(Model\ServiceAccount::class, $this->serviceAccount);
        $this->assertEquals(0, count($this->serviceAccount->listProps()));
        $this->serviceSdk = new Sdk($this->serviceAccount->getServiceToken());
    }

    /**
     * Remove the organization
     */
    public function tearDown(): void {
        $deleted = $this->sdk
            ->api()
            ->organizations()
            ->delete($this->sdk->config()->getOrgId());
        $this->assertTrue($deleted);
    }

    /**
     * Removed service account experiences
     */
    public function testExperiences() {
        // Create the notion
        $notion = $this->serviceSdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue("a notion " . mt_rand(1, 9999)));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));

        // Self-assess
        $experience = $this->serviceSdk
            ->api()
            ->experiences()
            ->evaluateSelf($notion->getId(), 5);
        $this->assertInstanceOf(Model\Experience::class, $experience);
        $this->assertEquals(0, count($experience->listProps()));

        // Assess the main account too
        $experienceMain = $this->sdk
            ->api()
            ->experiences()
            ->evaluateSelf($notion->getId(), 5);
        $this->assertInstanceOf(Model\Experience::class, $experienceMain);
        $this->assertEquals(0, count($experienceMain->listProps()));
    }

    public function testEvents() {
        // Create the notion
        $notion = $this->serviceSdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue("a notion " . mt_rand(1, 9999)));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));

        // Create a team
        $team = $this->serviceSdk
            ->api()
            ->teams()
            ->create((new Model\PayloadTeamCreate())->setTeamName("Test team"));
        $this->assertInstanceOf(Model\TeamExpanded::class, $team);
        $this->assertEquals(0, count($team->listProps()));

        // Create a channel
        $teamUpdated = $this->serviceSdk
            ->api()
            ->channels()
            ->create($team->getId(), (new Model\PayloadChannelCreate())->setChannelName("Test channel"));
        $this->assertInstanceOf(Model\TeamExpanded::class, $teamUpdated);
        $this->assertEquals(0, count($teamUpdated->listProps()));
        $channel = $teamUpdated->getChannels()[0];

        // Assign service account and main account to channel
        $assigned = $this->serviceSdk
            ->api()
            ->channels()
            ->assign($team->getId(), $channel->getId(), $this->serviceAccount->getId());
        $this->assertInstanceOf(Model\User::class, $assigned);
        $this->assertEquals(0, count($assigned->listProps()));
        $assigned = $this->serviceSdk
            ->api()
            ->channels()
            ->assign($team->getId(), $channel->getId(), $this->account->getId());
        $this->assertInstanceOf(Model\User::class, $assigned);
        $this->assertEquals(0, count($assigned->listProps()));

        // Create an item
        $item = $this->serviceSdk
            ->api()
            ->valueItems()
            ->create(
                $team->getId(),
                $channel->getId(),
                (new Model\PayloadValueItemCreate())->setHeading("A value item heading")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Add an assumption
        $assm = $this->serviceSdk
            ->api()
            ->assumptions()
            ->create(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                (new Model\PayloadAssmCreate())->setHeading("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Add second assumption
        $assm2 = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                (new Model\PayloadAssmCreate())->setHeading("Y can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm2);
        $this->assertEquals(0, count($assm2->listProps()));

        // Advance to validation
        $this->serviceSdk
            ->api()
            ->valueItems()
            ->advance($team->getId(), $channel->getId(), $item->getId());

        // Validate assumption with experiment
        $this->serviceSdk
            ->api()
            ->assumptions()
            ->experiment(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $assm->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Append to author IDs
        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $assm->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Experiment details 2")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );
        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $assm2->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Experiment 2 details 2")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Advance to execution
        $this->serviceSdk
            ->api()
            ->valueItems()
            ->advance($team->getId(), $channel->getId(), $item->getId());

        $task = $this->serviceSdk
            ->api()
            ->tasks()
            ->create(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Added notion
        $taskNotion = $this->serviceSdk
            ->api()
            ->tasks()
            ->update(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$notion->getId()])
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $taskNotion);
        $this->assertEquals(0, count($taskNotion->listProps()));

        $this->assertIsArray($taskNotion->getNotions());
        $this->assertEquals(1, count($taskNotion->getNotions()));

        // Fetch events
        $events = $this->serviceSdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $events);
        $this->assertEquals(0, count($events->listProps()));
        $this->assertIsArray($events->getEvents());

        // Found our self-eval event
        $this->assertEquals(1, count($events->getEvents()));
        $this->assertInstanceOf(Model\Event::class, $events->getEvents()[0]);
        $this->assertEquals(0, count($events->getEvents()[0]->listProps()));
        $this->assertEquals($notion->getId(), $events->getEvents()[0]->getNotionId());

        // Assign task to service account
        $task = $this->sdk
            ->api()
            ->tasks()
            ->assign($team->getId(), $channel->getId(), $item->getId(), $task->getId(), $this->serviceAccount->getId());
        $this->assertInstanceOf(Model\Task::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Add discoveries
        $this->serviceSdk
            ->api()
            ->tasks()
            ->discoveryCreate(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $task->getId(),
                (new Model\PayloadTaskDiscoveryCreate())->setDetails("Discovery 1")
            );
        $this->sdk
            ->api()
            ->tasks()
            ->discoveryCreate(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $task->getId(),
                (new Model\PayloadTaskDiscoveryCreate())->setDetails("Discovery 2")
            );

        // Add feedback
        $this->serviceSdk
            ->api()
            ->tasks()
            ->feedbackCreate(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $task->getId(),
                (new Model\PayloadTaskFeedbackCreate())->setMessage("Feedback 1")
            );
        $this->sdk
            ->api()
            ->tasks()
            ->feedbackCreate(
                $team->getId(),
                $channel->getId(),
                $item->getId(),
                $task->getId(),
                (new Model\PayloadTaskFeedbackCreate())->setMessage("Feedback 2")
            );

        // Delete the service account
        $serviceClosed = $this->sdk
            ->api()
            ->serviceAccounts()
            ->close($this->serviceAccount->getId());
        $this->assertInstanceOf(Model\ServiceAccount::class, $serviceClosed);
        $this->assertEquals(0, count($serviceClosed->listProps()));
        $this->assertNotEquals($this->serviceAccount->getServiceToken(), $serviceClosed->getServiceToken());

        // --------------------------
        // Confirm changes
        // --------------------------

        // Fetch new item
        $item = $this->sdk
            ->api()
            ->valueItems()
            ->read($team->getId(), $channel->getId(), $item->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $item);
        $this->assertEquals(0, count($item->listProps()));

        // Confirm change: Value item author
        $this->assertEquals($serviceClosed->getId(), $item->getAuthorUserId());

        // Confirm change: Assumption author
        $this->assertEquals($this->account->getId(), $item->getAssumptions()[0]->getAuthorUserId());
        $this->assertEquals($serviceClosed->getId(), $item->getAssumptions()[1]->getAuthorUserId());

        // Confirm change: Assumption experiment authors
        $this->assertContains(
            $serviceClosed->getId(),
            $item
                ->getAssumptions()[1]
                ->getExperiment()
                ->getAuthorUserIds()
        );
        $this->assertContains(
            $this->account->getId(),
            $item
                ->getAssumptions()[0]
                ->getExperiment()
                ->getAuthorUserIds()
        );

        // Fetch new task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->read($team->getId(), $channel->getId(), $item->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Confirm change: Task assignee
        $this->assertEquals($serviceClosed->getId(), $task->getAssigneeUserId());

        // Confirm change: Discovery author
        $this->assertEquals(
            $serviceClosed->getId(),
            $task
                ->getMinute()
                ->getDiscoveries()[0]
                ->getAuthorUserId()
        );
        $this->assertEquals(
            $this->account->getId(),
            $task
                ->getMinute()
                ->getDiscoveries()[1]
                ->getAuthorUserId()
        );

        // Confirm change: Feedback author
        $this->assertEquals(
            $serviceClosed->getId(),
            $task
                ->getMinute()
                ->getFeedback()[0]
                ->getAuthorUserId()
        );
        $this->assertEquals(
            $this->account->getId(),
            $task
                ->getMinute()
                ->getFeedback()[1]
                ->getAuthorUserId()
        );
    }
}
