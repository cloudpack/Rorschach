<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;

class StatusCode implements AssertInterface
{
    private $response;
    private $expect;

    public function __construct(Response $response, $expect)
    {
        $this->response = $response;
        $this->expect = $expect;
    }

    public function assert()
    {
        return ($this->response->getStatusCode() === $this->expect);
    }
}