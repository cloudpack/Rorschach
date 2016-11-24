<?php

namespace Rorschach;

use Symfony\Component\Yaml\Yaml;

class Parser
{
    /**
     * compile {{ }} brackets to (( )) brackets.
     *
     * @param $raw
     * @return mixed
     */
    public static function precompile($raw)
    {
        $regex = '/\{\{\s?([\w_\-]+)\s?\}\}/';

        return preg_replace($regex, '(( ${1} ))', $raw);
    }

    /**
     * compile (( )) brackets.bind vars.
     *
     * @param $raw
     * @param $binds
     * @return mixed
     */
    public static function compile($raw, $binds)
    {
        foreach ($binds as $key => $val) {
            $regex = '/\(\(\s?' . $key . '\s?\)\)/';
            $raw = preg_replace($regex, $val, $raw);
        }

        return $raw;
    }

    /**
     * search (( )) brackets vars.
     *
     * @param $raw
     * @return array
     */
    public static function searchVars($raw)
    {
        preg_match_all('/\(\(\s?([^\s]+)\s?\)\)/', $raw, $matches);
        return array_unique($matches[1]);
    }

    /**
     * parse yaml.
     *
     * @param $raw
     * @return mixed
     */
    public static function parse($raw)
    {
        return Yaml::parse($raw);
    }

    /**
     * search column vars in object.
     *
     * @param $pattern
     * @param $object
     * @return array|mixed
     * @throws \Exception
     */
    public static function search($pattern, $object)
    {
        if (is_null($object)) {
            throw new \Exception('No pattern found:: ' . $pattern);
        }

        $searches = explode('.', $pattern);
        foreach ($searches as $col) {
            // .. の場合は、配列
            if ($col === '') {
                if (is_array($object)) {
                    $object = array_shift($object);
                } else {
                    throw new \Exception('No pattern found:: ' . $pattern);
                }
            } else if (array_key_exists($col, $object)) {
                $object = $object[$col];
            } else {
                throw new \Exception('No pattern found:: ' . $pattern);
            }
        }

        return $object;
    }
}