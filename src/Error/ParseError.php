<?php

namespace Fullpipe\RpcClient\Error;

class ParseError extends RpcError
{
    public function __construct(string $message, $data = null)
    {
        parent::__construct($message, -32700, $data);
    }
}
