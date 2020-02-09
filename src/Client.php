<?php

namespace Fullpipe\RpcClient;

use Fullpipe\RpcClient\Error\InternalError;
use Fullpipe\RpcClient\Error\InvalidParams;
use Fullpipe\RpcClient\Error\AppError;
use Fullpipe\RpcClient\Error\InvalidRequest;
use Fullpipe\RpcClient\Error\MethodNotFound;
use Fullpipe\RpcClient\Error\ParseError;
use Fullpipe\RpcClient\Error\ServerError;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface as HttpResponseInterface;

class Client implements ClientInterface
{
    /**
     * @var string
     */
    private $apiEndpoint;

    /**
     * @var array ['retries' => 0, 'delay' => 500, 'handler' => new CurlHandler()]
     */
    private $config;

    /**
     * @var array
     */
    private $nextRequestConfig;

    /**
     * Guzzle client.
     *
     * @var GuzzleClientInterface
     */
    private $client;

    /**
     * @param array $config [
     *                      'retries' => 0,
     *                      'delay' => 500,
     *                      'handler' => new CurlHandler(),
     *                      'http' => ['timeout' => 1]
     *                      ]
     */
    public function __construct(string $apiEndpoint, array $config = [])
    {
        $this->apiEndpoint = $apiEndpoint;
        $this->config = \array_merge([
            'retries' => 0,
            'delay' => 500,
            'http' => ['timeout' => 1],
        ], $config);
        $this->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function retryOnce(int $delay = null): ClientInterface
    {
        return $this->retry(1, ['delay' => $delay ?? $this->config['delay']]);
    }

    /**
     * {@inheritdoc}
     */
    public function retry(int $retries = 2, array $config = []): ClientInterface
    {
        $this->nextRequestConfig['retries'] = $retries;

        $this->nextRequestConfig = \array_merge($this->nextRequestConfig, $config);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function call(string $method, ?array $params = null)
    {
        $response = $this->getClient()->request(
            'POST',
            $this->apiEndpoint,
            [
                RequestOptions::JSON => [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'id' => 1,
                    'params' => $params,
                ],
            ]
        );

        $this->reset();

        return $this->processResponse($response);
    }

    public function notify(string $method, ?array $params = null): void
    {
        $this->getClient()->request(
            'POST',
            $this->apiEndpoint,
            [
                RequestOptions::JSON => [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                ],
            ]
        );

        $this->reset();
    }

    private function processResponse(HttpResponseInterface $response)
    {
        $data = \json_decode($response->getBody(), true);

        if (200 == $response->getStatusCode() && isset($data['result'])) {
            return $data['result'];
        }

        if (isset($data['error'])) {
            $this->throwAppError($data['error']);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(json_last_error_msg());
        }

        throw new \RuntimeException($response->getBody(), $response->getStatusCode());
    }

    private function throwAppError($error)
    {
        $code = (int) $error['code'] ?? -32099;
        $message = $error['message'] ?? null;
        $data = $error['data'] ?? null;

        switch ($code) {
            case -32700:
                $exception = new ParseError(
                    $message ?? 'Invalid JSON was received by the server. An error occurred on the server while parsing the JSON text.',
                    $data
                );
                break;
            case -32600:
                $exception = new InvalidRequest(
                    $message ?? 'The JSON sent is not a valid Request object.',
                    $data
                );
                break;
            case -32601:
                $exception = new MethodNotFound(
                    $message ?? 'The method does not exist / is not available.',
                    $data
                );
                break;
            case -32602:
                $exception = new InvalidParams(
                    $message ?? 'Invalid method parameter(s).',
                    $data
                );
                break;
            case -32603:
                $exception = new InternalError(
                    $message ?? 'Internal JSON-RPC error.',
                    $data
                );
                break;
            default:
                if (-32099 <= $code && $code <= -32000) {
                    $exception = new ServerError(
                        $message ?? 'Server Error',
                        $code,
                        $data
                    );
                } else {
                    $exception = new AppError(
                        $message ?? 'Application error',
                        $code,
                        $data
                    );
                }
                break;
        }

        throw $exception;
    }

    private function getClient(): GuzzleClientInterface
    {
        if ($this->client) {
            return $this->client;
        }

        $handler = $this->config['handler'] ?? new CurlHandler($this->config['http'] ?? []);
        $stack = HandlerStack::create($handler);

        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        $this->client = new GuzzleHttpClient(['handler' => $stack]);

        return $this->client;
    }

    private function retryDecider()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
            if ($response) {
                if (0 < $response->getStatusCode() && $response->getStatusCode() < 500) {
                    return false;
                }

                $data = \json_decode($response->getBody(), true);

                if ($data && isset($data['error']['code']) && -32603 != $data['error']['code']) {
                    return false;
                }
            }

            if ($exception && !$exception instanceof ConnectException) {
                return false;
            }

            $this->nextRequestConfig['retries'] = ($this->nextRequestConfig['retries'] ?? 0) - 1;

            return $retries <= $this->nextRequestConfig['retries'];
        };
    }

    private function retryDelay()
    {
        return function ($retries) {
            return $this->nextRequestConfig['delay'] * $retries;
        };
    }

    private function reset()
    {
        $this->nextRequestConfig = $this->config;
    }
}
