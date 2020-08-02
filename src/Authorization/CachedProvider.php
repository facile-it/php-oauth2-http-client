<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Authorization;

use Facile\OAuth2\HttpClient\Exception\RuntimeException;
use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

class CachedProvider implements AuthorizationProvider
{
    /** @var CacheInterface */
    private $cache;

    /** @var int */
    private $defaultTtl;

    /** @var string */
    private $hashAlg = 'sha1';

    public function __construct(CacheInterface $cache, int $defaultTtl = 1800)
    {
        $this->cache = $cache;
        $this->defaultTtl = $defaultTtl;
    }

    public function setHashAlg(string $hashAlg): void
    {
        $this->hashAlg = $hashAlg;
    }

    private function getCacheKey(ClientInterface $client, OAuth2RequestInterface $request): string
    {
        $encoded = json_encode([
            'issuer' => $client->getIssuer()->getMetadata()->getIssuer(),
            'client_id' => $client->getMetadata()->getClientId(),
            'grantParams' => $request->getGrantParams(),
        ]);

        if (! is_string($encoded)) {
            throw new RuntimeException('Unable to create cache key');
        }

        return hash($this->hashAlg, $encoded);
    }

    public function getAuthorization(ClientInterface $client, OAuth2RequestInterface $request): ?string
    {
        return $this->cache->get($this->getCacheKey($client, $request));
    }

    public function saveAuthorization(
        ClientInterface $client,
        OAuth2RequestInterface $request,
        string $authorization,
        ?int $ttl = null
    ): void {
        $this->cache->set($this->getCacheKey($client, $request), $authorization, $ttl ?? $this->defaultTtl);
    }
}
