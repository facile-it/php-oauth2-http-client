<?php

declare(strict_types=1);

use Facile\OAuth2\HttpClient\OAuth2Plugin;
use Facile\OAuth2\HttpClient\Request\OAuth2Request;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Service\AuthorizationService;
use Http\Client\Common\PluginClient;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

require __DIR__ . '../vendor/autoload.php';

$subjectToken = <<<'TOKEN'
--- User JWT Token ---
TOKEN;

// build the issuer fetching configuration...
$issuer = $issuer = (new IssuerBuilder())->build('https://example.com');
// build your client...
$client = (new ClientBuilder())
    ->setIssuer($issuer)
    ->setClientMetadata(ClientMetadata::fromArray([
        'client_id' => 'example-confidential',
        'client_secret' => 'client-secret',
    ]))
    ->build();

$authorizationService = new AuthorizationService();

// create the plugin
$plugin = new OAuth2Plugin(
    $authorizationService,
    $client,
    null,
    [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
        'subject_token_type' => 'urn:ietf:params:oauth:token-type:access_token',
        'audience' => 'resource-server',
    ]
);

$httpClient = new PluginClient(Psr18ClientDiscovery::find(), [$plugin]);

// create a request
$request = Psr17FactoryDiscovery::findRequestFactory()
    ->createRequest('GET', 'https://resource-server.com/api/resource')
    ->withHeader('accept', 'application/json');

// create an OAuth2Request
$oauth2Request = (new OAuth2Request($request))
    ->withGrantParams([
        'subject_token' => $subjectToken,
    ]);

$response = $httpClient->sendRequest($oauth2Request);

var_dump([
    'status_code' => $response->getStatusCode(),
    'body' => (string) $response->getBody(),
]);
