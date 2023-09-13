<?php
/**Account
 * Invitation Test
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
 * Invitation Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class InvitationTest extends TestCase {
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
        $this->assertInstanceOf(Model\TeamExpanded::class, $this->team);
        $this->assertEquals(0, count($this->team->listProps()));
    }

    /**
     * Read & Update
     */
    public function testInvitationReadUpdate(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Get the first organization ID
        $this->sdk->config()->setOrgId(current($account->getRoleOrg())->getOrgId());

        // Create the invitation
        $invitationModel = $this->sdk
            ->api()
            ->invitations()
            ->create(
                (new Model\PayloadInvitationCreate())->setTeamId($this->team->getId())->setInviteName("New invitation")
            );
        $this->assertInstanceOf(Model\Invitation::class, $invitationModel);
        $this->assertEquals(0, count($invitationModel->listProps()));

        // Read the invitation data
        $invitationModelRead = $this->sdk
            ->api()
            ->invitations()
            ->read($invitationModel->getId());
        $this->assertInstanceOf(Model\Invitation::class, $invitationModelRead);
        $this->assertEquals(0, count($invitationModelRead->listProps()));

        // Validate views increment
        $this->assertEquals($invitationModel->getInviteViews() + 1, $invitationModelRead->getInviteViews());

        // List all invitations
        $invitationsList = $this->sdk
            ->api()
            ->invitations()
            ->list();
        $this->assertInstanceOf(Model\InvitationsList::class, $invitationsList);
        $this->assertEquals(0, count($invitationsList->listProps()));

        $this->assertIsArray($invitationsList->getInvitations());
        $this->assertGreaterThanOrEqual(1, count($invitationsList->getInvitations()));
        $this->assertGreaterThanOrEqual(count($invitationsList->getInvitations()), $invitationsList->getTotal());

        // Remove the invitation
        $invitationDeleted = $this->sdk
            ->api()
            ->invitations()
            ->delete($invitationModel->getId());
        $this->assertTrue($invitationDeleted);
    }

    /**
     * Invitation limits
     */
    public function testInvitationLimits(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Get the first organization ID
        $this->sdk->config()->setOrgId(current($account->getRoleOrg())->getOrgId());

        // Create: Name too long
        try {
            $this->sdk
                ->api()
                ->invitations()
                ->create(
                    new Model\PayloadInvitationCreate([
                        "teamId" => $this->team->getId(),
                        "inviteName" => str_repeat("x ", 33)
                    ])
                );
            $this->assertTrue(false, "invitations.create(name) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument-inviteName", $exc->getResponseObject()["id"]);
        }

        // Create a new invitation
        $invitation = $this->sdk
            ->api()
            ->invitations()
            ->create((new Model\PayloadInvitationCreate())->setTeamId($this->team->getId())->setInviteName("Test"));
        $this->assertInstanceOf(Model\Invitation::class, $invitation);
        $this->assertEquals(0, count($invitation->listProps()));

        // Update: Name too long
        try {
            $this->sdk
                ->api()
                ->invitations()
                ->update(
                    $invitation->getId(),
                    new Model\PayloadInvitationUpdate(["inviteName" => str_repeat("x ", 33)])
                );
            $this->assertTrue(false, "invitations.update(name) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument-inviteName", $exc->getResponseObject()["id"]);
        }

        // Remove the temporary invitation
        $invitationDeleted = $this->sdk
            ->api()
            ->invitations()
            ->delete($invitation->getId());
        $this->assertTrue($invitationDeleted);
    }

    /**
     * Invitation delete
     */
    public function testInvitationDelete() {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->read();

        // Get the first organization ID
        $this->sdk->config()->setOrgId(current($account->getRoleOrg())->getOrgId());

        // Create a new invitation
        $invitation = $this->sdk
            ->api()
            ->invitations()
            ->create((new Model\PayloadInvitationCreate())->setTeamId($this->team->getId())->setInviteName("Test"));
        $this->assertInstanceOf(Model\Invitation::class, $invitation);
        $this->assertEquals(0, count($invitation->listProps()));

        // Delete the invitation
        $deleted = $this->sdk
            ->api()
            ->invitations()
            ->delete($invitation->getId());
        $this->assertTrue($deleted);
    }
}
