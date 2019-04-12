<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

class HttpRequest implements IHttpRequest
{
    /**
     * Application Absolute Path in FileSystem
     *
     * @var     string
     * @example /var/www/gekko
     */
    private static $appDir;

    /**
     * Application Relative URI (include application local path)
     *
     * @var     string
     * @example http://someurl.com => /
     * @example http://someurl.com/gekko => /gekko
     */
    private static $appUri;

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
        // Resolve env variables
        self::$appDir = self::resolveAppDir();
        self::$appUri = self::resolveAppUri();
        self::$hostname = self::resolveHostname() . self::$appUri;

        // Resolve request properties
        $this->headers = apache_request_headers();
        $this->cookies = $_COOKIE;
        $this->method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_SPECIAL_CHARS);
        $this->url = $this->buildUrl();
        $this->parameters['get']    = $this->isGet()    ? $this->parseGetParameters() : [];
        $this->parameters['post']   = $this->isPost()   ? $this->parsePostParameters() : [];
        $this->parameters['put']    = $this->isPut()    ? $this->parseInputParameters() : [];
        $this->parameters['delete'] = $this->isDelete() ? $this->parseInputParameters() : [];
        $this->files = $this->isPost()  ? $this->parseFiles() : [];
    }

    private function buildUrl() : URI
    {        
        $query = isset($_SERVER['PATH_INFO']) && isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        return new URI(self::resolveHostname() . filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_SPECIAL_CHARS) . (empty($query) ? "" : "?{$query}"));
    }

    private static function resolveAppDir() : string
    {
        return dirname(dirname(__DIR__));
    }
    
    /**
     * Gekko relies on URL rewriting, because of that everything
     * befores string "index.php" is part of the application's root URI
     */
    private static function resolveAppUri() : string
    {
        $script = \filter_input(INPUT_SERVER, 'SCRIPT_NAME', FILTER_SANITIZE_URL);
        $pos = \strpos($script, "index.php");
        return substr($script, 0, $pos);
    }

    private static function resolveHostname() : string
    {
        $scheme = filter_input(INPUT_SERVER, 'REQUEST_SCHEME', FILTER_SANITIZE_URL);
        $port = filter_input(INPUT_SERVER, 'SERVER_PORT', FILTER_SANITIZE_URL);
        $servProtocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_URL);
        $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_URL);
        
        if (empty($scheme)) {
            $servProtocol = explode('/', $servProtocol);
            if (intval($port) == 443 || strtolower($servProtocol[0]) === "https") {
                $scheme = "https";
            } else {
                $scheme = "http";
            }
        }

        return "{$scheme}://{$host}";
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

    public function getRootDir($path = "") : string
    {
        if (strlen($path) > 0 && $path[0] != DIRECTORY_SEPARATOR) {
            $path = DIRECTORY_SEPARATOR . $path;
        }
        return urldecode(self::$appDir . "{$path}");
    }

    public function getRootUri(string $path = "") : string
    {
        if ($path == "") {
            return self::$appUri;
        }

        if ($path[0] != '/' && self::$appUri[0] != '/') {
            $path = "/{$path}";
        }

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        return \str_replace('//', '/', self::$appUri . $path);
    }
}
