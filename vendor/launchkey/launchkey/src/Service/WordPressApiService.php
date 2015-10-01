<?php
/**
 * @author Adam Englander <adam@launchkey.com>
 * @copyright 2015 LaunchKey, Inc. See project license for usage.
 */

namespace LaunchKey\SDK\Service;


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
 * WordPress native implementation of the ApiService that is guaranteed to work on WordPress installs.
 *
 * @package LaunchKey\SDK\Service
 */
class WordPressApiService extends AbstractApiService implements ApiService
{
    /**
     * @var \WP_Http
     */
    private $http;

    /**
     * @var CryptService
     */
    private $cryptService;

    /**
     * @var int
     */
    private $appKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var bool
     */
    private $sslVerify;

    /**
     * @var string
     */
    private $apiBaseURL;

    /**
     * @var int
     */
    private $requestTimeout;

    /**
     * WordPressApiService constructor.
     *
     * @param \WP_Http $http
     * @param CryptService $cryptService
     * @param Cache $cache
     * @param $publicKeyTTL
     * @param $appKey
     * @param $secretKey
     * @param LoggerInterface $logger
     */
    public function __construct(
        \WP_Http $http,
        CryptService $cryptService,
        Cache $cache,
        $publicKeyTTL,
        $appKey,
        $secretKey,
        $sslVerify,
        $apiBaseURL,
        $requestTimeout,
        LoggerInterface $logger = null
    ) {
        parent::__construct($cache, $cryptService, $secretKey, $publicKeyTTL, $logger);
        $this->http = $http;
        $this->cryptService = $cryptService;
        $this->appKey = $appKey;
        $this->secretKey = $secretKey;
        $this->sslVerify = $sslVerify;
        $this->apiBaseURL = $apiBaseURL;
        $this->requestTimeout = $requestTimeout;
    }

    /**
     * Perform a ping request
     * @return PingResponse
     * @throws CommunicationError
     * @throws InvalidResponseError
     * @throws LaunchKeyEngineError
     * @throws NoPairedDevicesError
     * @throws NoSuchUserError
     * @throws RateLimitExceededError
     * @throws ExpiredAuthRequestError
     * @throws InvalidCredentialsError
     * @throws InvalidRequestError
     */
    public function ping()
    {
        $data = $this->sendRequest('/v1/ping', 'GET');
        $pingResponse = new PingResponse(
            $this->getLaunchKeyDate($data["launchkey_time"]),
            $data["key"],
            $this->getLaunchKeyDate($data["date_stamp"])
        );

        return $pingResponse;
    }

    /**
     * Perform an "auth" request
     *
     * @param string $username Username to authorize
     * @param bool $session Is the request for a user session and not a transaction
     *
     * @throws CommunicationError
     * @throws InvalidResponseError
     * @throws LaunchKeyEngineError
     * @throws NoPairedDevicesError
     * @throws NoSuchUserError
     * @throws RateLimitExceededError
     * @throws ExpiredAuthRequestError
     * @throws InvalidCredentialsError
     * @throws InvalidRequestError
     * @return AuthRequest
     */
    public function auth($username, $session)
    {
        $encryptedSecretKey = $this->getEncryptedSecretKey();
        $data = $this->sendRequest('/v1/auths', 'POST', array(
            "app_key" => $this->appKey,
            "secret_key" => base64_encode($encryptedSecretKey),
            "signature" => $this->cryptService->sign($encryptedSecretKey),
            "username" => $username,
            "session" => $session ? 1 : 0,
            "user_push_id" => 1
        ));

        return new AuthRequest($username, $session, $data["auth_request"]);
    }

    /**
     * Poll to see if the auth request is completed and approved/denied
     *
     * @param string $authRequest auth_request returned from an auth call
     *
     * @return AuthResponse
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws ExpiredAuthRequestError If the auth request has expired
     * @throws InvalidResponseError
     */
    public function poll($authRequest)
    {
        $encryptedSecretKey = $this->getEncryptedSecretKey();
        try {
            $data = $this->sendRequest('/v1/poll', 'POST',
                array(
                    "app_key" => $this->appKey,
                    "secret_key" => base64_encode($encryptedSecretKey),
                    "signature" => $this->cryptService->sign($encryptedSecretKey),
                    "auth_request" => $authRequest
                ),
                array('METHOD' => 'GET')
            );
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
     *
     * @return null
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws LaunchKeyEngineError If the LaunchKey cannot apply the request auth request, action, status
     */
    public function log($authRequest, $action, $status)
    {
        $encryptedSecretKey = $this->getEncryptedSecretKey();
        $this->sendRequest('/v1/logs', 'PUT', array(
            'app_key' => $this->appKey,
            'secret_key' => base64_encode($encryptedSecretKey),
            'signature' => $this->cryptService->sign($encryptedSecretKey),
            'auth_request' => $authRequest,
            'action' => $action,
            'status' => $status ? 'True' : 'False'
        ));
    }

    /**
     * Create a white label user with the following identifier
     *
     * @param $identifier Unique and permanent identifier for the user in the white label application.  This identifier
     * will be used in all future communications regarding this user.  As such, it cannot ever change.
     *
     * @return WhiteLabelUser
     * @throws CommunicationError If there was an error communicating with the endpoint
     * @throws InvalidCredentialsError If the credentials supplied to the endpoint were invalid
     * @throws InvalidRequestError If the endpoint proclaims the request invalid
     * @throws InvalidResponseError
     */
    public function createWhiteLabelUser($identifier)
    {
        $requestData = array(
            "app_key" => $this->appKey,
            "secret_key" => base64_encode($this->getEncryptedSecretKey()),
            "identifier" => $identifier
        );
        $data = $this->sendRequest('/v1/users', 'POST', $requestData, array(), 'application/json');
        $cipher = $this->cryptService->decryptRSA($data['response']["cipher"]);
        $key = substr($cipher, 0, strlen($cipher) - 16);
        $iv = substr($cipher, - 16);
        $userJsonData = $this->cryptService->decryptAES($data['response']["data"], $key, $iv);
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
     * @param string $path
     * @param string $method
     * @param array $data
     * @param string $contentType
     *
     * @return array
     * @throws CommunicationError
     * @throws Exception\InvalidResponseError
     * @throws Exception\LaunchKeyEngineError
     * @throws Exception\NoPairedDevicesError
     * @throws Exception\NoSuchUserError
     * @throws Exception\RateLimitExceededError
     * @throws ExpiredAuthRequestError
     * @throws InvalidCredentialsError
     * @throws InvalidRequestError
     */
    private function sendRequest(
        $path,
        $method,
        array $data = array(),
        array $parameters = array(),
        $contentType = 'application/x-www-form-urlencoded'
    ) {
        $headers = array(
            'Accept' => 'application/json',
            /**
             * "Connection: close" header must be set for performance issues with thh WP_Http_Streams provider.
             *
             * The WP_Http_Streams provider waits for the connection to close to determine that it has received all
             * of the data from the server.  If the server does not close the connection, the stream context will wait
             * for the request to time out which is not optimal.  This issue was identified by WordPress in bug ticket
             * #23463 and resolved in WordPress 3.7.0.  The work-around is specifically im place for WordPress < 3.7.0
             *
             * @link https://core.trac.wordpress.org/ticket/23463
             */
            'Connection' => 'close'
        );

        if (!empty($data)) {
            $headers['Content-Type'] = $contentType;

            if ($contentType === 'application/x-www-form-urlencoded') {
                $body = http_build_query($data);
            } elseif ($contentType === 'application/json') {
                $body = json_encode($data);
                $parameters['signature'] = $this->cryptService->sign($body);
            }
        } else {
            $body = null;
        }

        if (!empty($parameters)) {
            $path .= '?' . http_build_query($parameters);
        }

        $this->debugLog("Sending request",
            array('path' => $path, 'method' => $method, 'headers' => $headers, 'body' => $body));
        $response = $this->http->request($this->getUrl($path), array(
            'method' => $method,
            'timeout' => $this->requestTimeout,
            'redirection' => 0,
            'httpversion' => '1.1',
            'sslverify' => $this->sslVerify,
            'body' => $body,
            'headers' => $headers
        ));

        if ($response instanceof \WP_Error) {
            $msg = implode(' => ', $response->get_error_messages());
            throw new CommunicationError($msg);
        } else {
            $this->debugLog("Response received", array($response));
            $data = $this->jsonDecodeData($response['body']);
            if (!in_array($response['response']['code'], array(200, 201))) {
                $this->throwExceptionForErrorResponse($data);
            }
        }

        return $data;
    }

    private function getUrl($path)
    {
        return $this->apiBaseURL . $path;
    }
}
