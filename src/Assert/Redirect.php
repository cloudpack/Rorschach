<?php

namespace Rorschach\Assert;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RedirectMiddleware;

class Redirect implements AssertInterface
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
        $history = $this->response->getHeaderLine(RedirectMiddleware::HISTORY_HEADER);
        $histories = explode(', ', $history);

        foreach ($histories as $redirect) {
            if ($redirect === $this->expect) {
                return true;
            }
        }

        return false;
    }
}