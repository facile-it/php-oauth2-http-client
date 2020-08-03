<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient;

use Facile\OAuth2\HttpClient\Authorization\AuthorizationProvider;
use Facile\OAuth2\HttpClient\Authorization\NullProvider;
use Facile\OAuth2\HttpClient\Exception\RuntimeException;
use Facile\OAuth2\HttpClient\Request\OAuth2Request;
use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Token\TokenSetInterface;
use Http\Client\Common\Plugin;
use Http\Promise\Promise;
use function in_array;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class OAuth2Plugin implements Plugin
{
    /** @var AuthorizationService */
    private $authorizationService;

    /** @var ClientInterface */
    private $client;

    /** @var bool */
    private $authenticateFirst;

    /** @var AuthorizationProvider */
    private $authorizationProvider;

    /** @var array<string, mixed> */
    private $grantParams;

    /** @var array<string, mixed> */
    private $defaultParams = [
        'grant_type' => 'client_credentials',
    ];

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param AuthorizationService $authorizationService Authorization Service
     * @param ClientInterface $client OAuth2 client
     * @param AuthorizationProvider|null $tokenProvider Token provider
     * @param array<string, mixed> $grantParams Grant parameters to use
     * @param bool $authorizationFirst Whether to authenticate before a request instead to try without authorization
     */
    public function __construct(
        AuthorizationService $authorizationService,
        ClientInterface $client,
        ?AuthorizationProvider $tokenProvider = null,
        array $grantParams = [],
        bool $authorizationFirst = true
    ) {
        $this->authorizationService = $authorizationService;
        $this->client = $client;
        $this->authorizationProvider = $tokenProvider ?? new NullProvider();
        $this->grantParams = array_merge(
            $this->defaultParams,
            $grantParams
        );
        $this->authenticateFirst = $authorizationFirst;
        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
        $request = $this->prepareRequest($request);

        /** @var Promise $promise */
        $promise = $next($request);

        return $promise->then(function (ResponseInterface $response) use ($next, $request): ResponseInterface {
            return $this->postRequest($request, $response, $next);
        });
    }

    private function postRequest(OAuth2RequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        if (in_array($response->getStatusCode(), [401, 403], true)) {
            // try to authenticate or re-authenticate for a new token
            $this->logger->debug('Received status code {status_code} from resource server', ['status_code' => $response->getStatusCode()]);

            $request = $this->authorize($request);

            return $next($request)->wait();
        }

        return $response;
    }

    private function prepareRequest(RequestInterface $request): OAuth2RequestInterface
    {
        if (! $request instanceof OAuth2RequestInterface) {
            $request = new OAuth2Request($request);
        }

        // Merge request grant params
        $request = $request->withGrantParams(array_merge($this->grantParams, $request->getGrantParams()));

        if ($request->hasHeader('Authorization')) {
            return $request;
        }

        $authorization = $this->authorizationProvider->getAuthorization($this->client, $request);

        if (null !== $authorization) {
            // if we have a provided access token, we use it for the first request
            return $this->createRequest($request, $authorization);
        }

        if ($this->authenticateFirst) {
            // if we need to authenticate before a request, we trigger the authentication
            return $this->authorize($request);
        }

        return $request;
    }

    private function createRequest(OAuth2RequestInterface $request, string $authorization): OAuth2RequestInterface
    {
        return $request->withoutHeader('Authorization')
            ->withHeader('Authorization', $authorization);
    }

    private function authorize(OAuth2RequestInterface $request): OAuth2RequestInterface
    {
        $startTime = microtime(true);
        $this->logger->debug('Starting authorization');

        $tokenSet = $this->authorizationService->grant($this->client, $request->getGrantParams());

        $this->logger->debug('Authorization received', ['duration' => microtime(true) - $startTime]);

        $accessToken = $this->parseAccessToken($tokenSet);

        $this->logger->debug('Received access token', ['access_token' => $accessToken]);

        $authorization = 'Bearer ' . $accessToken;
        $this->authorizationProvider->saveAuthorization($this->client, $request, $authorization, $tokenSet->getExpiresIn());

        return $this->createRequest($request, $authorization);
    }

    private function parseAccessToken(TokenSetInterface $tokenSet): string
    {
        $accessToken = $tokenSet->getAccessToken();

        if (null === $accessToken) {
            throw new RuntimeException('Unable to get a valid access token');
        }

        $tokenType = is_string($tokenSet->getTokenType()) ? strtolower($tokenSet->getTokenType()) : null;
        if (null !== $tokenType && 'bearer' !== $tokenType) {
            throw new RuntimeException(sprintf('Only Bearer token are supported, provided "%s" is not supported', $tokenType));
        }

        return $accessToken;
    }
}
