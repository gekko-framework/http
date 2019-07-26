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
    /**
     * Application URL including scheme, host, port and path
     *
     * @var string
     */
    private static $hostname;

    /**
     * @var URI
     */
    private $url;               // Request URI
        
    /**
     * @var string
     */
    private $method;            // GET, POST, PUT OR DELETE

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
    
    public function __construct()
    {
        self::$hostname = $this->resolveHostname();

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
    }

    private function createUri() : URI
    {
        // We don't want virtual path here as the REQUEST_URI should contain it
        $hostname = $this->resolveHostname(false);
        $request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_SPECIAL_CHARS);
        $query = isset($_SERVER['PATH_INFO']) && isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

        return new URI($hostname . $request_uri . (empty($query) ? "" : "?{$query}"));
    }    

    private function resolveHostname(bool $with_virtual_path = true) : string
    {
        $scheme = filter_input(INPUT_SERVER, 'REQUEST_SCHEME', FILTER_SANITIZE_URL);
        $port = filter_input(INPUT_SERVER, 'SERVER_PORT', FILTER_SANITIZE_URL);
        $servProtocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_URL);
        $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_URL);
        $virtual_path = $with_virtual_path ? Env::get("site.virtual_path") : "";
        
        if ($with_virtual_path && strlen($virtual_path) > 0)
        {
            if ($virtual_path[0] != '/')
                $virtual_path = "/" . $virtual_path;
            
            if ($virtual_path[strlen($virtual_path)-1] == '/')
                $virtual_path = \substr($virtual_path, 0, strlen($virtual_path) - 1);
        }
            
        
        if (empty($scheme)) {
            $servProtocol = explode('/', $servProtocol);
            if (intval($port) == 443 || strtolower($servProtocol[0]) === "https") {
                $scheme = "https";
            } else {
                $scheme = "http";
            }
        }

        return "{$scheme}://{$host}{$virtual_path}";
    }

    /**
     * ====================================================================================
     *
     * @author Leo Brugnara
     * @desc   Sanitize POST parameters
     * @return array
     * ====================================================================================
     */
    protected function parsePostParameters() : array
    {
        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
        $tmp = $_POST;
        if (isset($this->headers['Content-Type']) && strstr(trim($this->headers['Content-Type']), "application/json") !== false) {
            $body = file_get_contents("php://input");
            $tmp = json_decode($body, true);
        }
        if (empty($tmp)) {
            return [];
        }
        foreach ($tmp as $key => $value) {
            $tmp[$key] = self::sanitize($key, $value);
        }
        return $tmp;
    }

    /**
     * ====================================================================================
     *
     * @author Leo Brugnara
     * @desc   Sanitize GET parameters
     * @return array
     * ====================================================================================
     */
    protected function parseGetParameters() : array
    {
        $_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        $tmp = $_GET;
        if (empty($tmp)) {
            return [];
        }
        foreach ($tmp as $key => $value) {
            $tmp[$key] = self::sanitize($key, $value);
        }
        return $tmp;
    }

    /**
     * ====================================================================================
     *
     * @author Leo Brugnara
     * @desc   Handle php://input and sanitize the content
     * @return array
     * ====================================================================================
     */
    protected function parseInputParameters() : array
    {
        $vars = file_get_contents("php://input");
        if (empty($vars)) {
            return [];
        }

        $arrayVars = [];
        if (isset($this->headers['Content-Type']) && trim($this->headers['Content-Type']) === "application/json") {
            $arrayVars = json_decode($vars, true) or [];
        } else {
            parse_str($vars, $arrayVars);
        }

        if (empty($arrayVars)) {
            return [];
        }

        foreach ($arrayVars as $key => $value) {
            $arrayVars[$key] = self::sanitize($key, $value);
        }
        return $arrayVars;
    }

    protected function parseFiles() : array
    {
        if (empty($_FILES)) {
            return [];
        }
        $files = $_FILES;
        $newFiles = [];
        // a mapping of $_FILES indices for validity checking
        foreach ($files as $inputName => $data) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $i => $v) {
                        $newFiles[$inputName][$i][$key] = $v;
                    }
                } else {
                    $newFiles[$inputName][$key] = $value;
                }
            }
        }

        return $newFiles;
    }

    // TODO: Sanitize input values
    public static function sanitize($name, $value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value[$k] = static::sanitize($k, $v);
                } else {
                    $value[$k] = $v;
                }
            }
        } else {
            $value = $value;
        }

        return $value;
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

    public function hasParameter($name) : bool
    {
        return $this->hasMethodParameter($this->method, $name);
    }

    public function getParameters() : array
    {
        return $this->parameters;
    }

    public function getParameter($name)
    {
        return $this->getMethodParameter($this->method, $name);
    }

    public function hasMethodParameter($method, $name) : bool
    {
        $method = strtolower($method);
        return isset($this->parameters[$method]) && isset($this->parameters[$method][$name]);
    }

    public function getMethodParameter($method, $name)
    {
        $method = strtolower($method);
        return isset($this->parameters[$method]) && isset($this->parameters[$method][$name]) ? $this->parameters[$method][$name] : null;
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

    public function hostname($uri = "") : string
    {
        if ($uri == "") {
            return self::$hostname;
        }
        return self::$hostname . preg_replace('/(\/\/){1}/', '/', "/{$uri}");
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
        $uri = $this->getRootUri();

        if ($path == "") {
            return $uri;
        }

        if ($path[0] != '/' && strlen($uri) > 0 && $uri[0] != '/') {
            $path = "/{$path}";
        }

        if ($path[strlen($path)-1] != '/') {
            //$path .= '/';
        }

        return \str_replace('//', '/', $uri . $path);
    }

    public function dump() : string {
        return json_encode(get_object_vars($this));
    }
}
