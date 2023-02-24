<?php
/**Account
 * Team Test
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

/**
 * Team Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class TeamTest extends TestCase {
    /**
     * Kronup SDK
     *
     * @var \Kronup\Sdk
     */
    protected $sdk;

    /**
     * Set-up
     */
    public function setUp(): void {
        $this->sdk = new Sdk(getenv("KRONUP_API_KEY"));
    }

    /**
     * Read & Update
     */
    public function testTeamReadUpdate(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create the team
        $teamModel = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($orgId, (new Model\TeamCreateRequest())->setTeamName("New team"));
        $this->assertInstanceOf(Model\Team::class, $teamModel);

        // Read the team data
        $teamModelRead = $this->sdk
            ->api()
            ->teams()
            ->teamRead($teamModel->getId(), $orgId);
        $this->assertInstanceOf(Model\Team::class, $teamModelRead);

        // List all teams
        $teamsList = $this->sdk
            ->api()
            ->teams()
            ->teamList($orgId);
        $this->assertIsArray($teamsList);
        $this->assertGreaterThanOrEqual(1, count($teamsList));

        // Update team details
        $teamModelUpdated = $this->sdk
            ->api()
            ->teams()
            ->teamUpdate(
                $teamModel->getId(),
                $orgId,
                (new Model\TeamUpdateRequest())->setTeamName("Another team name")->setTeamDesc("A colorful description")
            );
        $this->assertInstanceOf(Model\Team::class, $teamModelUpdated);
        $this->assertEquals("Another team name", $teamModelUpdated->getTeamName());
        $this->assertEquals("A colorful description", $teamModelUpdated->getTeamDesc());

        // Add a channel
        $team = $this->sdk
            ->api()
            ->teamChannels()
            ->teamChannelCreate(
                $teamModel->getId(),
                $orgId,
                (new Model\TeamChannelCreateRequest())
                    ->setChannelName("A new channel")
                    ->setChannelDesc("The channel description")
            );
        $this->assertInstanceOf(Model\Team::class, $team);
        $this->assertIsArray($team->getChannels());
        $this->assertGreaterThanOrEqual(2, count($team->getChannels()));

        // Update a channel
        $this->sdk
            ->api()
            ->teamChannels()
            ->teamChannelUpdate(
                $team->getId(),
                $team->getChannels()[1]->getId(),
                $orgId,
                (new Model\TeamChannelCreateRequest())
                    ->setChannelName("A new channel 2")
                    ->setChannelDesc("The 2nd channel description")
            );

        // Assign the secondary channel
        $modelUser = $this->sdk
            ->api()
            ->teamChannels()
            ->teamChannelAssign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->assertInstanceOf(Model\User::class, $modelUser);
        $countAssigned = count($modelUser->getTeams()[count($modelUser->getTeams()) - 1]->getChannelIds());

        // Unassign the secondary channel
        $modelUser2 = $this->sdk
            ->api()
            ->teamChannels()
            ->teamChannelUnassign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->assertInstanceOf(Model\User::class, $modelUser2);
        $countUnassigned = count($modelUser2->getTeams()[count($modelUser2->getTeams()) - 1]->getChannelIds());
        $this->assertGreaterThan($countUnassigned, $countAssigned);

        // Unassign from the team
        $modelUser3 = $this->sdk
            ->api()
            ->teams()
            ->teamUnassign($team->getId(), $account->getId(), $orgId);
        $this->assertInstanceOf(Model\User::class, $modelUser3);
        $userTeams = array_map(function ($item) {
            return $item->getTeamId();
        }, $modelUser3->getTeams());
        $this->assertNotContains($team->getId(), $userTeams);

        // Assign team back
        $modelUser4 = $this->sdk
            ->api()
            ->teams()
            ->teamAssign($team->getId(), $account->getId(), $orgId);
        $this->assertInstanceOf(Model\User::class, $modelUser4);
        $userTeams = array_map(function ($item) {
            return $item->getTeamId();
        }, $modelUser4->getTeams());
        $this->assertContains($team->getId(), $userTeams);

        // Delete a channel
        $this->sdk
            ->api()
            ->teamChannels()
            ->teamChannelAssign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->sdk
            ->api()
            ->teamChannels()
            ->teamChannelDelete($team->getId(), $team->getChannels()[1]->getId(), $orgId);

        // Remove the team
        $teamModelDeleted = $this->sdk
            ->api()
            ->teams()
            ->teamDelete($teamModel->getId(), $orgId);
        $this->assertInstanceOf(Model\Team::class, $teamModelDeleted);
        $this->assertEquals($teamModelDeleted->getId(), $teamModelRead->getId());
    }
}
