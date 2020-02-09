<?php

namespace Fullpipe\RpcClient\Error;

use RuntimeException;

class RpcError extends RuntimeException
{
    /**
     * @var mixed
     */
    private $data;

    public function __construct(string $message, int $code, $data = null)
    {
        $this->data = $data;

        parent::__construct($message, $code);
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
}
