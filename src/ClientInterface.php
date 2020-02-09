<?php

namespace Fullpipe\RpcClient;

use GuzzleHttp\Exception\GuzzleException;

interface ClientInterface
{
    /**
     * Call rpc method.
     *
     * @throws RpcError|GuzzleException
     */
    public function call(string $method, ?array $params = null);

    /**
     * Notify rpc method.
     *
     * @throws RpcError|GuzzleException
     */
    public function notify(string $method, ?array $params = null): void;

    /**
     * Retry next request only once with delay.
     */
    public function retryOnce(int $delay = 500): self;

    /**
     * Retry next request $times times.
     */
    public function retry(int $times = 10, array $config = []): self;
}
