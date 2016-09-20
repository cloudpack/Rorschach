<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;
use Rorschach\Parser;

class Value
{
    private $response;
    private $col;
    private $expect;

    /**
     * Value constructor.
     * @param Response $response
     * @param $col
     * @param $expect
     */
    public function __construct(Response $response, $col, $expect)
    {
        $this->response = $response;
        $this->col = $col;
        $this->expect = $expect;
    }

    /**
     * @return bool
     */
    public function assert()
    {
        $body = json_decode((string)$this->response->getBody(), true);

        $col = Parser::search($this->col, $body);

        return $this->expect == $col;
    }
}