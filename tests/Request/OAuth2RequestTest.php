<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Test\Request;

use Facile\OAuth2\HttpClient\Request\OAuth2Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class OAuth2RequestTest extends TestCase
{
    public function testGrantParams(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $params = ['foo' => 'bar'];

        $this->assertSame([], $oauth2Request->getGrantParams());

        $requestWithGrantParams = $oauth2Request->withGrantParams($params);
        $this->assertNotSame($oauth2Request, $requestWithGrantParams);
        $this->assertSame($params, $requestWithGrantParams->getGrantParams());

        $this->assertNotSame($oauth2Request, $requestWithGrantParams->withoutGrantParam('foo'));
        $this->assertSame([], $requestWithGrantParams->withoutGrantParam('foo')->getGrantParams());

        $this->assertNotSame($oauth2Request, $requestWithGrantParams->withGrantParam('baz', 'foz'));
        $this->assertSame(['foo' => 'bar', 'baz' => 'foz'], $requestWithGrantParams->withGrantParam('baz', 'foz')->getGrantParams());
    }

    public function testShouldProxyProtocolVersion(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = 'foo';
        $request->withProtocolVersion($expected)->shouldBeCalled()->willReturn($request->reveal());
        $request->getProtocolVersion()->shouldBeCalled()->willReturn($expected);
        $requestWith = $oauth2Request->withProtocolVersion($expected);
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame($expected, $requestWith->getProtocolVersion());
    }

    public function testShouldProxyWithProtocolVersion(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $request->withProtocolVersion('foo')->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getMethod()->willReturn('baz');
        $requestWith = $oauth2Request->withProtocolVersion('foo');
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame('baz', $requestWith->getMethod());
    }

    public function testShouldProxyGetHeaders(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = [
            'Authorization' => [
                ['foo' => 'bar'],
            ],
        ];
        $request->getHeaders()->shouldBeCalled()->willReturn($expected);
        $this->assertSame($expected, $oauth2Request->getHeaders());
    }

    public function testShouldProxyHasHeader(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $request->hasHeader('Authorization')->shouldBeCalled()->willReturn(true);
        $this->assertTrue($oauth2Request->hasHeader('Authorization'));
    }

    public function testShouldProxyGetHeader(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = [
            'Authorization' => [
                ['foo' => 'bar'],
            ],
        ];
        $request->getHeader('Authorization')->shouldBeCalled()->willReturn($expected['Authorization']);
        $this->assertSame($expected['Authorization'], $oauth2Request->getHeader('Authorization'));
    }

    public function testShouldProxyGetHeaderLine(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $request->getHeaderLine('name')->shouldBeCalled()->willReturn('foo');
        $this->assertSame('foo', $oauth2Request->getHeaderLine('name'));
    }

    public function testShouldProxyWithHeader(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $request->withHeader('foo', 'bar')->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getMethod()->willReturn('baz');
        $requestWith = $oauth2Request->withHeader('foo', 'bar');
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame('baz', $requestWith->getMethod());
    }

    public function testShouldProxyWithAddedHeader(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $request->withAddedHeader('foo', 'bar')->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getMethod()->willReturn('baz');
        $requestWith = $oauth2Request->withAddedHeader('foo', 'bar');
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame('baz', $requestWith->getMethod());
    }

    public function testShouldProxyWithoutHeader(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $request->withoutHeader('foo')->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getMethod()->willReturn('baz');
        $requestWith = $oauth2Request->withoutHeader('foo');
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame('baz', $requestWith->getMethod());
    }

    public function testShouldProxyGetBody(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = $this->prophesize(StreamInterface::class);
        $request->getBody()->shouldBeCalled()->willReturn($expected->reveal());
        $this->assertSame($expected->reveal(), $oauth2Request->getBody());
    }

    public function testShouldProxyWithBody(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $body = $this->prophesize(StreamInterface::class);

        $request->withBody($body->reveal())->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getBody()->willReturn($body->reveal());
        $requestWith = $oauth2Request->withBody($body->reveal());
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame($body->reveal(), $requestWith->getBody());
    }

    public function testShouldProxyGetRequestTarget(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = 'foo';
        $request->getRequestTarget()->shouldBeCalled()->willReturn($expected);
        $this->assertSame($expected, $oauth2Request->getRequestTarget());
    }

    public function testShouldProxyWithRequestTarget(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $value = 'test';

        $request->withRequestTarget($value)->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getRequestTarget()->willReturn($value);
        $requestWith = $oauth2Request->withRequestTarget($value);
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame($value, $requestWith->getRequestTarget());
    }

    public function testShouldProxyGetMethod(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = 'foo';
        $request->getMethod()->shouldBeCalled()->willReturn($expected);
        $this->assertSame($expected, $oauth2Request->getMethod());
    }

    public function testShouldProxyWithMethod(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $value = 'test';

        $request->withMethod($value)->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getMethod()->willReturn($value);
        $requestWith = $oauth2Request->withMethod($value);
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame($value, $requestWith->getMethod());
    }

    public function testShouldProxyGetUri(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $expected = $this->prophesize(UriInterface::class);
        $request->getUri()->shouldBeCalled()->willReturn($expected->reveal());
        $this->assertSame($expected->reveal(), $oauth2Request->getUri());
    }

    public function testShouldProxyWithUri(): void
    {
        $request = $this->prophesize(RequestInterface::class);
        $newRequest = $this->prophesize(RequestInterface::class);
        $oauth2Request = new OAuth2Request($request->reveal());

        $value = $this->prophesize(UriInterface::class);

        $request->withUri($value->reveal(), false)->shouldBeCalled()->willReturn($newRequest->reveal());
        $newRequest->getUri()->willReturn($value->reveal());
        $requestWith = $oauth2Request->withUri($value->reveal());
        $this->assertNotSame($oauth2Request, $requestWith);
        $this->assertSame($value->reveal(), $requestWith->getUri());
    }
}
