<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

class HttpResponse implements IHttpResponse
{
    /**
     * HTTP response status
     *
     * @var string
     */
    protected $status_line;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var \Gekko\Http\HttpCookie[]
     */
    protected $cookies;

    /**
     * @var string
     */
    protected $body;

    public function __construct(string $status_line = "HTTP/1.1 200 OK")
    {
        $this->status_line = $status_line;
        $this->headers = [];
        $this->cookies = [];
        $this->body = "";
    }

    public function setBody(string $content) : void
    {
        $this->body = $content;
    }

    public function getBody() : string
    {
        return $this->body;
    }

    public function appendToBody(string $content) : void
    {
        $this->body .= $content;
    }

    public function setStatusLine(string $value) : void
    {
        $this->status_line = $value;
    }

    public function getStatusLine() : string
    {
        return $this->status_line;
    }

    public function getStatusCode() : int
    {
        $first_space = strpos($this->status_line, ' ', 0);
        
        if ($first_space === false)
            return -1;

        $second_space = strpos($this->status_line, ' ', $first_space + 1);

        if ($second_space === false)
            return intval(trim(substr($this->status_line, $first_space + 1)));

        return intval(trim(substr($this->status_line, $first_space + 1, strpos($this->status_line, ' ', $first_space + 1 ) - $first_space - 1)));
    }

    public function setHeaders(array $headers) : void
    {
        $this->headers = $headers;
    }

    public function setHeader(string $header, string $value) : void
    {
        $this->headers[$header] = $value;
    }

    public function getHeader(string $header) : ?string
    {
        return isset($this->headers[$header]) ? $this->headers[$header] : null;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function setCookie(HttpCookie $cookie) : void
    {
        $this->cookies[$cookie->name] = $cookie;
    }

    public function getCookie(string $cookie_name) : ?HttpCookie
    {
        return isset($this->cookies[$cookie_name]) ? $this->cookies[$cookie_name] : null;
    }

    public function setCookies(array $cookies) : void
    {
        $this->cookies = $cookies;
    }

    public function getCookies() : array
    {
        return $this->cookies;
    }

    public function __toString() : string
    {
        $raw = "{$this->status_line}\r\n";

        if (!empty($this->headers))
        {
            foreach ($this->headers as $header => $value)
            {
                $raw .= "{$header}: $value\r\n";
            }
        }

        if (!empty($this->cookies))
        {
            foreach ($this->cookies as $cookie) 
            {
                $raw .= "set-cookie: {$cookie}\r\n";
            }
        }

        $raw .= "\r\n{$this->body}";

        return $raw;
    }
}
