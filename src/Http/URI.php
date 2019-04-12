<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

class URI
{
    /**
     * @var array
     */
    protected $components;

    /**
     * @var string
     */
    protected $url;
    
    public function __construct(string $url)
    {
        $this->url = $url;
        $this->components = parse_url($url)? : [];
    }

    public function getScheme() : ?string
    {
        return isset($this->components['scheme']) ? $this->components['scheme'] : null;
    }

    public function getHost() : ?string
    {
        return isset($this->components['host']) ? $this->components['host'] : null;
    }

    public function getPort() : ?string
    {
        return isset($this->components['port']) ? $this->components['port'] : null;
    }

    public function getUser() : ?string
    {
        return isset($this->components['user']) ? $this->components['user'] : null;
    }

    public function getPass() : ?string
    {
        return isset($this->components['pass']) ? $this->components['pass'] : null;
    }

    public function getPath() : ?string
    {
        return isset($this->components['path']) ? $this->components['path'] : null;
    }

    public function getLocalPath() : ?string
    {
        if (!isset($this->components['path']))
            return null;

        return str_replace("/", DIRECTORY_SEPARATOR, $this->components['path']);
    }

    public function getQuery() : ?string
    {
        return isset($this->components['query']) ? $this->components['query'] : null;
    }

    public function getFragment() : ?string
    {
        return isset($this->components['fragment']) ? $this->components['fragment'] : null;
    }

    public function getComponent($c) : ?string
    {
        return isset($this->components[$c]) ? $this->components[$c] : null;
    }

    public function format($format) : string
    {
        $components = [ 'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment' ];
        array_map(
            function ($comp) use (&$format) {
                $componentVal = $this->getComponent($comp);
                if ($componentVal != null) {
                    switch ($comp) {
                        case "query":
                            $componentVal = "?" . $componentVal;
                            break;
                        case "fragment":
                            $componentVal = "#" . $componentVal;
                            break;
                    }
                }
                $format = str_replace("{" . $comp . "}", $componentVal, $format);
            },
            $components
        );
        return $format;
    }

    public function __toString() : string
    {
        return $this->url;
    }

    public static function segments(string $fragment) : array
    {
        return array_values(array_filter(explode("/", $fragment), '\strlen'));
    }
}
