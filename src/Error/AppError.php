<?php

namespace Fullpipe\RpcClient\Error;

class AppError extends RpcError
{
    public function __construct(string $message, int $code, $data = null)
    {
        parent::__construct($message, $code, $data);
    }
}
