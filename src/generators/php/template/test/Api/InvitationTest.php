<?php
/**Account
 * Invitation Test
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
 * Invitation Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class InvitationTest extends TestCase {
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
    }

    /**
     * Read & Update
     */
    public function testInvitationReadUpdate(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create the invitation
        $invitationModel = $this->sdk
            ->api()
            ->invitations()
            ->invitationCreate($orgId, (new Model\RequestInvitationCreate())->setInviteName("New invitation"));
        $this->assertInstanceOf(Model\Invitation::class, $invitationModel);
        $this->assertEquals(0, count($invitationModel->listProps()));

        // Read the invitation data
        $invitationModelRead = $this->sdk
            ->api()
            ->invitations()
            ->invitationRead($invitationModel->getId());
        $this->assertInstanceOf(Model\Invitation::class, $invitationModelRead);
        $this->assertEquals(0, count($invitationModelRead->listProps()));

        // List all invitations
        $invitationsList = $this->sdk
            ->api()
            ->invitations()
            ->invitationList($orgId);
        $this->assertInstanceOf(Model\InvitationsList::class, $invitationsList);
        $this->assertEquals(0, count($invitationsList->listProps()));

        $this->assertIsArray($invitationsList->getInvitations());
        $this->assertGreaterThanOrEqual(1, count($invitationsList->getInvitations()));

        // Update invitation details
        $invitationModelUpdated = $this->sdk
            ->api()
            ->invitations()
            ->invitationUpdate(
                $invitationModel->getId(),
                $orgId,
                (new Model\RequestInvitationUpdate())
                    ->setInviteName("Another invitation name")
                    ->setInviteDomain("example.com")
            );
        $this->assertInstanceOf(Model\Invitation::class, $invitationModelUpdated);
        $this->assertEquals(0, count($invitationModelUpdated->listProps()));

        $this->assertEquals("Another invitation name", $invitationModelUpdated->getInviteName());
        $this->assertEquals("example.com", $invitationModelUpdated->getInviteDomain());

        // Remove the invitation
        $invitationDeleted = $this->sdk
            ->api()
            ->invitations()
            ->invitationDelete($invitationModel->getId(), $orgId);
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
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create: Name too long
        try {
            $this->sdk
                ->api()
                ->invitations()
                ->invitationCreate($orgId, new Model\RequestInvitationCreate(["inviteName" => str_repeat("x ", 33)]));
            $this->assertTrue(false, "invitations.create(name) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument-inviteName", $exc->getResponseObject()["id"]);
        }

        // Create a new invitation
        $invitation = $this->sdk
            ->api()
            ->invitations()
            ->invitationCreate($orgId, (new Model\RequestInvitationCreate())->setInviteName("Test"));
        $this->assertInstanceOf(Model\Invitation::class, $invitation);
        $this->assertEquals(0, count($invitation->listProps()));

        // Update: Name too long
        try {
            $this->sdk
                ->api()
                ->invitations()
                ->invitationUpdate(
                    $invitation->getId(),
                    $orgId,
                    new Model\RequestInvitationUpdate(["inviteName" => str_repeat("x ", 33)])
                );
            $this->assertTrue(false, "invitations.update(name) should throw an error");
        } catch (Sdk\ApiException $exc) {
            $this->assertEquals("invalid-argument-inviteName", $exc->getResponseObject()["id"]);
        }

        // Remove the temporary invitation
        $invitationDeleted = $this->sdk
            ->api()
            ->invitations()
            ->invitationDelete($invitation->getId(), $orgId);
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
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create a new invitation
        $invitation = $this->sdk
            ->api()
            ->invitations()
            ->invitationCreate($orgId, (new Model\RequestInvitationCreate())->setInviteName("Test"));
        $this->assertInstanceOf(Model\Invitation::class, $invitation);
        $this->assertEquals(0, count($invitation->listProps()));

        // Delete the invitation
        $deleted = $this->sdk
            ->api()
            ->invitations()
            ->invitationDelete($invitation->getId(), $orgId);
        $this->assertTrue($deleted);
    }
}
