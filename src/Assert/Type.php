<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;

class Type implements AssertInterface
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

        $cols = explode('.', $this->expect['col']);
        foreach ($cols as $col) {
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

        $expects = explode('|', $this->expect['expect']);

        // nullableかつnull
        if (in_array('nullable', $expects) && is_null($body)) {
            return true;
        }

        foreach ($expects as $type) {
            switch ($type) {
                case 'string':
                    break;
                case 'integer':
                    return $body == (int)$body;
                    break;
                case 'float':
                    return $body == (float)$body;
                    break;
                case 'array':
                    return array_values($body) === $body;
                    break;
                case 'object':
                    return array_values($body) !== $body;
                    break;
                default:
                    throw new \Exception('unknown type selected.');
            }
        }
    }
}