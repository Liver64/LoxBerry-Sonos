<?php
/**
 * Sonos4Lox Request
 * Version: V04.1
 * Language: EN
 *
 * Purpose:
 * - Encapsulate $_GET access for newly refactored code.
 * - Preserve all existing query parameter names and values.
 */

class S4L_Request
{
    private $query;

    public function __construct($query)
    {
        $this->query = is_array($query) ? $query : array();
    }

    public static function fromGlobals()
    {
        return new self($_GET);
    }

    public function action()
    {
        return $this->get('action', '');
    }

    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->query) ? $this->query[$name] : $default;
    }

    public function has($name)
    {
        return array_key_exists($name, $this->query);
    }

    public function all()
    {
        return $this->query;
    }
}
