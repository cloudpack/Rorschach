<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;

interface AssertInterface
{
    public function __construct(Response $response, $expect);
    public function assert();
}