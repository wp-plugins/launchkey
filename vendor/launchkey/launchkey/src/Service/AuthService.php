<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */
namespace LaunchKey\SDK\Service;

use LaunchKey\SDK\Domain\AuthRequest;
use LaunchKey\SDK\Domain\AuthResponse;
use LaunchKey\SDK\Domain\DeOrbitCallback;

/**
 * Interface for services providing user authorization/authentication actions
 *
 * @package LaunchKey\SDK\Service
 */
interface AuthService
{
    /**
     * Authorize a transaction for the provided username
     *
     * @param string $username LaunchKey username, user hash, or internal identifier for the user
     * @return AuthRequest
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws NoPairedDevicesError If the account for the provided username has no paired devices with which to respond
     * @throws NoSuchUserError If the username provided does not exist
     * @throws RateLimitExceededError If the same username is requested to often and exceeds the rate limit
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     */
    public function authorize($username);

    /**
     * Request a user session for the provided username
     *
     * @param string $username LaunchKey username, user hash, or internal identifier for the user
     * @return AuthRequest
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws NoPairedDevicesError If the account for the provided username has no paired devices with which to respond
     * @throws NoSuchUserError If the username provided does not exist
     * @throws RateLimitExceededError If the same username is requested to often and exceeds the rate limit
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     */
    public function authenticate($username);

    /**
     * Get the status of a previous authorize or authenticate.  This method can be used after a user has
     * successfully authenticate to determine if the user has submitted a de-orbit request and authorization
     * for a session has been revoked.
     *
     * @param string $authRequestId authRequestId from the AuthRequest object returned from a previous authorize
     * or authenticate call.
     * @return AuthResponse
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws ExpiredAuthRequestError If the auth request has expired
     */
    public function getStatus($authRequestId);

    /**
     * Revoke the authorization for a session.  This method is to be called after a user is logged out of the
     * application in order to update the LaunchKey or white label application of the status of the authenticated
     * session.
     *
     * @param $authRequestId authRequestId from the AuthRequest object returned from a previous authorize
     * or authenticate call.
     * @return null
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws ExpiredAuthRequestError If the auth request has expired
     * @throws LaunchKeyEngineError If the LaunchKey cannot apply the request auth request, action, status
     */
    public function deOrbit($authRequestId);

    /**
     * Handle a callback request from the LaunchKey Engine.  This data is an associative array of query string key value
     * pairs from the callback POST.  This can be the global $_GET array or an array of query parameters provided by an
     * MVC framework like Zend, Cake, Symfony, etc.
     *
     * @param array $queryParameters Key/value pairs derived from the query string
     * @return AuthResponse|DeOrbitCallback
     */
    public function handleCallback(array $queryParameters);
}