<?php
/**
 * Experience Test
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
use Kronup\Sdk\ApiException;

/**
 * Experience Test
 *
 * @coversDefaultClass \Kronup\Local\Wallet
 */
class ExperienceTest extends TestCase {
    /**
     * kronup SDK
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
     * Organization ID
     *
     * @var string
     */
    protected $orgId;

    /**
     * Set-up
     */
    public function setUp(): void {
        $this->sdk = new Sdk(getenv("KRONUP_API_KEY"));

        // Fetch account data
        $this->account = $this->sdk
            ->api()
            ->account()
            ->accountRead();

        // Get the first organization ID
        $this->orgId = current($this->account->getRoleOrg())->getOrgId();
    }

    /**
     * Tear-down
     */
    public function tearDown(): void {
    }

    /**
     * Create & Read
     */
    public function testCreateRead(): void {
        $keyword = $this->sdk
            ->api()
            ->experience()
            ->keywordCreate(
                $this->orgId,
                (new Model\RequestKeywordCreate())->setKeyword("Abcdef " . mt_rand(1, 999999))
            );
        $this->assertInstanceOf(Model\Keyword::class, $keyword);
        $this->assertEquals(0, count($keyword->listProps()));
    }
}
