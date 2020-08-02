<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Test\Authorization;

use Facile\OAuth2\HttpClient\Authorization\CachedProvider;
use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadataInterface;
use Facile\OpenIDClient\Issuer\IssuerInterface;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadataInterface;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CachedProviderTest extends TestCase
{
    public function testShouldReturnCachedValue(): void
    {
        $issuer = $this->prophesize(IssuerInterface::class);
        $issuerMetadata = $this->prophesize(IssuerMetadataInterface::class);
        $clientMetadata = $this->prophesize(ClientMetadataInterface::class);
        $client = $this->prophesize(ClientInterface::class);
        $client->getMetadata()->willReturn($clientMetadata->reveal());
        $client->getIssuer()->willReturn($issuer->reveal());
        $issuer->getMetadata()->willReturn($issuerMetadata->reveal());
        $clientMetadata->getClientId()->willReturn('client-id');
        $issuerMetadata->getIssuer()->willReturn('https://issuer.com');
        $request = $this->prophesize(OAuth2RequestInterface::class);

        $cache = $this->prophesize(CacheInterface::class);

        $grantParams = [
            'foo' => 'bar',
        ];

        $expectedCacheKey = hash('sha1', json_encode([
            'issuer' => 'https://issuer.com',
            'client_id' => 'client-id',
            'grantParams' => $grantParams,
        ]));

        $request->getGrantParams()->willReturn($grantParams);
        $cache->get($expectedCacheKey)->willReturn('Bearer foo');

        $expected = 'Bearer foo';
        $provider = new CachedProvider($cache->reveal());
        $this->assertSame($expected, $provider->getAuthorization($client->reveal(), $request->reveal()));
    }

    public function testShouldSaveCachedValue(): void
    {
        $issuer = $this->prophesize(IssuerInterface::class);
        $issuerMetadata = $this->prophesize(IssuerMetadataInterface::class);
        $clientMetadata = $this->prophesize(ClientMetadataInterface::class);
        $client = $this->prophesize(ClientInterface::class);
        $client->getMetadata()->willReturn($clientMetadata->reveal());
        $client->getIssuer()->willReturn($issuer->reveal());
        $issuer->getMetadata()->willReturn($issuerMetadata->reveal());
        $clientMetadata->getClientId()->willReturn('client-id');
        $issuerMetadata->getIssuer()->willReturn('https://issuer.com');
        $request = $this->prophesize(OAuth2RequestInterface::class);

        $cache = $this->prophesize(CacheInterface::class);

        $grantParams = [
            'foo' => 'bar',
        ];

        $expectedCacheKey = hash('sha1', json_encode([
            'issuer' => 'https://issuer.com',
            'client_id' => 'client-id',
            'grantParams' => $grantParams,
        ]));

        $expected = 'Bearer foo';

        $request->getGrantParams()->willReturn($grantParams);
        $cache->set($expectedCacheKey, $expected, 101)
            ->shouldBeCalled()
            ->willReturn('Bearer foo');

        $provider = new CachedProvider($cache->reveal());
        $provider->saveAuthorization($client->reveal(), $request->reveal(), $expected, 101);
    }

    public function testShouldSaveCachedValueWithProvidedHashAlg(): void
    {
        $issuer = $this->prophesize(IssuerInterface::class);
        $issuerMetadata = $this->prophesize(IssuerMetadataInterface::class);
        $clientMetadata = $this->prophesize(ClientMetadataInterface::class);
        $client = $this->prophesize(ClientInterface::class);
        $client->getMetadata()->willReturn($clientMetadata->reveal());
        $client->getIssuer()->willReturn($issuer->reveal());
        $issuer->getMetadata()->willReturn($issuerMetadata->reveal());
        $clientMetadata->getClientId()->willReturn('client-id');
        $issuerMetadata->getIssuer()->willReturn('https://issuer.com');
        $request = $this->prophesize(OAuth2RequestInterface::class);

        $cache = $this->prophesize(CacheInterface::class);

        $hashAlg = 'sha256';
        $grantParams = [
            'foo' => 'bar',
        ];

        $expectedCacheKey = hash($hashAlg, json_encode([
            'issuer' => 'https://issuer.com',
            'client_id' => 'client-id',
            'grantParams' => $grantParams,
        ]));

        $expected = 'Bearer foo';

        $request->getGrantParams()->willReturn($grantParams);
        $cache->set($expectedCacheKey, $expected, 101)
            ->shouldBeCalled()
            ->willReturn('Bearer foo');

        $provider = new CachedProvider($cache->reveal());
        $provider->setHashAlg($hashAlg);
        $provider->saveAuthorization($client->reveal(), $request->reveal(), $expected, 101);
    }

    public function testShouldSaveCachedValueWithDefaultTtl(): void
    {
        $issuer = $this->prophesize(IssuerInterface::class);
        $issuerMetadata = $this->prophesize(IssuerMetadataInterface::class);
        $clientMetadata = $this->prophesize(ClientMetadataInterface::class);
        $client = $this->prophesize(ClientInterface::class);
        $client->getMetadata()->willReturn($clientMetadata->reveal());
        $client->getIssuer()->willReturn($issuer->reveal());
        $issuer->getMetadata()->willReturn($issuerMetadata->reveal());
        $clientMetadata->getClientId()->willReturn('client-id');
        $issuerMetadata->getIssuer()->willReturn('https://issuer.com');
        $request = $this->prophesize(OAuth2RequestInterface::class);

        $cache = $this->prophesize(CacheInterface::class);

        $grantParams = [
            'foo' => 'bar',
        ];

        $expectedCacheKey = hash('sha1', json_encode([
            'issuer' => 'https://issuer.com',
            'client_id' => 'client-id',
            'grantParams' => $grantParams,
        ]));

        $expected = 'Bearer foo';

        $request->getGrantParams()->willReturn($grantParams);
        $cache->set($expectedCacheKey, $expected, 501)
            ->shouldBeCalled()
            ->willReturn('Bearer foo');

        $provider = new CachedProvider($cache->reveal(), 501);
        $provider->saveAuthorization($client->reveal(), $request->reveal(), $expected);
    }
}
