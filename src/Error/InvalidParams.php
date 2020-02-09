<?php

namespace Fullpipe\RpcClient\Error;

class InvalidParams extends RpcError
{
    public function __construct(string $message, $data = null)
    {
        parent::__construct($message, -32602, $data);
    }
}
