<?php
/**
 * Event Test
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
 * Event Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class EventTest extends TestCase {
    /**
     * Kronup SDK
     *
     * @var \Kronup\Sdk
     */
    protected $sdk;

    /**
     * Kronup SDK for the Service Account
     *
     * @var \Kronup\Sdk
     */
    protected $sdkService;

    /**
     * Service Account model
     *
     * @var Model\ServiceAccount
     */
    protected $serviceAccount;

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

        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->list();
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));
        $this->assertIsArray($serviceAccountList->getServiceAccounts());

        $this->serviceAccount =
            0 !== count($serviceAccountList->getServiceAccounts())
                ? $serviceAccountList->getServiceAccounts()[0]
                : $this->sdk
                    ->api()
                    ->serviceAccounts()
                    ->create(
                        (new Model\PayloadServiceAccountCreate())
                            ->setRoleOrg(Model\PayloadServiceAccountCreate::ROLE_ORG_ADMIN)
                            ->setUserName("New account name")
                    );

        // Store ths Service Account API
        $this->sdkService = new Sdk($this->serviceAccount->getServiceToken());

        // Create the team
        $this->team = $this->sdk
            ->api()
            ->teams()
            ->create((new Model\PayloadTeamCreate())->setTeamName("New team"));
        $this->assertInstanceOf(Model\TeamExpanded::class, $this->team);
        $this->assertEquals(0, count($this->team->listProps()));

        // Store the default channel
        $this->channel = $this->team->getChannels()[0];

        // Subscribe first account
        $user = $this->sdk
            ->api()
            ->channels()
            ->assign($this->team->getId(), $this->channel->getId(), $this->account->getId());
        $this->assertInstanceOf(Model\User::class, $user);
        $this->assertEquals(0, count($user->listProps()));
        $this->assertIsArray($user->getTeams());
        $this->assertGreaterThanOrEqual(1, count($user->getTeams()));
        $this->assertInstanceOf(Model\UserTeam::class, $user->getTeams()[0]);
        $this->assertEquals(0, count($user->getTeams()[0]->listProps()));
        $this->assertEquals($user->getTeams()[0]->getTeamId(), $this->team->getId());

        // Re-fetch account
        $this->account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Subscribe service account
        $user = $this->sdk
            ->api()
            ->channels()
            ->assign($this->team->getId(), $this->channel->getId(), $this->serviceAccount->getId());
        $this->assertInstanceOf(Model\User::class, $user);
        $this->assertEquals(0, count($user->listProps()));
        $this->assertIsArray($user->getTeams());
        $this->assertGreaterThanOrEqual(1, count($user->getTeams()));
        $this->assertInstanceOf(Model\UserTeam::class, $user->getTeams()[0]);
        $this->assertEquals(0, count($user->getTeams()[0]->listProps()));
        $this->assertEquals($user->getTeams()[0]->getTeamId(), $this->team->getId());

        // Re-fetch service account
        $serviceAccountList = $this->sdk
            ->api()
            ->serviceAccounts()
            ->list();
        $this->assertInstanceOf(Model\ServiceAccountsList::class, $serviceAccountList);
        $this->assertEquals(0, count($serviceAccountList->listProps()));
        $this->assertIsArray($serviceAccountList->getServiceAccounts());
        $this->serviceAccount = $serviceAccountList->getServiceAccounts()[0];
        $this->assertInstanceOf(Model\ServiceAccount::class, $this->serviceAccount);
        $this->assertEquals(0, count($this->serviceAccount->listProps()));
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
     * Create a value item and check event list for author and other team member
     *
     * @return Model\ValueItem
     */
    protected function _getValueItem() {
        // Create a value item
        $valueItem = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadValueItemCreate())->setHeading("A test item")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $valueItem);
        $this->assertEquals(0, count($valueItem->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Read the item
        $valueItemService = $this->sdkService
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $valueItemService);
        $this->assertEquals(0, count($valueItemService->listProps()));

        // Re-fetch events
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        return $valueItem;
    }

    /**
     * Test create/update value item assumptions
     *
     * @return array Model\ValueItem, Model\TaskExpanded
     */
    protected function _getTask() {
        $valueItem = $this->_getValueItem();

        // Create the assumption
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                (new Model\PayloadAssmCreate())->setHeading("Assumption heading")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Advance the value item
        $valueItem = $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_ITEMS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("stage", $eventsList->getEvents()[0]->getDiff());

        // Read the item
        $valueItemService = $this->sdkService
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $valueItemService);
        $this->assertEquals(0, count($valueItemService->listProps()));

        // Validate the assumption
        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                $assm->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("All good")
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_ASSUMPTIONS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("state", $eventsList->getEvents()[0]->getDiff());
        $this->assertContains("details", $eventsList->getEvents()[0]->getDiff());

        // Advance again
        $valueItem = $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_ITEMS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("stage", $eventsList->getEvents()[0]->getDiff());

        // Read the item
        $valueItemService = $this->sdkService
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $valueItemService);
        $this->assertEquals(0, count($valueItemService->listProps()));

        // --- TASKS ---
        $task = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task heading")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));
        $taskUpdated = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setHeading("Task heading update")->setDetails("Task details update")
            );
        $this->assertInstanceOf(Model\Task::class, $taskUpdated);
        $this->assertEquals(0, count($taskUpdated->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_TASKS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("heading", $eventsList->getEvents()[0]->getDiff());
        $this->assertContains("details", $eventsList->getEvents()[0]->getDiff());

        // Read the task
        $task = $this->sdkService
            ->api()
            ->tasks()
            ->read($this->team->getId(), $this->channel->getId(), $valueItem->getId(), $task->getId());
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Re-fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        return [$valueItem, $task];
    }

    /**
     * Test create/update value item
     */
    public function testAddRemoveItem(): void {
        // Create a value item
        $valueItem = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadValueItemCreate())->setHeading("A test item")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $valueItem);
        $this->assertEquals(0, count($valueItem->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_ITEMS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("heading", $eventsList->getEvents()[0]->getDiff());

        // Update the value item
        $valueItem = $this->sdk
            ->api()
            ->valueItems()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                (new Model\PayloadValueItemUpdate())->setHeading("Another heading")->setDetails("Value item details")
            );
        $this->assertInstanceOf(Model\ValueItem::class, $valueItem);
        $this->assertEquals(0, count($valueItem->listProps()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_ITEMS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("heading", $eventsList->getEvents()[0]->getDiff());
        $this->assertContains("details", $eventsList->getEvents()[0]->getDiff());

        // Read the item
        $valueItemService = $this->sdkService
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $valueItemService);
        $this->assertEquals(0, count($valueItemService->listProps()));

        // Re-fetch events
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));
    }

    /**
     * Test create/update value item assumptions
     */
    public function testAddRemoveItemAssm(): void {
        $valueItem = $this->_getValueItem();

        // Create the assumption
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                (new Model\PayloadAssmCreate())->setHeading("Assumption heading")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals($eventsList->getEvents()[0]->getItemId(), $valueItem->getId());
        $this->assertEquals(Model\Event::TYPE_ASSUMPTIONS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("heading", $eventsList->getEvents()[0]->getDiff());

        // Read the item
        $valueItemService = $this->sdkService
            ->api()
            ->valueItems()
            ->read($this->team->getId(), $this->channel->getId(), $valueItem->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $valueItemService);
        $this->assertEquals(0, count($valueItemService->listProps()));

        // Re-fetch events
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));
    }

    /**
     * Test notions
     */
    public function testNotions(): void {
        /**
         * @var Model\ValueItem $valueItem
         * @var Model\TaskExpanded $taks
         */
        [$valueItem, $taks] = $this->_getTask();

        // Prepare the notion
        $notion = $this->sdk
            ->api()
            ->notions()
            ->create((new Model\PayloadNotionCreate())->setValue("notion-one"));
        $this->assertInstanceOf(Model\Notion::class, $notion);
        $this->assertEquals(0, count($notion->listProps()));

        // Update the task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                $taks->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$notion->getId()])
            );
        $this->assertInstanceOf(Model\Task::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Fetch my notifications
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals(Model\Event::TYPE_SELF_EVALUATION, $eventsList->getEvents()[0]->getType());

        // Self-evaluate
        $experience = $this->sdk
            ->api()
            ->experiences()
            ->evaluate($notion->getId(), mt_rand(1, 10));
        $this->assertInstanceOf(Model\Experience::class, $experience);
        $this->assertEquals(0, count($experience->listProps()));

        // Self eval notification should be gone
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));

        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(2, count($eventsList->getEvents()));

        // First up: self-evaluation
        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals(Model\Event::TYPE_SELF_EVALUATION, $eventsList->getEvents()[0]->getType());

        // Self-evaluate
        $experience = $this->sdkService
            ->api()
            ->experiences()
            ->evaluate($notion->getId(), mt_rand(1, 10));
        $this->assertInstanceOf(Model\Experience::class, $experience);
        $this->assertEquals(0, count($experience->listProps()));

        // Self eval notification should be gone
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        // Second: notion ids change
        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals(Model\Event::TYPE_TASKS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("notionIds", $eventsList->getEvents()[0]->getDiff());

        // --- Peer eval ---
        $this->sdk
            ->api()
            ->tasks()
            ->assign(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                $task->getId(),
                $this->serviceAccount->getId()
            );
        $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $valueItem->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setState(Model\PayloadTaskUpdate::STATE_DONE)
            );

        // Fetch my notifications - evaluate peer
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals(Model\Event::TYPE_PEER_EVALUATION, $eventsList->getEvents()[0]->getType());
        $this->assertEquals($this->serviceAccount->getId(), $eventsList->getEvents()[0]->getPeerUserId());

        // Evaluate peer
        $experience = $this->sdk
            ->api()
            ->experiences()
            ->evaluatePeer($notion->getId(), 5, $this->serviceAccount->getId());
        $this->assertInstanceOf(Model\Experience::class, $experience);
        $this->assertEquals(0, count($experience->listProps()));

        // No more notifications for me
        $eventsList = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertIsArray($eventsList->getEvents());
        $this->assertEquals(0, count($eventsList->getEvents()));

        // Fetch service account notifications
        $eventsList = $this->sdkService
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $eventsList);
        $this->assertEquals(0, count($eventsList->listProps()));
        $this->assertEquals(1, count($eventsList->getEvents()));

        $this->assertInstanceOf(Model\Event::class, $eventsList->getEvents()[0]);
        $this->assertEquals(0, count($eventsList->getEvents()[0]->listProps()));
        $this->assertEquals(Model\Event::TYPE_TASKS, $eventsList->getEvents()[0]->getType());
        $this->assertContains("state", $eventsList->getEvents()[0]->getDiff());
    }
}
