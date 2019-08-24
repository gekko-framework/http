<?php

namespace Gekko\Http;

use DateTime;

class HttpCookie
{
    /**
     * Cookie name
     *
     * @var string
     */
    public $name;

    /**
     * Cookie value
     *
     * @var string
     */
    public $value;

    /**
     * Cookie expiration time (unix timestamp)
     *
     * @var int
     */
    public $expires;

    /**
     * The path on the server in which the cookie will be available on
     *
     * @var string
     */
    public $path;

    /**
     * The (sub)domain that the cookie is available to
     *
     * @var string
     */
    public $domain;

    /**
     * Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client
     *
     * @var bool
     */
    public $secure;

    /**
     * When TRUE the cookie will be made accessible only through the HTTP protocol
     *
     * @var bool
     */
    public $http_only;

    public function __construct(string $name, string $value = "" , int $expires = 0, string $path = "", string $domain = "", bool $secure = false, bool $http_only = false)
    {
        $this->name = $name;
        $this->value = $value;
        $this->expires = $expires;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->http_only = $http_only;
    }

    public function __toString()
    {
        $cookie = "{$this->name}={$this->value}";

        if ($this->expires !== 0)
        {
            $dt = new DateTime('now');
            $dt->setTimestamp($this->expires);
            $cookie .= "; Expires={$dt->format(DateTime::COOKIE)}";
        }

        if (strlen($this->path) > 0)
            $cookie .= "; Path={$this->path}";

        if (strlen($this->domain) > 0)
            $cookie .= "; Domain={$this->domain}";

        if ($this->secure)
            $cookie .= "; Secure";

        if ($this->http_only)
            $cookie .= "; HttpOnly";

        return $cookie;
    }
}