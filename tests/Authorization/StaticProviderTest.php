<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Test\Authorization;

use Facile\OAuth2\HttpClient\Authorization\StaticProvider;
use Facile\OAuth2\HttpClient\Request\OAuth2RequestInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use PHPUnit\Framework\TestCase;

class StaticProviderTest extends TestCase
{
    public function testShouldReturnProvidedAuthorization(): void
    {
        $authorizationValue = 'Bearer foo';
        $client = $this->prophesize(ClientInterface::class);
        $request = $this->prophesize(OAuth2RequestInterface::class);
        $provider = new StaticProvider($authorizationValue);

        $this->assertSame($authorizationValue, $provider->getAuthorization($client->reveal(), $request->reveal()));
    }
}
