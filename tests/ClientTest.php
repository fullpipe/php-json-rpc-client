<?php

namespace Fullpipe\Tests\RpcClient;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Fullpipe\RpcClient\Client;
use Fullpipe\RpcClient\Error\AppError;
use Fullpipe\RpcClient\Error\InternalError;
use Fullpipe\RpcClient\Error\InvalidParams;
use Fullpipe\RpcClient\Error\InvalidRequest;
use Fullpipe\RpcClient\Error\MethodNotFound;
use Fullpipe\RpcClient\Error\ParseError;
use Fullpipe\RpcClient\Error\ServerError;
use GuzzleHttp\Exception\ServerException;

class ClientTest extends TestCase
{
    public function testValidCalls()
    {
        $handler = new MockHandler([
            new Response(200, [], \json_encode([
                'jsonrpc' => '2.0',
                'result' => 'foo',
                'id' => 1,
            ])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler]);
        $result = $rpc->call('method_name', ['foo' => 'bar']);
        $this->assertEquals('foo', $result);

        $request = $handler->getLastRequest();

        $this->assertEquals($request->getMethod(), 'POST');
        $this->assertEquals($request->getUri(), 'mock_url');
        $this->assertEquals([
            'jsonrpc' => '2.0',
            'method' => 'method_name',
            'id' => 1,
            'params' => ['foo' => 'bar'],
        ], \json_decode($request->getBody(), true));
    }

    public function testRetryCalls()
    {
        $handler = new MockHandler([
            new Response(504, []), // time out
            new Response(505, []),
            new Response(506, []),
            new Response(500, []),
            new Response(200, [], \json_encode([
                'jsonrpc' => '2.0',
                'result' => 'foo',
                'id' => 1,
            ])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'retries' => 10, 'delay' => 1]);

        $result = $rpc->call('method_name', ['foo' => 'bar']);

        $this->assertEquals('foo', $result);
    }

    public function testRetrysOnceAndFails()
    {
        $handler = new MockHandler([
            new Response(504, []), // time out
            new Response(504, []), // time out
            new Response(200, [], \json_encode(['jsonrpc' => '2.0', 'result' => 'foo', 'id' => 1])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'retries' => 10, 'delay' => 1]);

        $this->expectException(ServerException::class);
        $rpc->retryOnce()->call('method_name', ['foo' => 'bar']);
    }

    public function testRetriesOn32603()
    {
        $handler = new MockHandler([
            new Response(500, [], \json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['foo' => 'bar'],
                ],
                'id' => 1,
            ])),
            new Response(200, [], \json_encode(['jsonrpc' => '2.0', 'result' => 'foo', 'id' => 1])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'delay' => 1, 'retries' => 0]);

        $result = $rpc->retryOnce()->call('method_name', ['foo' => 'bar']);
        $this->assertEquals('foo', $result);
    }

    public function testDoesNotRetriesOnOtherThen32603()
    {
        $handler = new MockHandler([
            new Response(500, [], \json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error',
                    'data' => ['foo' => 'bar'],
                ],
                'id' => 1,
            ])),
            new Response(200, [], \json_encode(['jsonrpc' => '2.0', 'result' => 'foo', 'id' => 1])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'delay' => 1]);

        $this->expectException(ServerException::class);
        $rpc->retryOnce()->call('method_name', ['foo' => 'bar']);
    }

    public function testRetriesOnConnectionError()
    {
        $handler = new MockHandler([
            new ConnectException('Error Communicating with Server', new Request('POST', 'test')),
            new Response(200, [], \json_encode(['jsonrpc' => '2.0', 'result' => 'foo', 'id' => 1])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'delay' => 1]);

        $result = $rpc->retryOnce()->call('method_name', ['foo' => 'bar']);
        $this->assertEquals('foo', $result);
    }

    public function testDoesNotRetriesOn404()
    {
        $handler = new MockHandler([
            new BadResponseException('Not found', new Request('POST', 'test'), new Response(404)),
            new Response(200, [], \json_encode(['jsonrpc' => '2.0', 'result' => 'foo', 'id' => 1])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'delay' => 1, 'retries' => 10]);

        $this->expectException(BadResponseException::class);
        $rpc->call('method_name', ['foo' => 'bar']);
    }

    /**
     * @dataProvider getRpcErrorsData
     */
    public function testRpcErrors($error, $exceptionClass)
    {
        $handler = new MockHandler([
            new Response(200, [], \json_encode([
                'jsonrpc' => '2.0',
                'error' => $error,
                'id' => 1,
            ])),
        ]);

        $rpc = new Client('mock_url', ['handler' => $handler, 'delay' => 1, 'retries' => 0]);

        $this->expectException($exceptionClass);
        $rpc->call('method_name', ['foo' => 'bar']);
    }

    public function getRpcErrorsData()
    {
        return [
            [['code' => -32700], ParseError::class],
            [['code' => -32600], InvalidRequest::class],
            [['code' => -32601 ], MethodNotFound::class],
            [['code' => -32602 ], InvalidParams::class],
            [['code' => -32603 ], InternalError::class],
            [['code' => -32000 ], ServerError::class],
            [['code' => 404 ], AppError::class],
        ];
    }
}


try {
    $userData = $client->call('user.get', ['id' => 123]);
} catch (AppError $e) {
    if ($e->getCode() !== 404) {
        throw $e;
    }

    $userData = $this->createNewUser();
} catch (MethodNotFound | InvalidParams $e) {
    $this->sentry->catchException($e);
}
