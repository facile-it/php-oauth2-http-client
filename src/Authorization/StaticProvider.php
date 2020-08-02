<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Authorization;

use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;

class StaticProvider implements AuthorizationProvider
{
    /** @var string */
    private $authorization;

    public function __construct(string $authorization)
    {
        $this->authorization = $authorization;
    }

    public function getAuthorization(ClientInterface $client, OAuth2RequestInterface $request): ?string
    {
        return $this->authorization;
    }

    public function saveAuthorization(
        ClientInterface $client,
        OAuth2RequestInterface $request,
        string $authorization,
        ?int $ttl = null
    ): void {
    }
}
