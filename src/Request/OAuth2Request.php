<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Request;

use function array_key_exists;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class OAuth2Request implements OAuth2RequestInterface
{
    /** @var RequestInterface */
    private $request;

    /**
     * @var array<string, mixed>
     */
    private $grantParams = [];

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function getGrantParams(): array
    {
        return $this->grantParams;
    }

    /**
     * @inheritDoc
     */
    public function withGrantParams(array $grantParams): OAuth2RequestInterface
    {
        $new = clone $this;
        $new->grantParams = $grantParams;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withGrantParam(string $name, $value): OAuth2RequestInterface
    {
        $new = clone $this;
        $new->grantParams[$name] = $value;

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withoutGrantParam(string $name): OAuth2RequestInterface
    {
        $new = clone $this;
        if (array_key_exists($name, $new->grantParams)) {
            unset($new->grantParams[$name]);
        }

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {
        return $this->request->getProtocolVersion();
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;
        $new->request = $this->request->withProtocolVersion($version);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->request->getHeaders();
    }

    /**
     * @inheritDoc
     */
    public function hasHeader($name): bool
    {
        return $this->request->hasHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name): array
    {
        return $this->request->getHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine($name): string
    {
        return $this->request->getHeaderLine($name);
    }

    /**
     * @inheritDoc
     */
    public function withHeader($name, $value)
    {
        $new = clone $this;
        $new->request = $this->request->withHeader($name, $value);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader($name, $value)
    {
        $new = clone $this;
        $new->request = $this->request->withAddedHeader($name, $value);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader($name)
    {
        $new = clone $this;
        $new->request = $this->request->withoutHeader($name);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getBody()
    {
        return $this->request->getBody();
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body)
    {
        $new = clone $this;
        $new->request = $this->request->withBody($body);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {
        return $this->request->getRequestTarget();
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget($requestTarget)
    {
        $new = clone $this;
        $new->request = $this->request->withRequestTarget($requestTarget);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->request->getMethod();
    }

    /**
     * @inheritDoc
     */
    public function withMethod($method)
    {
        $new = clone $this;
        $new->request = $this->request->withMethod($method);

        return $new;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {
        return $this->request->getUri();
    }

    /**
     * @inheritdoc
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $new = clone $this;
        $new->request = $this->request->withUri($uri, $preserveHost);

        return $new;
    }
}
