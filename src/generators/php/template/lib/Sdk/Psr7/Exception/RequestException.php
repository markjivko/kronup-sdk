<?php

declare(strict_types=1);

/**
 * Request exception
 *
 * @copyright (c) 2022-2023 kronup.com
 * @license   MIT
 * @package   kronup
 * @author    Mark Jivko
 */
namespace Kronup\Sdk\Psr7\Exception;
!defined("KRONUP-SDK") && exit();

use RuntimeException;
use Kronup\Sdk\Psr7\Response;

/**
 * Exception thrown on CURL execution
 */
class RequestException extends RuntimeException {
    /**
     * Client response
     *
     * @var Response
     */
    protected $_response;

    /**
     * Request exception
     *
     * @param Response $response     Response
     * @param string   $errorMessage (optional) Error message
     */
    public function __construct($response, $errorMessage = "") {
        parent::__construct(
            is_string($errorMessage) && strlen($errorMessage) ? $errorMessage : $response->getReasonPhrase(),
            $response->getStatusCode()
        );

        $this->_response = $response;
    }

    /**
     * Get method response
     *
     * @return Response
     */
    public function getResponse() {
        return $this->_response;
    }
}
