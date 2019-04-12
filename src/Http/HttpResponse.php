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
     * @var array
     */
    protected $headerLines;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var array
     */
    protected $cookies;

    /**
     * @var string
     */
    protected $body;

    public function __construct()
    {
        $this->headerLines = [];
        $this->headers = [];
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

    public function setHeader(string $header, string $value) : void
    {
        $this->headers[$header] = $value;
    }

    public function setHeaderLine(string $value) : void
    {
        $this->headerLines[] = $value;
    }

    public function setHeaders(array $headers) : void
    {
        $this->headers = $headers;
    }

    public function getHeader(string $header) : ?string
    {
        return isset($this->headers[$header]) ? $this->headers[$header] : null;
    }

    public function getHeaders($headers) : array
    {
        return $this->headers;
    }

    public function setCookie(string $name, string $value, int $exp = null, string $path = null) : void
    {
        setcookie($name, $value, $exp, $path);
    }

    public function __toString() : string
    {
        if (!empty($this->headerLines)) {
            foreach ($this->headerLines as $headerLine) {
                header("{$headerLine}");
            }
        }
        if (!empty($this->headers)) {
            foreach ($this->headers as $header => $value) {
                header("{$header}: $value");
            }
        }
        return "{$this->body}";
    }
}
