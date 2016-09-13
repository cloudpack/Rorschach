<?php

namespace Rorschach\Resource;

use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Yaml\Yaml;
use Rorschach\Assert;

class Entity
{
    private $raw;
    private $binds;
    private $config;
    /* @var GuzzleClient $guzzle */
    private $guzzle;

    public function __construct($raw, $binds)
    {
        $this->raw = $raw;
        $this->binds = $binds;
    }

    public function initialize()
    {
        if ($this->isValid()) {
            $params = [
                'base_uri' => $this->config['base'],
                'headers' => $this->config['headers'] ?: [],
            ];
            if (array_key_exists('allow_redirects', $this->config)) {
                $params['allow_redirects'] = $this->config['allow_redirects'];
            }
            $this->guzzle = new GuzzleClient($params);
        } else {
            throw new \Exception('Invalid config params.', 500);
        }

        return $this;
    }

    private function isValid()
    {
        foreach (["base", "resources"] as $required) {
            if (!array_key_exists($required, $this->config)) {
                return false;
            }
        }

        return true;
    }

    public function preRequest()
    {
        if (!$this->config['pre-requests']) {
            return $this;
        }
        $preRequests = $this->config['pre-requests'];
        foreach ($preRequests as $preRequest) {
            $requestBody = $preRequest["body"] ?: null;
            $requestHeaders = $preRequest["headers"] ?: [];
            $headers = array_merge(
                $requestHeaders,
                $this->config['headers']
            );

            $response = $this->guzzle->request(
                $preRequest["method"],
                $preRequest["resource"],
                [
                    'headers' => $headers,
                    "body" => $requestBody,
                ]
            );

            if ($response->getStatusCode() < 400) {
                $responseBody = $response->getBody();
                foreach ($preRequest["bind"] as $from => $to) {
                    $elements = array_filter(explode('.', $to));
                    $val = $responseBody;
                    foreach ($elements as $element) {
                        $val = $val[$element];
                    }
                    $this->binds[$from] = $val;
                }
            }
        }
        return $this;
    }

    public function getResources()
    {
        return $this->config['resources'];
    }

    public function request(array $resource)
    {
        $headers = array_merge(
            $resource['headers'] ?: [],
            $this->config['headers']
        );
        $body = $resource['body'] ?: null;
        $response = $this->guzzle->request(
            $resource['method'],
            $resource['url'],
            [
                'headers' => $headers,
                'body' => $body,
            ]
        );

        return $response;
    }

    public function assert($response, $type, $value)
    {
        switch ($type) {
            case 'code':
                return (new Assert\StatusCode($response, $value))->assert();
            case 'has':
                return (new Assert\HasProperty($response, $value))->assert();
            case 'type':
                return (new Assert\Type($response, $value))->assert();
            case 'redirect':
                return (new Assert\Redirect($response, $value))->assert();
        }
    }

    /**
     * To bind parameters to (( )) bracket.
     *
     * @return Entity $Entity
     */
    public function compile()
    {
        $params = [];
        if (count($this->binds) > 0) {
            foreach ($this->binds as $bind) {
                $bind = json_decode($bind, true);
                $params = array_merge($params, $bind);
            }
        }
        foreach ($params as $key => $val) {
            $regex = '/\(\(\s' . $key . '\s\)\)/';
            $this->raw = preg_replace($regex, $val, $this->raw);
        }

        $this->config = Yaml::parse($this->raw);

        return $this;
    }
}