<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Test\Authorization;

use Facile\OAuth2\HttpClient\Authorization\NullProvider;
use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use PHPUnit\Framework\TestCase;

class NullProviderTest extends TestCase
{
    public function testShouldReturnNullOnGetAuthorization(): void
    {
        $client = $this->prophesize(ClientInterface::class);
        $request = $this->prophesize(OAuth2RequestInterface::class);

        $provider = new NullProvider();

        $this->assertNull($provider->getAuthorization($client->reveal(), $request->reveal()));
    }
}
