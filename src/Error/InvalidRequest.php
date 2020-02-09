<?php

namespace Fullpipe\RpcClient\Error;

class InvalidRequest extends RpcError
{
    public function __construct(string $message, $data = null)
    {
        parent::__construct($message, -32600, $data);
    }
}
