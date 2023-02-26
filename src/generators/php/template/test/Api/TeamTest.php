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
            ->channels()
            ->channelCreate(
                $teamModel->getId(),
                $orgId,
                (new Model\ChannelCreateRequest())
                    ->setChannelName("A new channel")
                    ->setChannelDesc("The channel description")
            );
        $this->assertInstanceOf(Model\Team::class, $team);
        $this->assertIsArray($team->getChannels());
        $this->assertGreaterThanOrEqual(2, count($team->getChannels()));

        // Update a channel
        $this->sdk
            ->api()
            ->channels()
            ->channelUpdate(
                $team->getId(),
                $team->getChannels()[1]->getId(),
                $orgId,
                (new Model\ChannelCreateRequest())
                    ->setChannelName("A new channel 2")
                    ->setChannelDesc("The 2nd channel description")
            );

        // Assign the secondary channel
        $modelUser = $this->sdk
            ->api()
            ->channels()
            ->channelAssign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->assertInstanceOf(Model\User::class, $modelUser);
        $countAssigned = count($modelUser->getTeams()[count($modelUser->getTeams()) - 1]->getChannelIds());

        // Unassign the secondary channel
        $modelUser2 = $this->sdk
            ->api()
            ->channels()
            ->channelUnassign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
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
            ->channels()
            ->channelAssign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->sdk
            ->api()
            ->channels()
            ->channelDelete($team->getId(), $team->getChannels()[1]->getId(), $orgId);

        // Remove the team
        $teamModelDeleted = $this->sdk
            ->api()
            ->teams()
            ->teamDelete($teamModel->getId(), $orgId);
        $this->assertInstanceOf(Model\Team::class, $teamModelDeleted);
        $this->assertEquals($teamModelDeleted->getId(), $teamModelRead->getId());
    }

    /**
     * Team limits (name & description)
     */
    public function testTeamLimits(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create: Name too long
        try {
            $this->sdk
                ->api()
                ->teams()
                ->teamCreate($orgId, new Model\TeamCreateRequest(["teamName" => str_repeat("x ", 33)]));
            $this->assertTrue(false, "teams.create(name) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument", $exc->getResponseObject()["id"]);
        }

        // Create: Description too long
        try {
            $this->sdk
                ->api()
                ->teams()
                ->teamCreate(
                    $orgId,
                    new Model\TeamCreateRequest(["teamName" => "abc", "teamDesc" => str_repeat("x ", 129)])
                );
            $this->assertTrue(false, "teams.create(description) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument", $exc->getResponseObject()["id"]);
        }

        // Create a new team
        $team = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($orgId, (new Model\TeamCreateRequest())->setTeamName("Test"));
        $this->assertInstanceOf(Model\Team::class, $team);

        // Update: Name too long
        try {
            $this->sdk
                ->api()
                ->teams()
                ->teamUpdate($team->getId(), $orgId, new Model\TeamUpdateRequest(["teamName" => str_repeat("x ", 33)]));
            $this->assertTrue(false, "teams.update(name) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument", $exc->getResponseObject()["id"]);
        }

        // Update: Description too long
        try {
            $this->sdk
                ->api()
                ->teams()
                ->teamUpdate(
                    $team->getId(),
                    $orgId,
                    new Model\TeamUpdateRequest(["teamDesc" => str_repeat("x ", 129)])
                );
            $this->assertTrue(false, "teams.update(description) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument", $exc->getResponseObject()["id"]);
        }

        // Remove the temporary team
        $this->sdk
            ->api()
            ->teams()
            ->teamDelete($team->getId(), $orgId);
    }

    /**
     * Team delete (test that it's unassigned from all users)
     */
    public function testTeamDelete() {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create a new team
        $team = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($orgId, (new Model\TeamCreateRequest())->setTeamName("Test"));
        $this->assertInstanceOf(Model\Team::class, $team);

        // Assign team to user
        $user = $this->sdk
            ->api()
            ->teams()
            ->teamAssign($team->getId(), $account->getId(), $orgId);
        $teamIds = array_map(function ($item) {
            return $item->getTeamId();
        }, $user->getTeams());
        $this->assertContains($team->getId(), $teamIds);

        // Delete the team
        $this->sdk
            ->api()
            ->teams()
            ->teamDelete($team->getId(), $orgId);

        // Fetch the user model again
        $user = $this->sdk
            ->api()
            ->account()
            ->accountRead();
        $teamIds = array_map(function ($item) {
            return $item->getTeamId();
        }, $user->getTeams());

        // Check that the user is no longer part of the deleted team
        $this->assertNotContains($team->getId(), $teamIds);
    }
}
