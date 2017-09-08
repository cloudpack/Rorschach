<?php

namespace Rorschach;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class Request
{
    /* @var GuzzleClient $guzzle */
    private $guzzle;
    private $request;

    /**
     * Request constructor.
     * @param $setting
     * @param $request
     */
    public function __construct($setting, $request)
    {
        $params = [
            'base_uri' => $setting['base'],
            'allow_redirects' => true,
            'http_errors' => false,
        ];
        $params = array_merge($params, $setting['option'] ?: []);

        $this->guzzle = new GuzzleClient($params);
        $this->request = $request;
    }

    /**
     * do request
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function request()
    {
        return $this->guzzle->request(
            $this->request['method'],
            $this->request["url"],
            $this->request['option'] ?: []
        );
    }

    /**
     * return binding param set.
     * @param GuzzleResponse $response
     * @param $binds
     * @param $function
     * @return array
     * @throws \Exception
     */
    public static function getBindParams(GuzzleResponse $response, $binds, $function)
    {
        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Pre-request failed.');
        }

        if ($function) {
            $body = $function($response);
        } else {
            $body = json_decode((string)$response->getBody(), true);
        }

        $params = [];
        foreach ($binds as $from => $to) {
            $params[$from] = Parser::search($to, $body);
        }

        return $params;
    }
}