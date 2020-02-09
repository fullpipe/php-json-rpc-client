<?php

namespace Fullpipe\RpcClient\Error;

class ServerError extends RpcError
{
    public function __construct(string $message, int $code, $data = null)
    {
        parent::__construct($message, $code, $data);
    }
}
