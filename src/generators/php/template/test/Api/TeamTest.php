<?php
/**Account
 * Team Test
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

/**
 * Team Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class TeamTest extends TestCase {
    /**
     * kronup sdk
     *
     * @var \Kronup\Sdk
     */
    protected $sdk;

    /**
     * Set-up
     */
    public function setUp(): void {
        $this->sdk = new Sdk(getenv("KRONUP_API_KEY"));

        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        if (!count($account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->organizationCreate(
                    (new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc.")
                );
            $this->assertInstanceOf(Model\Organization::class, $organization);
            $this->assertEquals(0, count($organization->listProps()));
        }
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
            ->teamCreate($orgId, (new Model\PayloadTeamCreate())->setTeamName("New team"));
        $this->assertInstanceOf(Model\Team::class, $teamModel);
        $this->assertEquals(0, count($teamModel->listProps()));

        // Read the team data
        $teamModelRead = $this->sdk
            ->api()
            ->teams()
            ->teamRead($teamModel->getId(), $orgId);
        $this->assertInstanceOf(Model\Team::class, $teamModelRead);
        $this->assertEquals(0, count($teamModelRead->listProps()));

        // List all teams
        $teamsList = $this->sdk
            ->api()
            ->teams()
            ->teamList($orgId);
        $this->assertInstanceOf(Model\TeamsList::class, $teamsList);
        $this->assertEquals(0, count($teamsList->listProps()));

        $this->assertIsArray($teamsList->getTeams());
        $this->assertGreaterThanOrEqual(1, count($teamsList->getTeams()));

        $this->assertGreaterThanOrEqual(count($teamsList->getTeams()), $teamsList->getTotal());

        // Update team details
        $teamModelUpdated = $this->sdk
            ->api()
            ->teams()
            ->teamUpdate(
                $teamModel->getId(),
                $orgId,
                (new Model\PayloadTeamUpdate())->setTeamName("Another team name")->setTeamDesc("A colorful description")
            );
        $this->assertInstanceOf(Model\Team::class, $teamModelUpdated);
        $this->assertEquals(0, count($teamModelUpdated->listProps()));

        $this->assertEquals("Another team name", $teamModelUpdated->getTeamName());
        $this->assertEquals("A colorful description", $teamModelUpdated->getTeamDesc());

        // Add a channel
        $team = $this->sdk
            ->api()
            ->channels()
            ->channelCreate(
                $teamModel->getId(),
                $orgId,
                (new Model\PayloadChannelCreate())
                    ->setChannelName("A new channel")
                    ->setChannelDesc("The channel description")
            );
        $this->assertInstanceOf(Model\Team::class, $team);
        $this->assertEquals(0, count($team->listProps()));

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
                (new Model\PayloadChannelUpdate())
                    ->setChannelName("A new channel 2")
                    ->setChannelDesc("The 2nd channel description")
            );

        // Assign the secondary channel
        $modelUser = $this->sdk
            ->api()
            ->channels()
            ->channelAssign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->assertInstanceOf(Model\User::class, $modelUser);
        $this->assertEquals(0, count($modelUser->listProps()));

        $countAssigned = count($modelUser->getTeams()[count($modelUser->getTeams()) - 1]->getChannelIds());

        // Unassign the secondary channel
        $channelUnassigned = $this->sdk
            ->api()
            ->channels()
            ->channelUnassign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $this->assertTrue($channelUnassigned);

        // Fetch the model
        $modelUser2 = $this->sdk
            ->api()
            ->users()
            ->userRead($account->getId());
        $this->assertInstanceOf(Model\User::class, $modelUser2);
        $this->assertEquals(0, count($modelUser2->listProps()));

        $countUnassigned = count($modelUser2->getTeams()[count($modelUser2->getTeams()) - 1]->getChannelIds());
        $this->assertGreaterThan($countUnassigned, $countAssigned);

        // Unassign from the team
        $teamUnassigned = $this->sdk
            ->api()
            ->teams()
            ->teamUnassign($team->getId(), $account->getId(), $orgId);
        $this->assertTrue($teamUnassigned);

        // Fetch the user model
        $modelUser3 = $this->sdk
            ->api()
            ->users()
            ->userRead($account->getId());

        // Fetch the teams
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
        $this->assertEquals(0, count($modelUser4->listProps()));

        $userTeams = array_map(function ($item) {
            return $item->getTeamId();
        }, $modelUser4->getTeams());
        $this->assertContains($team->getId(), $userTeams);

        // Delete a channel
        $this->sdk
            ->api()
            ->channels()
            ->channelAssign($team->getId(), $team->getChannels()[1]->getId(), $account->getId(), $orgId);
        $channelDeleted = $this->sdk
            ->api()
            ->channels()
            ->channelDelete($team->getId(), $team->getChannels()[1]->getId(), $orgId);
        $this->assertTrue($channelDeleted);

        // Remove the team
        $teamDeleted = $this->sdk
            ->api()
            ->teams()
            ->teamDelete($teamModel->getId(), $orgId);
        $this->assertTrue($teamDeleted);
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
                ->teamCreate($orgId, new Model\PayloadTeamCreate(["teamName" => str_repeat("x ", 33)]));
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
                    new Model\PayloadTeamCreate(["teamName" => "abc", "teamDesc" => str_repeat("x ", 129)])
                );
            $this->assertTrue(false, "teams.create(description) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument", $exc->getResponseObject()["id"]);
        }

        // Create a new team
        $team = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($orgId, (new Model\PayloadTeamCreate())->setTeamName("Test"));
        $this->assertInstanceOf(Model\Team::class, $team);
        $this->assertEquals(0, count($team->listProps()));

        // Update: Name too long
        try {
            $this->sdk
                ->api()
                ->teams()
                ->teamUpdate($team->getId(), $orgId, new Model\PayloadTeamUpdate(["teamName" => str_repeat("x ", 33)]));
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
                    new Model\PayloadTeamUpdate(["teamDesc" => str_repeat("x ", 129)])
                );
            $this->assertTrue(false, "teams.update(description) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument", $exc->getResponseObject()["id"]);
        }

        // Remove the temporary team
        $teamDeleted = $this->sdk
            ->api()
            ->teams()
            ->teamDelete($team->getId(), $orgId);
        $this->assertTrue($teamDeleted);
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
        if (!count($account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->organizationCreate(
                    (new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc.")
                );
            $this->assertInstanceOf(Model\Organization::class, $organization);
            $this->assertEquals(0, count($organization->listProps()));
            $orgId = $organization->getId();
        } else {
            $orgId = current($account->getRoleOrg())->getOrgId();
        }

        // Create a new team
        $team = $this->sdk
            ->api()
            ->teams()
            ->teamCreate($orgId, (new Model\PayloadTeamCreate())->setTeamName("Test"));
        $this->assertInstanceOf(Model\Team::class, $team);
        $this->assertEquals(0, count($team->listProps()));

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
        $deleted = $this->sdk
            ->api()
            ->teams()
            ->teamDelete($team->getId(), $orgId);
        $this->assertTrue($deleted);

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
