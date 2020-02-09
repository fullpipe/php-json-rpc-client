# JSON-RPC 2.0 PHP client

## Install

```
composer require fullpipe/php-json-rpc-client
```

## Usage

```php
use Fullpipe\RpcClient\Client;
use Fullpipe\RpcClient\Error\AppError;
use Fullpipe\RpcClient\Error\MethodNotFound;
use Fullpipe\RpcClient\Error\InvalidParams;
...

$client = new Client('https://api.server/rpc', [
    'retries' => 0, 
    'delay' => 500,
    'http' => ['timeout' => 1],
]);

// Simple call
$userData = $client->call('user.get', ['id' => 123]);

// Simple call with single retry
$userData = $client->retryOnce()->call('user.get', ['id' => 123]);

// Call and catch application error
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
```

## Configuration

### Default 

By default retries disabled. And CurlHandler used as handler for guzzle.

```php
[
    'retries' => 0, 
    'delay' => 500,
    'http' => ['timeout' => 1], // options for CurlHandler
]
```

### Custom handler 
 
You could use you own handler. For tests for example.

```php
use GuzzleHttp\Handler\MockHandler;
...

$handler = new MockHandler([
    new Response(200, [], \json_encode([
        'jsonrpc' => '2.0',
        'result' => 'foo',
        'id' => 1,
    ])),
]);

$client = new Client('https://api.server/rpc', [
    'handler' => $handler
]);
```
