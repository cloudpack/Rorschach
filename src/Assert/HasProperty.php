<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;

class HasProperty implements AssertInterface
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
        $body = json_decode((string)$this->response->getBody(), true);

        $expects = explode('.', $this->expect);
        foreach ($expects as $col) {
            // .. の場合は、配列
            if ($col === '') {
                if (is_array($body)) {
                    $body = array_shift($body);
                } else {
                    return false;
                }
            } else if (array_key_exists($col, $body)) {
                $body = $body[$col];
            } else {
                return false;
            }
        }

        return true;
    }
}