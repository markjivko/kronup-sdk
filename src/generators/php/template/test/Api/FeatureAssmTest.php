<?php
/**
 * Feature Assumption Test
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
 * Assumption Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class FeatureAssmTest extends TestCase {
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

        // Store the feature
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
    }

    /**
     * Create & Read
     */
    public function testCreateRead(): void {
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

        // List
        $assmList = $this->sdk
            ->api()
            ->assumptions()
            ->list($this->team->getId(), $this->channel->getId(), $this->feature->getId());
        $this->assertInstanceOf(Model\AssumptionsList::class, $assmList);
        $this->assertEquals(0, count($assmList->listProps()));

        $this->assertIsArray($assmList->getAssumptions());
        $this->assertGreaterThan(0, count($assmList->getAssumptions()));
        $this->assertGreaterThanOrEqual(count($assmList->getAssumptions()), $assmList->getTotal());

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
        $this->assertIsArray($featureList->getFeatures()[0]->getAssumptions());
        $this->assertGreaterThan(0, count($featureList->getFeatures()[0]->getAssumptions()));
        $this->assertInstanceOf(Model\AssumptionLite::class, $featureList->getFeatures()[0]->getAssumptions()[0]);
        $this->assertEquals(
            0,
            count(
                $featureList
                    ->getFeatures()[0]
                    ->getAssumptions()[0]
                    ->listProps()
            )
        );
        $experimentLite = $featureList
            ->getFeatures()[0]
            ->getAssumptions()[0]
            ->getExperiment();
        $this->assertInstanceOf(Model\ExperimentLite::class, $experimentLite);
        $this->assertEquals(0, count($experimentLite->listProps()));
    }

    /**
     * Update & delete
     */
    public function testUpdateDelete(): void {
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

        // Update assumption
        $assmUpdated = $this->sdk
            ->api()
            ->assumptions()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $this->feature->getId(),
                $assm->getId(),
                (new Model\PayloadAssmUpdate())->setHeading("New assumption")
            );
        $this->assertInstanceOf(Model\Assumption::class, $assmUpdated);
        $this->assertEquals(0, count($assmUpdated->listProps()));
        $this->assertEquals("New assumption", $assmUpdated->getHeading());

        // Advance
        $this->sdk
            ->api()
            ->features()
            ->advance($this->team->getId(), $this->channel->getId(), $this->feature->getId());

        // Validate assumption with experiment
        $assmUpdatedExp = $this->sdk
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
        $this->assertInstanceOf(Model\Assumption::class, $assmUpdatedExp);
        $this->assertEquals(0, count($assmUpdatedExp->listProps()));

        $this->assertInstanceOf(Model\Experiment::class, $assmUpdatedExp->getExperiment());
        $this->assertEquals(0, count($assmUpdatedExp->getExperiment()->listProps()));

        $this->assertEquals("Experiment details", $assmUpdatedExp->getExperiment()->getDetails());
        $this->assertTrue($assmUpdatedExp->getExperiment()->getConfirmed());
        $this->assertEquals(Model\PayloadAssmExperiment::STATE_DONE, $assmUpdatedExp->getExperiment()->getState());

        // Delete
        $deleted = $this->sdk
            ->api()
            ->assumptions()
            ->delete($this->team->getId(), $this->channel->getId(), $this->feature->getId(), $assm->getId());
        $this->assertTrue($deleted);
    }
}
