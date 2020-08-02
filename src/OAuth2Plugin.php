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

    /**
     * @param AuthorizationService $authorizationService Authorization Service
     * @param ClientInterface $client OAuth2 client
     * @param AuthorizationProvider|null $tokenProvider Token provider
     * @param array<string, mixed> $grantParams Grant parameters to use
     * @param bool $authenticateFirst Whether to authenticate before a request instead to try without authorization
     */
    public function __construct(
        AuthorizationService $authorizationService,
        ClientInterface $client,
        ?AuthorizationProvider $tokenProvider = null,
        array $grantParams = [],
        bool $authenticateFirst = true
    ) {
        $this->authorizationService = $authorizationService;
        $this->client = $client;
        $this->authorizationProvider = $tokenProvider ?? new NullProvider();
        $this->grantParams = $grantParams;
        $this->authenticateFirst = $authenticateFirst;
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
        $tokenSet = $this->authorizationService->grant($this->client, $request->getGrantParams());

        $accessToken = $this->parseAccessToken($tokenSet);

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
