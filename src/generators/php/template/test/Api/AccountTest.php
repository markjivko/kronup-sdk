<?php
/**
 * Account Test
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
 * Account Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class AccountTest extends TestCase {
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
     * Get user list
     */
    public function testAccountRead(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();
        $this->assertInstanceOf(Model\Account::class, $account);
        $userName = $account->getUserName();

        // Update the account
        $updatedAccount = $this->sdk
            ->api()
            ->account()
            ->accountUpdate((new Model\AccountUpdateRequest())->setUserName("$userName 2"));
        $this->assertInstanceOf(Model\Account::class, $updatedAccount);
        $this->assertEquals("$userName 2", $updatedAccount->getUserName());

        // Revert the changes
        $this->sdk
            ->api()
            ->account()
            ->accountUpdate((new Model\AccountUpdateRequest())->setUserName($userName));
    }
}
