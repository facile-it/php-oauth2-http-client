<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Test;

use Facile\OAuth2\HttpClient\Authorization\AuthorizationProvider;
use Facile\OAuth2\HttpClient\OAuth2Plugin;
use Facile\OAuth2\HttpClient\Request\OAuth2Request;
use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Token\TokenSetInterface;
use Http\Client\Common\Plugin;
use Http\Promise\FulfilledPromise;
use Laminas\Diactoros\RequestFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OAuth2PluginTest extends TestCase
{
    public function testShouldHandleRequestWithNoAuthorization(): void
    {
        $psrRequest = (new RequestFactory())->createRequest('GET', 'https://example.com/foo');
        $request = (new OAuth2Request($psrRequest))
            ->withGrantParams([
                'actor_token' => 'foo',
            ]);

        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            null,
            [],
            false
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(200);

        $next = function () use ($response) {
            return new FulfilledPromise($response->reveal());
        };
        $first = static function (): void {
        };

        $responsePromise = $plugin->handleRequest($request, $next, $first);
        $this->assertSame($response->reveal(), $responsePromise->wait());
    }

    public function authZRequestProvider(): iterable
    {
        yield 'Normal Request' => [(new RequestFactory())->createRequest('GET', 'https://example.com/foo')];
        yield 'OAuth2Request' => [
            (new OAuth2Request((new RequestFactory())->createRequest('GET', 'https://example.com/foo')))
                ->withGrantParams([
                    'actor_token' => 'foo',
                ]),
        ];
    }

    /**
     * @dataProvider authZRequestProvider
     *
     * @param RequestInterface $request
     */
    public function testShouldHandleRequestWithRequestedAuthorization(RequestInterface $request): void
    {
        $requestGrantParams = $request instanceof OAuth2RequestInterface
            ? $request->getGrantParams()
            : [];
        $defaultGrantParams = [
            'token_type' => 'bar',
            'actor_token' => 'baz',
        ];

        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            null,
            $defaultGrantParams,
            false
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(401);

        $authorizedResponse = $this->prophesize(ResponseInterface::class);

        $tokenSet = $this->prophesize(TokenSetInterface::class);
        $tokenSet->getAccessToken()->willReturn('access-token');
        $tokenSet->getExpiresIn()->willReturn(999);
        $tokenSet->getTokenType()->willReturn('bearer');

        $authorizationService->grant($client->reveal(), array_merge(
            ['grant_type' => 'client_credentials'],
            $defaultGrantParams,
            $requestGrantParams
        ))
            ->shouldBeCalled()
            ->willReturn($tokenSet->reveal());

        $this->assertExecution(
            $plugin,
            $request,
            $response->reveal(),
            $authorizedResponse->reveal()
        );
    }

    public function testShouldHandleRequestWithoutAuthorization(): void
    {
        $request = (new RequestFactory())->createRequest('GET', 'https://example.com/foo');
        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            null,
            [],
            false
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(200);

        $authorizationService->grant(Argument::cetera())
            ->shouldNotBeCalled();

        $this->assertExecution(
            $plugin,
            $request,
            $response->reveal(),
            $response->reveal()
        );
    }

    public function testShouldNotCallAuthorizationServiceWithRequestWithAuthorizationHeader(): void
    {
        $request = (new RequestFactory())->createRequest('GET', 'https://example.com/foo')
            ->withHeader('Authorization', 'foo');

        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            null,
            [],
            true
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(200);

        $authorizationService->grant(Argument::cetera())
            ->shouldNotBeCalled();

        $next = function (RequestInterface $request) use ($response) {
            $this->assertTrue($request->hasHeader('Authorization'));
            $this->assertSame('foo', $request->getHeader('Authorization')[0]);

            return new FulfilledPromise($response->reveal());
        };

        $first = static function (): void {
        };

        $responsePromise = $plugin->handleRequest($request, $next, $first);
        $this->assertSame($response->reveal(), $responsePromise->wait());
    }

    /**
     * @dataProvider authZRequestProvider
     *
     * @param RequestInterface $request
     */
    public function testShouldAuthorizeBeforeCallWhenAuthorizationFirst(RequestInterface $request): void
    {
        $requestGrantParams = $request instanceof OAuth2RequestInterface
            ? $request->getGrantParams()
            : [];
        $defaultGrantParams = [
            'token_type' => 'bar',
            'actor_token' => 'baz',
        ];

        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            null,
            $defaultGrantParams,
            true
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(200);

        $tokenSet = $this->prophesize(TokenSetInterface::class);
        $tokenSet->getAccessToken()->willReturn('access-token');
        $tokenSet->getExpiresIn()->willReturn(999);
        $tokenSet->getTokenType()->willReturn('bearer');

        $authorizationService->grant($client->reveal(), array_merge(
            ['grant_type' => 'client_credentials'],
            $defaultGrantParams,
            $requestGrantParams
        ))
            ->shouldBeCalled()
            ->willReturn($tokenSet->reveal());

        $next = function (RequestInterface $request) use ($response) {
            $this->assertSame('Bearer access-token', $request->getHeader('Authorization')[0]);

            return new FulfilledPromise($response->reveal());
        };

        $first = static function (): void {
        };

        $responsePromise = $plugin->handleRequest($request, $next, $first);
        $this->assertSame($response->reveal(), $responsePromise->wait());
    }

    public function testShouldUseProvidedAuthorization(): void
    {
        $request = (new OAuth2Request((new RequestFactory())->createRequest('GET', 'https://example.com/foo')))
            ->withGrantParams([
                'actor_token' => 'foo',
            ]);
        $defaultGrantParams = [
            'token_type' => 'bar',
            'actor_token' => 'baz',
        ];

        $authorizationProvider = $this->prophesize(AuthorizationProvider::class);
        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            $authorizationProvider->reveal(),
            $defaultGrantParams
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(200);

        $authorizationProvider->getAuthorization($client->reveal(), Argument::type(OAuth2RequestInterface::class))
            ->shouldBeCalled()
            ->willReturn('bearer foo');

        $authorizationService->grant(Argument::cetera())
            ->shouldNotBeCalled();

        $next = function (RequestInterface $request) use ($response) {
            $this->assertTrue($request->hasHeader('Authorization'));
            $this->assertSame('bearer foo', $request->getHeader('Authorization')[0]);

            return new FulfilledPromise($response->reveal());
        };

        $first = static function (): void {
        };

        $responsePromise = $plugin->handleRequest($request, $next, $first);
        $this->assertSame($response->reveal(), $responsePromise->wait());
    }

    public function testShouldUpdateAuthorizationAndSaveIt(): void
    {
        $requestGrantParams = [
            'actor_token' => 'foo',
        ];
        $defaultGrantParams = [
            'token_type' => 'bar',
            'actor_token' => 'baz',
        ];
        $request = (new OAuth2Request((new RequestFactory())->createRequest('GET', 'https://example.com/foo')))
            ->withGrantParams($requestGrantParams);

        $authorizationProvider = $this->prophesize(AuthorizationProvider::class);
        $authorizationService = $this->prophesize(AuthorizationService::class);
        $client = $this->prophesize(ClientInterface::class);

        $plugin = new OAuth2Plugin(
            $authorizationService->reveal(),
            $client->reveal(),
            $authorizationProvider->reveal(),
            $defaultGrantParams
        );

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->shouldBeCalled()->willReturn(403);

        $authorizedResponse = $this->prophesize(ResponseInterface::class);

        $authorizationProvider->getAuthorization($client->reveal(), Argument::type(OAuth2RequestInterface::class))
            ->shouldBeCalled()
            ->willReturn('Bearer access-token1');

        $authorizationProvider->saveAuthorization(
            $client->reveal(),
            Argument::type(OAuth2RequestInterface::class),
            'Bearer access-token2',
            999
        )
            ->shouldBeCalled();

        $tokenSet = $this->prophesize(TokenSetInterface::class);
        $tokenSet->getAccessToken()->willReturn('access-token2');
        $tokenSet->getExpiresIn()->willReturn(999);
        $tokenSet->getTokenType()->willReturn('bearer');

        $authorizationService->grant($client->reveal(), array_merge(
            ['grant_type' => 'client_credentials'],
            $defaultGrantParams,
            $requestGrantParams
        ))
            ->shouldBeCalled()
            ->willReturn($tokenSet->reveal());

        $nextFuncs = [
            function (RequestInterface $request) use ($response) {
                $this->assertTrue($request->hasHeader('Authorization'));
                $this->assertSame('Bearer access-token1', $request->getHeader('Authorization')[0]);

                return new FulfilledPromise($response->reveal());
            },
            function (RequestInterface $request) use ($authorizedResponse) {
                $this->assertTrue($request->hasHeader('Authorization'));
                $this->assertSame('Bearer access-token2', $request->getHeader('Authorization')[0]);

                return new FulfilledPromise($authorizedResponse->reveal());
            },
        ];

        $nextCount = 0;

        $next = static function (RequestInterface $request) use ($nextFuncs, &$nextCount) {
            return ($nextFuncs[$nextCount++])($request);
        };

        $first = static function (): void {
        };

        $responsePromise = $plugin->handleRequest($request, $next, $first);
        $this->assertSame($authorizedResponse->reveal(), $responsePromise->wait());
    }

    private function assertExecution(
        Plugin $plugin,
        RequestInterface $request,
        ResponseInterface $response,
        ResponseInterface $authorizedResponse
    ): void {
        $nextFuncs = [
            function (RequestInterface $request) use ($response) {
                $this->assertFalse($request->hasHeader('Authorization'));

                return new FulfilledPromise($response);
            },
            function (RequestInterface $request) use ($authorizedResponse) {
                $this->assertTrue($request->hasHeader('Authorization'));
                $this->assertSame('Bearer access-token', $request->getHeader('Authorization')[0]);

                return new FulfilledPromise($authorizedResponse);
            },
        ];

        $nextCount = 0;

        $next = static function (RequestInterface $request) use ($nextFuncs, &$nextCount) {
            return ($nextFuncs[$nextCount++])($request);
        };

        $first = static function (): void {
        };

        $responsePromise = $plugin->handleRequest($request, $next, $first);
        $this->assertSame($authorizedResponse, $responsePromise->wait());
    }
}
