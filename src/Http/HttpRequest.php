<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

use Gekko\Env;

class HttpRequest implements IHttpRequest
{
    public const PROTO_VER_1_0 = 1;
    public const PROTO_VER_1_1 = 11;
    public const PROTO_VER_2_0 = 2;

    /**
     * Application URL including scheme, host, port and path
     *
     * @var string
     */
    private $hostname;

    /**
     * HTTP Protocol version
     *
     * @var int
     */
    private $protocol_version;

    /**
     * @var URI
     */
    private $url;               // Request URI
        
    /**
     * @var string
     */
    private $method;            // GET, POST, PUT OR DELETE

    /**
     * @var string
     */
    private $body;

    /**
     * @var array
     */
    private $headers;           // Request headers

    /**
     * @var array
     */
    private $cookies;           // Request headers

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var array
     */
    private $files;             // Files sent on the request

    /**
     * @var array
     */
    private $properties;        // Used by framework
    
    /*public function __construct()
    {
        $this->hostname = $this->resolveHostname();

        // Resolve request properties
        $this->headers = apache_request_headers();
        $this->cookies = $_COOKIE;
        $this->method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_SPECIAL_CHARS);
        $this->url = $this->createUri();
        $this->parameters['get']    = $this->isGet()    ? $this->parseGetParameters() : [];
        $this->parameters['post']   = $this->isPost()   ? $this->parsePostParameters() : [];
        $this->parameters['put']    = $this->isPut()    ? $this->parseInputParameters() : [];
        $this->parameters['delete'] = $this->isDelete() ? $this->parseInputParameters() : [];
        $this->files = $this->isPost()  ? $this->parseFiles() : [];
    }*/

    public function __construct(string $verb, URI $url, array $headers = [], int $protocol_version = self::PROTO_VER_1_1, string $body = "", array $cookies = [], array $files = [])
    {
        $this->method = $verb;
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->protocol_version = $protocol_version;
        
        $this->hostname = "{$url->getScheme()}://{$url->getHost()}";
        $port = $url->getPort();

        if (!empty($port) && $port !== "80" && $port !== "443")
            $this->hostname .= ":{$port}";
    }

    public function getBody() : string
    {
        return $this->body;
    }

    public function getProtocolVersion() : int
    {
        return $this->protocol_version;
    }

    public function addProperty(string $name, $val) : void
    {
        if (isset($this->properties[$name])) {
            throw new \Exception("Property {$name} already exists");
        }
        $this->properties[$name] = $val;
    }

    public function setProperty(string $name, $val) : void
    {
        $this->properties[$name] = $val;
    }

    public function getProperty(string $name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    public function isPost() : bool
    {
        return strtolower($this->method) == "post";
    }

    public function isGet() : bool
    {
        return strtolower($this->method) == "get";
    }

    public function isPut() : bool
    {
        return strtolower($this->method) == "put";
    }

    public function isDelete() : bool
    {
        return strtolower($this->method) == "delete";
    }

    public function isOptions() : bool
    {
        return strtolower($this->method) == "options";
    }

    public function getMethod() : string
    {
        return strtoupper($this->method);
    }

    public function getURI() : URI
    {
        return $this->url;
    }

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function hasHeader($name) : bool
    {
        return isset($this->headers[$name]);
    }

    public function getHeader($name) : ?string
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    public function getCookies() : array
    {
        return $this->cookies;
    }

    public function hasCookie($name) : bool
    {
        return isset($this->cookies[$name]);
    }

    public function getCookie($name) : string
    {
        return isset($this->cookies[$name]) ? $this->cookies[$name] : null;
    }

    public function hostname() : string
    {
        $hostname = $this->hostname;
        
        if ($hostname[strlen($hostname)-1] == '/')
            $hostname = \substr($hostname, 0, strlen($hostname) - 1);

        return $hostname;
    }

    public function toLocalPath(string $path) : string
    {
        return Env::toLocalPath($path);
    }

    public function getRootUri() : string
    {
        return "/" . Env::get("site.virtual_path");
    }

    public function toUri(string $path) : string
    {
        $uri = $this->hostname() . $this->getRootUri();

        if ($path == "") {
            return $uri;
        }

        if ($path[0] != '/' && $uri[strlen($uri)-1] != '/')
            $path = "/{$path}";
        else if ($path[0] == '/' && $uri[strlen($uri)-1] == '/')
            $path = \substr($path, 1, \strlen($path) - 1);

        return "{$uri}{$path}";
    }

    public function toRelativeUri(string $path) : string
    {
        $uri = $this->getRootUri();

        if ($path == "")
            return $uri;

        if ($path[0] != '/' && strlen($uri) > 0 && $uri[strlen($uri)-1] != '/')
            $path = "/{$path}";
        else if ($path[0] == '/' && strlen($uri) > 0 && $uri[strlen($uri)-1] == '/')
            $path = \substr($path, 1, \strlen($path) - 1);

        return \str_replace('//', '/', $uri . $path);
    }

    public function createHttpResponse() : IHttpResponse
    {
        $response = new HttpResponse();
        $response->setStatus($this->protocol_version, 200, "OK");
        return $response;
    }

    public function dump() : string {
        return json_encode(get_object_vars($this));
    }
}
