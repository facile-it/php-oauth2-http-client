# php-oauth2-http-client

HTTPPlug plugin for OAuth2 authorization.

[![Latest Stable Version](https://poser.pugx.org/facile-it/php-oauth2-http-client/v/stable)](https://packagist.org/packages/facile-it/php-oauth2-http-client)
[![Total Downloads](https://poser.pugx.org/facile-it/php-oauth2-http-client/downloads)](https://packagist.org/packages/facile-it/php-oauth2-http-client)
[![License](https://poser.pugx.org/facile-it/php-oauth2-http-client/license)](https://packagist.org/packages/facile-it/php-oauth2-http-client)
[![Code Coverage](https://scrutinizer-ci.com/g/facile-it/php-oauth2-http-client/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/facile-it/php-oauth2-http-client/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/facile-it/php-oauth2-http-client/badges/build.png?b=master)](https://scrutinizer-ci.com/g/facile-it/php-oauth2-http-client/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/facile-it/php-oauth2-http-client/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/facile-it/php-oauth2-http-client/?branch=master)

This package allows you to use a compatible PSR-18 HTTP client and handle OAuth2 authorization when making a request to an external protected resource.

This package is based on [facile-it/php-openid-client](https://github.com/facile-it/php-openid-client) to handle
authentication. You need to understand how to use it, specially on creating a Client.

## Installation

```
composer require facile-it/php-oauth2-http-client
```

## HTTPlug Plugin

This library provides you an [HTTPlug](http://httplug.io/) plugin to handle authorization, so you need to create an instance of it.

The Client will be used for authentication and requests to the token endpoint.

When the resource server answers with a `401` or `403` status code, this plugin try to make an authorization request to 
obtain a Bearer Access Token, and retry the request with the filled `Authorization` header.
By default, an authorization request to obtain an access token is always made before to execute the real request. 

```php
// facile-it/php-openid-client dependencies
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OAuth2\HttpClient\OAuth2Plugin;

// create an OIDC/OAuth2 client and the AuthorizationService from facile-it/php-openid-client
/** @var AuthorizationService $authorizationService */
/** @var ClientInterface $client */

$oauth2Plugin = new OAuth2Plugin($authorizationService, $client);
```

Now you can inject the plugin on your client.

## Usage with a PSR-18 HTTP client

To use a PSR-18 client you can use our plugin instance created before and use the PluginClient decorator from 
[`php-http/client-common`](https://github.com/php-http/client-common):

```php
use Facile\OAuth2\HttpClient\OAuth2Plugin;
use Psr\Http\Client\ClientInterface;
use Http\Client\Common\PluginClient;

// create the plugin instance like the previous example
/** @var OAuth2Plugin $oauth2Plugin */
// use your PSR-18 HTTP client
/** @var ClientInterface $psrHttpClient */

// use the PluginClient class from php-http/client-common to decorate your client and use the plugin
$httpClient = new PluginClient($psrHttpClient, [$oauth2Plugin]);
```

## Advanced usage for production environments

There are some improvements that we can do to customize authorization behaviour and to improve performance in 
production environments.

### Custom grant parameters

You can configure the plugin to use default parameters to use in the OAuth2 token request:

```php
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OAuth2\HttpClient\OAuth2Plugin;

// create an OIDC/OAuth2 client and the AuthorizationService from facile-it/php-openid-client
/** @var AuthorizationService $authorizationService */
/** @var ClientInterface $client */

$oauth2Plugin = new OAuth2Plugin(
    $authorizationService,
    $client,
    null,
    [
        // custom default grant parameters
        'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
    ]
);
```

Optionally, you can create a custom `OAuth2Request` (it's a PSR-7 Request decorator) to use grant parameters for a 
single request.  
Request grant parameters will be merged with the default grant parameters injected in the plugin.

```php
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestInterface;
use Facile\OAuth2\HttpClient\Request\OAuth2Request;

// use your PSR-18 HTTP client configured with our plugin
/** @var HttpClient $psrHttpClient */
// your PSR-7 HTTP request
/** @var RequestInterface $request */

$oauth2Request = (new OAuth2Request($request))
    ->withGrantParams([
        // request grant parameters
        'my-custom-param' => 'my-value',
    ]);
$response = $psrHttpClient->sendRequest($oauth2Request);
```

### Token-Exchange

With the ability to use custom grant parameters for each request, is simple to exchange tokens 
(see [Token-Exchange (RFC8693)](https://tools.ietf.org/html/rfc8693)).

Image that your API resources (service A) are protected, and the user should have his personal access-token 
(the subject token, maybe a JWT with user infos).
Then, service A needs to make a request to another protected Resource Server (service B).
You can't use the same access token provided by the user because the JWT audience is just for the service A, so you
need to exchange the token with another one with the audience for the service B.

```php
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OAuth2\HttpClient\OAuth2Plugin;
use Facile\OAuth2\HttpClient\Request\OAuth2Request;

// create an OIDC/OAuth2 client and the AuthorizationService from facile-it/php-openid-client
/** @var AuthorizationService $authorizationService */
/** @var ClientInterface $client */

$plugin = new OAuth2Plugin(
    $authorizationService,
    $client,
    null,
    [
         // inject default parameters:
        'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
        'subject_token_type' => 'urn:ietf:params:oauth:token-type:access_token',
        'audience' => 'my-resource-server',
    ]
);

// use your PSR-18 HTTP client configured with our plugin
/** @var HttpClient $apiClient */
// your HTTP request
/** @var RequestInterface $request */

// the subject token can be the access token provided by the user requesting your APIs
$subjectToken = '';

// then you need to call another service (my-resource-server), but you need another access token with the right audience
$apiRequest = (new OAuth2Request($request))
    ->withGrantParams([
        'subject_token' => $subjectToken, // the subject token
    ]);
$response = $apiClient->sendRequest($apiRequest);
```

### Cached Authorization

To improve performance and avoid to fetch tokens when not necessary we can cache tokens using the `CachedProvider`.

The cache is based on the client, the request URI host, and grant parameters.

```php
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OAuth2\HttpClient\OAuth2Plugin;
use Facile\OAuth2\HttpClient\Authorization\CachedProvider;
use Psr\SimpleCache\CacheInterface;

// create an OIDC/OAuth2 client and the AuthorizationService from facile-it/php-openid-client
/** @var AuthorizationService $authorizationService */
/** @var ClientInterface $client */
// use your PSR-16 simple-cache implementation
/** @var CacheInterface $cache */

$oauth2Plugin = new OAuth2Plugin(
    $authorizationService,
    $client,
    new CachedProvider($cache /*, default TTL (in seconds) = 1800 */)
);
```

### Authorization by request

Sometimes, only few resources are protected, so you don't always need to make and authorization request to every HTTP request.

You can disable it, so authorization will be made only if the resource server request it (with a `401` or `403` response code). 

```php
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OAuth2\HttpClient\OAuth2Plugin;

// create an OIDC/OAuth2 client and the AuthorizationService from facile-it/php-openid-client
/** @var AuthorizationService $authorizationService */
/** @var ClientInterface $client */

$oauth2Plugin = new OAuth2Plugin(
    $authorizationService,
    $client,
    null,
    [],
    false // disable authorization for each request
);
```
