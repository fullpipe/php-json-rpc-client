<?php

namespace Fullpipe\RpcClient\Error;

class MethodNotFound extends RpcError
{
    public function __construct(string $message, $data = null)
    {
        parent::__construct($message, -32601, $data);
    }
}
