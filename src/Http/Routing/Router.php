<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http\Routing;

use \Gekko\Http\URI;
use \Gekko\Helpers\Utils;
use \Gekko\Http\IHttpRequest;
use \Gekko\Http\HttpHandlerType;
use \Gekko\DependencyInjection\IDependencyInjector;

class Router
{
    /**
     * @var string
     */
    private const PARAM_REGEX = '#\{([a-zA-Z\-_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?:(.+)?\}$#';

    /**
     * @var \Gekko\Http\Routing\RoutingMap
     */
    private $routingMap;

    /**
     * @var \Gekko\DependencyInjection\IDependencyInjector
     */
    private $injector;

    public function __construct(IDependencyInjector $injector)
    {
        $this->routingMap = new RoutingMap();
        $this->injector = $injector;
    }

    public function __invoke()
    {
        // As a middleware, it receives $request, $response and $next middleware
        list($req, $resp, $next) = array_pad(func_get_args(), 3, null);

        if ($req == null || $resp == null) {
            throw new Exception("Missing parameters for middleware " . __CLASS__);
        }

        $this->enroute($req);
        return $next($req, $resp);
    }

    public function isException(IHttpRequest $httprequest) : bool
    {
        $requestUrl = $this->getRequestPath($httprequest);

        $requestedFile = $httprequest->getRootDir($httprequest->getURI()->getLocalPath());

        $exceptions = $this->routingMap->getExceptions();
        foreach ($exceptions as $exception) {
            if (preg_match("#/?" . $exception . "#", $requestUrl) && file_exists($requestedFile)) {
                return true;
            }
        }
        return false;
    }

    protected function getRequestPath(IHttpRequest $httprequest) : string
    {
        $fullpath = $httprequest->getURI()->getPath();

        if ($fullpath[\strlen($fullpath) - 1] != "/")
            $fullpath .= "/";

        return str_replace($httprequest->getRootUri(), "/", $fullpath);
    }

    public function enroute(IHttpRequest $httprequest) : bool
    {
        if ($this->isException($httprequest)) {
            return true;
        }

        return $this->findRoute($this->routingMap, $httprequest);
    }

    protected function findRoute(RoutingMap $routingMap, IHttpRequest $httprequest) : bool
    {
        $verb = $httprequest->getMethod();

        // Test routes
        foreach ($routingMap->getRoutes() as $routeOptions) {
            $routeUrl = $routeOptions['url'];

            if (!in_array($verb, $routeOptions['methods'])) {
                // If the current verb is not supported by the route, continue
                continue;
            }

            // Handler is the Closure or IHttpController that will be called to
            // process the HTTP request
            $handler = $routeOptions['handler'];
            $handlerType = $routeOptions['htype'];

            if ($handlerType == HttpHandlerType::Unknown) {
                continue;
            }

            // Split the URL of the route to start building the regex
            $urlRegexes = URI::segments($routeUrl);
            // Save the route's parameters
            $params = [];

            foreach ($urlRegexes as $i => $urlRegex) {
                // Path regex will contain a regex for this specific part of the path
                $pathRegex = null;

                // Change capturing groups for non-capturing groups
                $pathRegex = $this->sanitizeRegex($urlRegex);

                // If route part is a parameter, process it and add it to the params array
                if (preg_match(self::PARAM_REGEX, $urlRegex)) {
                    // parseParams returns an array with keys [ name => <var name> , regex => <accepted values> ]
                    $param = $this->parseParam($urlRegex);
                    // Add the param to the params array. IMPORTANT: We are using the $i value to keep track of
                    // the part of the URL this param is occupying
                    $params[$i] = $param;
                    // This part of the URL regex will contain the param's regex
                    $pathRegex = $param['regex'];
                }

                // Update the path in the URL regex with the current path
                $urlRegexes[$i] = "\/?({$pathRegex})";
            }

            // Join the regexes (do not add slashes, they are added in previous loop)
            $routeRegex = implode('', $urlRegexes);

            // 1) Add an optional trailing slash.
            // 2) HttpHandlerType::HttpClass matches the initial part of the regex, but the specific method to be
            // called is determined in another findRoute trip, so we are not looking for a complete match ($ symbol) right
            // now. Once we call findRoute with the matched controller, the handlerType will change to Method or Closure
            // so it will try to match the entire request then.
            $routeRegex = "#^{$routeRegex}(\/)?" . ($handlerType == HttpHandlerType::HttpClass ? "" : "$") . "#";

            // Get Request URL to be tested again $routeRegex
            $requestUrl = $this->getRequestPath($httprequest);

            // Add some adjustments:
            $lpos = strlen($requestUrl)-1;
            
            // 1) Remove the trailing slash
            if ($lpos > 0 && $requestUrl[$lpos] == '/') {
                $requestUrl = substr($requestUrl, 0, $lpos);
            }

            // 2) Add a leading slash
            if ($lpos > 0 && $requestUrl[0] != '/') {
                $requestUrl = '/' . $requestUrl;
            }

            // Find coincidences and filter results
            Utils::echopre("URL Regex", $routeRegex, $requestUrl, "_____________");
            $urlMatches = $this->tryMatchRoute($routeRegex, $requestUrl);
            Utils::echopre("URL Matches", $urlMatches, "_____________");

            if (empty($urlMatches)) {
                continue;
            }

            // Matched route! Look for routed parameters
            $routeParams = new RouteParams;
            $j = 0;
            // Becuase we saved $params with the key as the position they occupy in the URL
            // we can retrieve the matched value using that index.
            // If URL is /something/{param:}/test our $i value for the parameter will be 1
            foreach ($params as $i => $param) {
                if (!isset($urlMatches[$i])) {
                    continue;
                }
                $pval = $urlMatches[$i];

                /*
                 * This two lines (if uncommented) allow request parameters (GET, POST, etc) to override 
                 * route parameters (paths with format {param:regex} in the URL) when they do not exist. 
                 * Notice that we are not ignoring empty matches, so it represents the 'lack' of the route parameter. 
                 * This way, we can separate route parameters from request parameters, making 
                 * it more clear to distinguish concerns in our app. Let say you have a search
                 * page, probably you only want the query string to contain the terms to search
                 * so you wouldn't define a route like /search/{term:} or maybe you've some 
                 * articles service with a URL like /articles/{slug:} and you do not want 
                 * users to access it like /articles?slug=some-ex-friendly-url. 
                 *
                 *  if (empty($pval))
                 *      continue;
                 */

                // Save the parameter with its name if it has one, or use a 0-based index
                $routeParams[($param['name']  != null ? $param['name'] : $j++)] = $pval;
            }
            Utils::echopre("URL Params", $routeParams, "_____________");

            // If handler types are Closure or Method, we finished our search, but if the type is
            // HttpController, this is the first step, now we need to use the RoutingMap object
            // returned by IHttpController->routes() method that contains the controller's registerd
            // routes
            if ($handlerType == HttpHandlerType::Closure || $handlerType == HttpHandlerType::Method) {
                $this->injector->getContainer()->add(Route::class, ['reference' => new Route($handler, $routeParams, $routingMap)]);
                return true;
            } elseif ($handlerType == HttpHandlerType::HttpClass) {
                foreach ($handler as $handlerClass) {
                    // Create a Routing Map for the specific IHttpController
                    $croutesmap = new RoutingMap($routeUrl, $handlerClass);
                    $handler = $this->injector->make($handlerClass);
                    $handler->routes($croutesmap);
                    if ($this->findRoute($croutesmap, $httprequest)) {
                        return true;
                    }
                }
            } else {
                return false;
            }
        }
        return false;
    }

    protected function tryMatchRoute($regex, $requestURLPath) : array
    {
        $urlMatches = [];
        preg_match_all($regex, $requestURLPath, $urlMatches);

        // We should have at least 1 capturing group
        if (!isset($urlMatches[1]) || empty($urlMatches[1][0])) {
            return [];
        }

        // Remove unnecessary offset 0
        unset($urlMatches[0]);
        $urlMatches = array_values($urlMatches);
        
        // Return the single value of the match, instead of an array containing it.
        $urlMatches = array_map(
            function ($exception) use ($urlMatches) {
                return $exception[0];
            },
            $urlMatches
        );

        // Clean empty groups and
        return $urlMatches;
    }

    protected function parseParam(string $paramPath) : array
    {
        // Remove {}
        $varNameAndRegex = substr($paramPath, 1, strlen($paramPath)-2);
        // Get position of :
        $pos = strpos($varNameAndRegex, ":");
        // Get param name (if it has one)
        $varName = substr($varNameAndRegex, 0, $pos);
        // Get and sanitize param regex
        $paramRegex = $this->sanitizeRegex(substr($varNameAndRegex, $pos+1));
        return [
            'name' => strlen($varName) == 0 ? null : $varName,
            // Default regex for parameters [^/#?]+?
            'regex' => strlen($paramRegex) == 0 ? "[^\/\#\?]+" : $paramRegex
        ];
    }

    /**
     * Replace capturing groups by non-capturing groups
     */
    protected function sanitizeRegex(string $regex) : string
    {
        $o = [];
        $e = str_split($regex);
        $inCharClass = false;
        while (($c = array_shift($e)) != null) {
            $o[] = $c;
            if ($c == "[" && count($o) > 0 && $o[count($o)-1] != '\\') {
                $inCharClass = true;
            } elseif ($c == "]" && (count($o) == 0 || (count($o) > 0 && $o[count($o)-1] != '\\')) && $inCharClass) {
                $inCharClass = false;
            } elseif ($c == "(" && (count($o) == 0 || (count($o) > 0 && $o[count($o)-1] != '\\')) && !$inCharClass && $e[0] != '?') {
                $o[] = "?:";
            }
        }
        return implode("", $o);
    }

    public function exception(string $path)
    {
        $this->routingMap->exception($path);
    }

    public function exceptions(array $paths)
    {
        $this->routingMap->exceptions($paths);
    }

    public function register(RoutingMap $map)
    {
        $this->routingMap->register($map);
    }

    public function get(string $routeUrl, $handler)
    {
        $this->routingMap->get($routeUrl, $handler);
    }

    public function post(string $routeUrl, $handler)
    {
        $this->routingMap->post($routeUrl, $handler);
    }

    public function put(string $routeUrl, $handler)
    {
        $this->routingMap->put($routeUrl, $handler);
    }

    public function delete(string $routeUrl, $handler)
    {
        $this->routingMap->delete($routeUrl, $handler);
    }

    public function head(string $routeUrl, $handler)
    {
        $this->routingMap->head($routeUrl, $handler);
    }

    public function options(string $routeUrl, $handler)
    {
        $this->routingMap->options($routeUrl, $handler);
    }

    public function patch(string $routeUrl, $handler)
    {
        $this->routingMap->patch($routeUrl, $handler);
    }
}
