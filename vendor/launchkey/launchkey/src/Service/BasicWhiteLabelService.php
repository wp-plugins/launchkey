<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */

namespace LaunchKey\SDK\Service;

use LaunchKey\SDK\Domain\WhiteLabelUser;
use LaunchKey\SDK\Event\WhiteLabelUserCreatedEvent;
use LaunchKey\SDK\EventDispatcher\EventDispatcher;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Basic implementation of the WhiteLabelService providing event dispatching abd logging
 * @package LaunchKey\SDK\Service
 */
class BasicWhiteLabelService implements WhiteLabelService
{
    /**
     * @var ApiService
     */
    private $apiService;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var LoggerAwareInterface
     */
    private $logger;

    /**
     * @param ApiService $apiService
     * @param EventDispatcher $eventDispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        ApiService $apiService,
        EventDispatcher $eventDispatcher,
        LoggerInterface $logger = null
    )
    {
        $this->apiService = $apiService;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * @param string $identifier Permanent and unique identifier of this user within your application.  This
     * identifier will be used authenticate the user as well as pair devices additional devices to the user's account
     * within your white label group.
     * @return WhiteLabelUser
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws InvalidResponseError If the encrypted data is not valid JSON
     */
    public function createUser($identifier) {
        $this->debugLog("Initiating white label user create request", array("identifier" => $identifier));
        $user = $this->apiService->createWhiteLabelUser($identifier);
        $this->debugLog("White label user created", array("user" => $user));
        $this->eventDispatcher->dispatchEvent(WhiteLabelUserCreatedEvent::NAME, new WhiteLabelUserCreatedEvent($user));
        return $user;
    }

    /**
     * @param $message
     * @param $context
     */
    private function debugLog($message, $context)
    {
        if ($this->logger) $this->logger->debug($message, $context);
    }
}
