<?php
/**
 * Organization Test
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
 * Organization Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class OrganizationTest extends TestCase {
    /**
     * Tear-down needed
     */
    protected $tearDownNeeded = true;

    /**
     * kronup SDK
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
     * Deep Context item
     *
     * @var Model\ValueItem
     */
    protected $dcItem;

    /**
     * Notion
     *
     * @var Model\Notion
     */
    protected $notion;

    /**
     * Experience
     *
     * @var Model\Experience
     */
    protected $experience;

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
        $this->dcItem = $this->sdk
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

        // Add second item
        $this->item = $this->sdk
            ->api()
            ->valueItems()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                (new Model\PayloadValueItemCreate())
                    ->setHeading("Second item heading")
                    ->setDetails("Second item details")
                    ->setPriority(4)
            );

        // Add an assumption
        $assm = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->dcItem->getId(),
                (new Model\PayloadAssmCreate())->setHeading("X can be done")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assm);
        $this->assertEquals(0, count($assm->listProps()));

        // Second item assumption
        $assm2 = $this->sdk
            ->api()
            ->assumptions()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadAssmCreate())->setHeading("X can be done a second time")
            );

        // Advance to validation
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->dcItem->getId());
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
                $this->dcItem->getId(),
                $assm->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        $this->sdk
            ->api()
            ->assumptions()
            ->experiment(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $assm2->getId(),
                (new Model\PayloadAssmExperiment())
                    ->setDetails("Second experiment details")
                    ->setConfirmed(true)
                    ->setState(Model\PayloadAssmExperiment::STATE_DONE)
            );

        // Advance to execution
        $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->dcItem->getId());
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
                $this->dcItem->getId(),
                (new Model\PayloadTaskCreate())->setHeading("Task one")->setDetails("Details of task one")
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Prepare task for second item
        $task2 = $this->sdk
            ->api()
            ->tasks()
            ->create(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                (new Model\PayloadTaskCreate())
                    ->setHeading("Second task one")
                    ->setDetails("Second item task one details")
            );

        // Add notion to task
        $task = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->dcItem->getId(),
                $task->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$this->notion->getId()])
            );
        $this->assertInstanceOf(Model\TaskExpanded::class, $task);
        $this->assertEquals(0, count($task->listProps()));

        // Append notion to task 2
        $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->item->getId(),
                $task2->getId(),
                (new Model\PayloadTaskUpdate())->setNotionIds([$this->notion->getId()])
            );

        // Update task
        $taskUpdated = $this->sdk
            ->api()
            ->tasks()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->dcItem->getId(),
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

        // Validate notion
        $this->assertIsArray($taskUpdated->getNotions());
        $this->assertGreaterThanOrEqual(1, count($taskUpdated->getNotions()));
        $this->assertInstanceOf(Model\Notion::class, $taskUpdated->getNotions()[0]);
        $this->assertEquals(0, count($taskUpdated->getNotions()[0]->listProps()));
        $this->assertEquals($this->notion->getValue(), $taskUpdated->getNotions()[0]->getValue());

        // Advance
        $this->dcItem = $this->sdk
            ->api()
            ->valueItems()
            ->advance($this->team->getId(), $this->channel->getId(), $this->dcItem->getId());
        $this->assertInstanceOf(Model\ValueItem::class, $this->dcItem);
        $this->assertEquals(0, count($this->dcItem->listProps()));

        $this->experience = $this->sdk
            ->api()
            ->experiences()
            ->evaluate($this->notion->getId(), mt_rand(1, 10));
        $this->assertInstanceOf(Model\Experience::class, $this->experience);
        $this->assertEquals(0, count($this->experience->listProps()));
    }

    /**
     * Tear-down
     */
    public function tearDown(): void {
        if ($this->tearDownNeeded) {
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
    }

    /**
     * Delete
     */
    public function testDelete() {
        $this->tearDownNeeded = false;

        $deleted = $this->sdk
            ->api()
            ->organizations()
            ->delete($this->sdk->config()->getOrgId());
        $this->assertTrue($deleted);

        // Create another organization
        $organization = $this->sdk
            ->api()
            ->organizations()
            ->create((new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc."));
        $this->assertInstanceOf(Model\Organization::class, $organization);
        $this->assertEquals(0, count($organization->listProps()));
        $this->sdk->config()->setOrgId($organization->getId());
    }
}
