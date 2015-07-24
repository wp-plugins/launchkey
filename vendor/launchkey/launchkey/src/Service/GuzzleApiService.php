<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */

namespace LaunchKey\SDK\Service;


use Guzzle\Http\ClientInterface;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Exception\ServerErrorResponseException;
use Guzzle\Http\Message\RequestInterface;
use LaunchKey\SDK\Cache\Cache;
use LaunchKey\SDK\Domain\AuthRequest;
use LaunchKey\SDK\Domain\AuthResponse;
use LaunchKey\SDK\Domain\PingResponse;
use LaunchKey\SDK\Domain\WhiteLabelUser;
use LaunchKey\SDK\Service\Exception\CommunicationError;
use LaunchKey\SDK\Service\Exception\ExpiredAuthRequestError;
use LaunchKey\SDK\Service\Exception\InvalidCredentialsError;
use LaunchKey\SDK\Service\Exception\InvalidRequestError;
use LaunchKey\SDK\Service\Exception\InvalidResponseError;
use LaunchKey\SDK\Service\Exception\LaunchKeyEngineError;
use LaunchKey\SDK\Service\Exception\NoPairedDevicesError;
use LaunchKey\SDK\Service\Exception\NoSuchUserError;
use LaunchKey\SDK\Service\Exception\RateLimitExceededError;
use Psr\Log\LoggerInterface;

/**
 * ApiService implementation utilizing Guzzle3 as the HTTP client
 *
 * @package LaunchKey\SDK\Service
 */
class GuzzleApiService extends AbstractApiService implements ApiService
{
    /**
     * @var string
     */
    private $appKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var ClientInterface
     */
    private $guzzleClient;

    /**
     * @var CryptService
     */
    private $cryptService;

    /**
     * @param string $appKey
     * @param string $secretKey
     * @param ClientInterface $guzzleClient
     * @param CryptService $cryptService
     * @param Cache $cache Cache implementation to be used for caching public keys
     * @param int $publicKeyTTL Number of seconds a public key should live in the cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        $appKey,
        $secretKey,
        ClientInterface $guzzleClient,
        CryptService $cryptService,
        Cache $cache,
        $publicKeyTTL,
        LoggerInterface $logger = null
    ) {
        parent::__construct($cache, $cryptService, $secretKey, $publicKeyTTL, $logger);
        $this->appKey = $appKey;
        $this->secretKey = $secretKey;
        $this->guzzleClient = $guzzleClient;
        $this->cryptService = $cryptService;

    }

    /**
     * Perform a ping request
     * @return PingResponse
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     */
    public function ping()
    {
        $request = $this->guzzleClient->get( "/v1/ping" );
        $data    = $this->sendRequest( $request );

        $pingResponse = new PingResponse(
            $this->getLaunchKeyDate( $data["launchkey_time"] ),
            $data["key"],
            $this->getLaunchKeyDate( $data["date_stamp"] )
        );

        return $pingResponse;
    }

    /**
     * Perform an "auth" request
     *
     * @param string $username Username to authorize
     * @param bool $session Is the request for a user session and not a transaction
     * @return AuthRequest
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws NoPairedDevicesError If the account for the provided username has no paired devices with which to respond
     * @throws NoSuchUserError If the username provided does not exist
     * @throws RateLimitExceededError If the same username is requested to often and exceeds the rate limit
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     */
    public function auth($username, $session)
    {
        $encryptedSecretKey = $this->getEncryptedSecretKey();
        $request = $this->guzzleClient->post("/v1/auths")
            ->addPostFields(array(
                "app_key" => $this->appKey,
                "secret_key" => base64_encode($encryptedSecretKey),
                "signature" => $this->cryptService->sign($encryptedSecretKey),
                "username" => $username,
                "session" => $session ? 1 : 0,
                "user_push_id" => 1
            ));
        $data = $this->sendRequest($request);
        return new AuthRequest($username, $session, $data["auth_request"]);
    }

    /**
     * Poll to see if the auth request is completed and approved/denied
     *
     * @param string $authRequest auth_request returned from an auth call
     * @return AuthResponse
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws ExpiredAuthRequestError If the auth request has expired
     */
    public function poll($authRequest)
    {
        $encryptedSecretKey = $this->getEncryptedSecretKey();
        $request = $this->guzzleClient->post("/v1/poll")
            ->addPostFields(array(
                "app_key" => $this->appKey,
                "secret_key" => base64_encode($encryptedSecretKey),
                "signature" => $this->cryptService->sign($encryptedSecretKey),
                "auth_request" => $authRequest
            ));
        $request->getQuery()->add("METHOD", "GET");
        try {
            $data = $this->sendRequest($request);
            $auth = json_decode($this->cryptService->decryptRSA($data['auth']), true);
            $response = new AuthResponse(
                true,
                $auth["auth_request"],
                $data["user_hash"],
                isset($data["organization_user"]) ? $data["organization_user"] : null,
                $data["user_push_id"],
                $auth["device_id"],
                $auth["response"] == "true"
            );
        } catch (InvalidRequestError $e) {
            if ($e->getCode() == 70403) {
                $response = new AuthResponse();
            } else {
                throw $e;
            }
        }
        return $response;

    }

    /**
     * Update the LaunchKey Engine with the current status of the auth request or user session
     *
     * @param string $authRequest auth_request returned from an auth call
     * @param string $action Action to log.  i.e. Authenticate, Revoke, etc.
     * @param bool $status
     * @return null
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws ExpiredAuthRequestError If the auth request has expired
     * @throws LaunchKeyEngineError If the LaunchKey cannot apply the request auth request, action, status
     */
    public function log($authRequest, $action, $status)
    {
        $encryptedSecretKey = $this->getEncryptedSecretKey();
        $request = $this->guzzleClient->put("/v1/logs")
            ->addPostFields(array(
                "app_key" => $this->appKey,
                "secret_key" => base64_encode($encryptedSecretKey),
                "signature" => $this->cryptService->sign($encryptedSecretKey),
                "auth_request" => $authRequest,
                "action" => $action,
                "status" => $status ? "True" : "False"
            ));
        $this->sendRequest($request);
    }

    /**
     * Create a white label user with the following identifier
     *
     * @param string $identifier Unique and permanent identifier for the user in the white label application.  This identifier
     * will be used in all future communications regarding this user.  As such, it cannot ever change.
     *
     * @return WhiteLabelUser
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws InvalidResponseError If the encrypted data is not valid JSON
     */
    public function createWhiteLabelUser($identifier)
    {
        $body = json_encode(array(
            "app_key" => $this->appKey,
            "secret_key" => base64_encode($this->getEncryptedSecretKey()),
            "identifier" => $identifier
        ));
        $request = $this->guzzleClient->post("/v1/users")
            ->setBody($body, "application/json");
        $request->getQuery()->add("signature", $this->cryptService->sign($body));
        $data = $this->sendRequest($request);
        $cipher = $this->cryptService->decryptRSA($data["cipher"]);
        $key = substr($cipher, 0, strlen($cipher) - 16);
        $iv = substr($cipher, -16);
        $userJsonData = $this->cryptService->decryptAES($data["data"], $key, $iv);
        try {
            $userData = $this->jsonDecodeData($userJsonData);
        } catch (InvalidResponseError $e) {
            throw new InvalidResponseError("Response data is not valid JSON when decrypted", $e->getCode(), $e);
        }
        return new WhiteLabelUser(
            $userData["qrcode"],
            $userData["code"]
        );
    }

    /**
     * @param RequestInterface $request
     * @return array
     * @throws CommunicationError
     * @throws ExpiredAuthRequestError
     * @throws InvalidCredentialsError
     * @throws InvalidRequestError
     * @throws InvalidResponseError
     * @throws LaunchKeyEngineError
     * @throws NoPairedDevicesError
     * @throws NoSuchUserError
     * @throws RateLimitExceededError
     */
    private function sendRequest(RequestInterface $request)
    {
        try {
            $response = $request->send();
            $this->debugLog("Response received", array("response" => $response->getMessage()));
        } catch (ClientErrorResponseException $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
            try {
                $data = $this->jsonDecodeData($request->getResponse()->getBody());
                $this->throwExceptionForErrorResponse($data, $e);
            } catch (InvalidResponseError $de) {
                throw new InvalidRequestError($message, $code, $e);
            }
        } catch (ServerErrorResponseException $e) {
            throw new CommunicationError("Error performing request", $e->getCode(), $e);
        }

        $data = $this->jsonDecodeData($response->getBody(true));
        // If debug response with data in the "response" attribute return that
        return isset($data["response"]) ? $data["response"] : $data;
    }
}
