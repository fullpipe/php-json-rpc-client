<?php

namespace Fullpipe\RpcClient\Error;

class InternalError extends RpcError
{
    public function __construct(string $message, $data = null)
    {
        parent::__construct($message, -32603, $data);
    }
}
