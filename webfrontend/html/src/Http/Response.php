<?php
/**
 * Sonos4Lox Response
 * Version: V04.1
 * Language: EN
 *
 * Purpose:
 * - Reserve a response abstraction for later action groups.
 * - V03 playback actions mainly keep legacy echo/logging behaviour.
 */

class S4L_Response
{
    private $body;
    private $statusCode;

    public function __construct($body = '', $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

    public function send()
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
        }

        if ($this->body !== '') {
            echo $this->body;
        }
    }
}
