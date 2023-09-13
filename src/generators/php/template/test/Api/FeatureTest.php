<?php
/**
 * Feature Test
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
 * Feature Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class FeatureTest extends TestCase {
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
     * Read & Update
     */
    public function testCreateRead(): void {
        $feature = $this->sdk
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
        $this->assertInstanceOf(Model\Feature::class, $feature);
        $this->assertEquals(0, count($feature->listProps()));

        // Get the events
        $events = $this->sdk
            ->api()
            ->account()
            ->eventsList();
        $this->assertInstanceOf(Model\EventsList::class, $events);
        $this->assertEquals(0, count($events->listProps()));
        $this->assertIsArray($events->getEvents());

        // Get the list
        $features = $this->sdk
            ->api()
            ->features()
            ->list($this->team->getId(), $this->channel->getId());
        $this->assertInstanceOf(Model\FeaturesList::class, $features);
        $this->assertEquals(0, count($features->listProps()));

        $this->assertIsArray($features->getFeatures());
        $this->assertGreaterThan(0, count($features->getFeatures()));
        $this->assertGreaterThanOrEqual(count($features->getFeatures()), $features->getTotal());

        $this->assertInstanceOf(Model\FeatureLite::class, $features->getFeatures()[0]);
        $this->assertEquals(0, count($features->getFeatures()[0]->listProps()));
    }

    /**
     * Delete feature
     */
    public function testUpdateDelete() {
        $feature = $this->sdk
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

        // Update the feature
        $featureUpdated = $this->sdk
            ->api()
            ->features()
            ->update(
                $this->team->getId(),
                $this->channel->getId(),
                $feature->getId(),
                (new Model\PayloadFeatureUpdate())->setHeading("The new heading")
            );
        $this->assertInstanceOf(Model\Feature::class, $featureUpdated);
        $this->assertEquals(0, count($featureUpdated->listProps()));

        $this->assertEquals("The new heading", $featureUpdated->getHeading());

        // Delete the feature
        $deleted = $this->sdk
            ->api()
            ->features()
            ->delete($this->team->getId(), $this->channel->getId(), $feature->getId());
        $this->assertTrue($deleted);

        // Expect to fail (feature was removed)
        $this->expectExceptionObject(new ApiException("Not Found", 404));
        $this->sdk
            ->api()
            ->features()
            ->read($this->team->getId(), $this->channel->getId(), $feature->getId());
    }

    /**
     * Advance error
     */
    public function testAdvanceError() {
        $feature = $this->sdk
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
        $this->assertInstanceOf(Model\Feature::class, $feature);
        $this->assertEquals(0, count($feature->listProps()));

        // Advance the feature
        $this->expectExceptionObject(new ApiException("You must add at least 1 Assumption", 403));
        $this->sdk
            ->api()
            ->features()
            ->advance($this->team->getId(), $this->channel->getId(), $feature->getId());
    }
}
