<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;

class StatusCode
{
    private $response;
    private $expect;

    /**
     * StatusCode constructor.
     * @param Response $response
     * @param $expect
     */
    public function __construct(Response $response, $expect)
    {
        $this->response = $response;
        $this->expect = $expect;
    }

    /**
     * @return bool
     */
    public function assert()
    {
        return ($this->response->getStatusCode() === $this->expect);
    }
}