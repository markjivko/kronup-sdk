<?php
/**
 * Value Item Test
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
 * Value Item Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ItemTest extends TestCase {
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
    public function testItemCreate(): void {
        // Fetch account data
        $account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        $orgId = current($account->getRoleOrg())->getOrgId();

        // Create an item
        $account = $this->sdk
            ->api()
            ->valueItems()
            ->itemCreate($orgId, (new Model\ItemCreateRequest())->setDigest("Hello world"));
        $this->assertInstanceOf(Model\Item::class, $account);
    }
}
