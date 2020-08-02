<?php

declare(strict_types=1);

namespace Facile\OAuth2\HttpClient\Request;

use Psr\Http\Message\RequestInterface;

interface OAuth2RequestInterface extends RequestInterface
{
    /**
     * Returns grant parameters
     *
     * @return array<string, mixed>
     */
    public function getGrantParams(): array;

    /**
     * Set grant parameters
     *
     * @param array<string, mixed> $grantParams
     *
     * @return self
     */
    public function withGrantParams(array $grantParams): self;

    /**
     * Set a grant parameter
     *
     * @param string $name
     * @param mixed $value
     *
     * @return self
     */
    public function withGrantParam(string $name, $value): self;

    /**
     * Remove a single grant parameter
     *
     * @param string $name
     *
     * @return self
     */
    public function withoutGrantParam(string $name): self;
}
