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
            ->read();

        // Get the first organization ID
        if (!count($account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->create((new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc."));
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
            ->read();

        // Get the first organization ID
        $this->sdk->config()->setOrgId(current($account->getRoleOrg())->getOrgId());

        // Create the team
        $teamModel = $this->sdk
            ->api()
            ->teams()
            ->create((new Model\PayloadTeamCreate())->setTeamName("New team"));
        $this->assertInstanceOf(Model\TeamExtended::class, $teamModel);
        $this->assertEquals(0, count($teamModel->listProps()));

        // Read the team data
        $teamModelRead = $this->sdk
            ->api()
            ->teams()
            ->read($teamModel->getId());
        $this->assertInstanceOf(Model\TeamExtended::class, $teamModelRead);
        $this->assertEquals(0, count($teamModelRead->listProps()));

        // List all teams
        $teamsList = $this->sdk
            ->api()
            ->teams()
            ->listAll();
        $this->assertInstanceOf(Model\TeamsList::class, $teamsList);
        $this->assertEquals(0, count($teamsList->listProps()));

        $this->assertIsArray($teamsList->getTeams());
        $this->assertGreaterThanOrEqual(1, count($teamsList->getTeams()));
        $this->assertGreaterThanOrEqual(count($teamsList->getTeams()), $teamsList->getTotal());

        $this->assertInstanceOf(Model\Team::class, $teamsList->getTeams()[0]);
        $this->assertEquals(0, count($teamsList->getTeams()[0]->listProps()));

        // Update team details
        $teamModelUpdated = $this->sdk
            ->api()
            ->teams()
            ->update($teamModel->getId(), (new Model\PayloadTeamUpdate())->setTeamName("Another team name"));
        $this->assertInstanceOf(Model\TeamExtended::class, $teamModelUpdated);
        $this->assertEquals(0, count($teamModelUpdated->listProps()));

        $this->assertEquals("Another team name", $teamModelUpdated->getTeamName());

        // Add a channel
        $team = $this->sdk
            ->api()
            ->channels()
            ->create(
                $teamModel->getId(),
                (new Model\PayloadChannelCreate())
                    ->setChannelName("A new channel")
                    ->setChannelDesc("The channel description")
            );
        $this->assertInstanceOf(Model\TeamExtended::class, $team);
        $this->assertEquals(0, count($team->listProps()));

        $this->assertIsArray($team->getChannels());
        $this->assertGreaterThanOrEqual(2, count($team->getChannels()));

        // Update a channel
        $this->sdk
            ->api()
            ->channels()
            ->update(
                $team->getId(),
                $team->getChannels()[1]->getId(),
                (new Model\PayloadChannelUpdate())
                    ->setChannelName("A new channel 2")
                    ->setChannelDesc("The 2nd channel description")
            );

        // Find prospects
        $prospectsList = $this->sdk
            ->api()
            ->channels()
            ->listProspects($team->getId(), $team->getChannels()[0]->getId());
        $this->assertInstanceOf(Model\ChannelProspectsList::class, $prospectsList);
        $this->assertEquals(0, count($prospectsList->listProps()));
        $this->assertIsArray($prospectsList->getProspects());
        $this->assertGreaterThanOrEqual(1, count($prospectsList->getProspects()));
        $this->assertInstanceOf(Model\User::class, $prospectsList->getProspects()[0]);
        $this->assertEquals(0, count($prospectsList->getProspects()[0]->listProps()));

        // Sign me up
        $this->sdk
            ->api()
            ->teams()
            ->assign($team->getId(), $account->getId());

        // Fetch user teams
        $listUser = $this->sdk
            ->api()
            ->teams()
            ->listUser($account->getId());
        $this->assertInstanceOf(Model\TeamsExtendedList::class, $listUser);
        $this->assertEquals(0, count($listUser->listProps()));
        $this->assertIsArray($listUser->getTeams());
        $this->assertGreaterThanOrEqual(1, count($listUser->getTeams()));
        $this->assertInstanceOf(Model\TeamExtended::class, $listUser->getTeams()[0]);
        $this->assertEquals(0, count($listUser->getTeams()[0]->listProps()));

        // Validate channels
        $this->assertIsArray($listUser->getTeams()[0]->getChannels());
        $this->assertGreaterThanOrEqual(1, count($listUser->getTeams()[0]->getChannels()));
        $this->assertInstanceOf(Model\Channel::class, $listUser->getTeams()[0]->getChannels()[0]);
        $this->assertEquals(
            0,
            count(
                $listUser
                    ->getTeams()[0]
                    ->getChannels()[0]
                    ->listProps()
            )
        );

        // Fetch channel members
        $channelMembers = $this->sdk
            ->api()
            ->channels()
            ->listMembers($team->getId(), $team->getChannels()[0]->getId());
        $this->assertInstanceOf(Model\ChannelMembersList::class, $channelMembers);

        $this->assertEquals(0, count($channelMembers->listProps()));
        $this->assertIsArray($channelMembers->getMembers());
        $this->assertGreaterThanOrEqual(1, count($channelMembers->getMembers()));

        $this->assertInstanceOf(Model\User::class, $channelMembers->getMembers()[0]);
        $this->assertEquals(0, count($channelMembers->getMembers()[0]->listProps()));
        $this->assertEquals($channelMembers->getMembers()[0]->getId(), $account->getId());

        // Re-fetch account
        $account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Validate organizations teams lit
        $this->assertIsArray($account->getOrgsTeams());
        $this->assertGreaterThanOrEqual(1, count($account->getOrgsTeams()));
        $this->assertInstanceOf(Model\OrganizationTeams::class, $account->getOrgsTeams()[0]);
        $this->assertEquals(0, count($account->getOrgsTeams()[0]->listProps()));

        // Assign the secondary channel
        $modelUser = $this->sdk
            ->api()
            ->channels()
            ->assign($team->getId(), $team->getChannels()[1]->getId(), $account->getId());
        $this->assertInstanceOf(Model\User::class, $modelUser);
        $this->assertEquals(0, count($modelUser->listProps()));

        $countAssigned = count($modelUser->getTeams()[count($modelUser->getTeams()) - 1]->getChannelIds());

        // Unassign the secondary channel
        $channelUnassigned = $this->sdk
            ->api()
            ->channels()
            ->unassign($team->getId(), $team->getChannels()[1]->getId(), $account->getId());
        $this->assertTrue($channelUnassigned);

        // Fetch the model
        $modelUser2 = $this->sdk
            ->api()
            ->users()
            ->read($account->getId());
        $this->assertInstanceOf(Model\User::class, $modelUser2);
        $this->assertEquals(0, count($modelUser2->listProps()));

        $countUnassigned = count($modelUser2->getTeams()[count($modelUser2->getTeams()) - 1]->getChannelIds());
        $this->assertGreaterThan($countUnassigned, $countAssigned);

        // Unassign from the team
        $teamUnassigned = $this->sdk
            ->api()
            ->teams()
            ->unassign($team->getId(), $account->getId());
        $this->assertTrue($teamUnassigned);

        // Fetch the user model
        $modelUser3 = $this->sdk
            ->api()
            ->users()
            ->read($account->getId());

        // Fetch the teams
        $userTeams = array_map(function ($item) {
            return $item->getTeamId();
        }, $modelUser3->getTeams());
        $this->assertNotContains($team->getId(), $userTeams);

        // Assign team back
        $modelUser4 = $this->sdk
            ->api()
            ->teams()
            ->assign($team->getId(), $account->getId());
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
            ->assign($team->getId(), $team->getChannels()[1]->getId(), $account->getId());
        $channelDeleted = $this->sdk
            ->api()
            ->channels()
            ->delete($team->getId(), $team->getChannels()[1]->getId());
        $this->assertTrue($channelDeleted);

        // Remove the team
        $teamDeleted = $this->sdk
            ->api()
            ->teams()
            ->delete($teamModel->getId());
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
            ->read();

        // Get the first organization ID
        $this->sdk->config()->setOrgId(current($account->getRoleOrg())->getOrgId());

        // Create
        $this->sdk
            ->api()
            ->teams()
            ->create(new Model\PayloadTeamCreate(["teamName" => str_repeat("x ", 33)]));

        // Create a new team
        $team = $this->sdk
            ->api()
            ->teams()
            ->create((new Model\PayloadTeamCreate())->setTeamName("Test"));
        $this->assertInstanceOf(Model\TeamExtended::class, $team);
        $this->assertEquals(0, count($team->listProps()));

        // Remove the temporary team
        $teamDeleted = $this->sdk
            ->api()
            ->teams()
            ->delete($team->getId());
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
            ->read();

        // Get the first organization ID
        if (!count($account->getRoleOrg())) {
            $organization = $this->sdk
                ->api()
                ->organizations()
                ->create((new Model\PayloadOrganizationCreate())->setOrgName("Org " . mt_rand(1, 999) . ", Inc."));
            $this->assertInstanceOf(Model\Organization::class, $organization);
            $this->assertEquals(0, count($organization->listProps()));
            $this->sdk->config()->setOrgId($organization->getId());
        } else {
            $this->sdk->config()->setOrgId(current($account->getRoleOrg())->getOrgId());
        }

        // Create a new team
        $team = $this->sdk
            ->api()
            ->teams()
            ->create((new Model\PayloadTeamCreate())->setTeamName("Test"));
        $this->assertInstanceOf(Model\TeamExtended::class, $team);
        $this->assertEquals(0, count($team->listProps()));

        // Assign team to user
        $user = $this->sdk
            ->api()
            ->teams()
            ->assign($team->getId(), $account->getId());
        $teamIds = array_map(function ($item) {
            return $item->getTeamId();
        }, $user->getTeams());
        $this->assertContains($team->getId(), $teamIds);

        // Validate user teams
        $this->assertIsArray($user->getTeams());
        $this->assertInstanceOf(Model\UserTeam::class, $user->getTeams()[0]);
        $this->assertEquals(0, count($user->getTeams()[0]->listProps()));

        // Validate account teams
        $account = $this->sdk
            ->api()
            ->account()
            ->read();
        $this->assertIsArray($account->getTeams());
        $this->assertInstanceOf(Model\AccountTeam::class, $account->getTeams()[0]);
        $this->assertEquals(0, count($account->getTeams()[0]->listProps()));

        // Create the invitation
        $invitationModel = $this->sdk
            ->api()
            ->invitations()
            ->create((new Model\PayloadInvitationCreate())->setTeamId($team->getId())->setInviteName("New invitation"));
        $this->assertInstanceOf(Model\Invitation::class, $invitationModel);
        $this->assertEquals(0, count($invitationModel->listProps()));

        // Delete the team
        $deleted = $this->sdk
            ->api()
            ->teams()
            ->delete($team->getId());
        $this->assertTrue($deleted);

        // Fetch the user model again
        $user = $this->sdk
            ->api()
            ->account()
            ->read();
        $teamIds = array_map(function ($item) {
            return $item->getTeamId();
        }, $user->getTeams());

        // Check that the user is no longer part of the deleted team
        $this->assertNotContains($team->getId(), $teamIds);

        // Fetch the invitation again
        try {
            $this->sdk
                ->api()
                ->invitations()
                ->read($invitationModel->getId());
            $this->fail("Shold have deleted invitation as well");
        } catch (\Exception $exc) {
            $this->assertInstanceOf(Sdk\ApiException::class, $exc);
        }
    }
}
