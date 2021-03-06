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
use \Gekko\Http\StaticResourceHandler;
use \Gekko\DependencyInjection\IDependencyInjector;
use Gekko\Http\IHttpMiddleware;
use Gekko\Http\IHttpResponse;

class Router implements IHttpMiddleware
{
    /**
     * @var string
     */
    private const ROUTE_PARAM_REGEX = '#\{([a-zA-Z\-_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?:(.+)?\}#';

    /**
     * @var \Gekko\Http\Routing\RoutingMap
     */
    private $routing_map;

    /**
     * @var \Gekko\DependencyInjection\IDependencyInjector
     */
    private $injector;


    public function __construct(IDependencyInjector $injector)
    {
        $this->routing_map = new RoutingMap();
        $this->injector = $injector;
    }


    public function apply(IHttpRequest $request, IHttpResponse $response, callable $next)
    {
        if ($request == null || $response == null) {
            throw new Exception("Missing parameters for middleware " . __CLASS__);
        }

        $this->enroute($request);
        return $next($request, $response);
    }

    public function isException(IHttpRequest $request) : bool
    {
        $request_url = $request->getURI()->getPath();

        if ($request_url[\strlen($request_url) - 1] != "/")
            $request_url .= "/";

        $maybe_local_file = $request->toLocalPath($request->getURI()->getLocalPath());

        $exceptions = $this->routing_map->getExceptions();

        foreach ($exceptions as $exception_uri) {
            if (preg_match("#/?" . $exception_uri . "#", $request_url) && file_exists($maybe_local_file)) {
                return true;
            }
        }

        return false;
    }

    public function enroute(IHttpRequest $request) : bool
    {
        if ($this->isException($request)) {
            $this->injector->getContainer()->add(Route::class, [
                'reference' => new Route([StaticResourceHandler::class, "serve"], new RouteParams(), $this->routing_map)]
            );
            return true;
        }

        return $this->findRoute($this->routing_map, $request);
    }

    protected function findRoute(RoutingMap $routing_map, IHttpRequest $request) : bool
    {
        $verb = $request->getMethod();

        // Test routes
        foreach ($routing_map->getRoutes() as $route_config) {
            $route_url = $route_config['url'];

            // If the current verb is not supported by the route, continue
            if (!in_array($verb, $route_config['methods']))
                continue;

            // Handler is the Closure or IHttpController that will be called to
            // process the HTTP request
            $handler = $route_config['handler'];
            $handler_type = $route_config['htype'];

            if ($handler_type == HttpHandlerType::Unknown)
                continue;

            // Split the URL of the route to start building the regex
            $segments_regexes = URI::segments($route_url);

            // Save the route's parameters
            $route_params = [];

            foreach ($segments_regexes as $i => $segment_regex) {
                // Path regex will contain a regex for this specific part of the path
                $path_regex = null;

                // Change capturing groups for non-capturing groups
                $path_regex = $this->sanitizeRegex($segment_regex);

                // If route part is a parameter, process it and add it to the params array
                if (preg_match(self::ROUTE_PARAM_REGEX, $segment_regex)) {
                    // parseParams returns an array with keys [ name => <var name> , regex => <accepted values> ]
                    $params = $this->parseParam($segment_regex);

                    $k = $i;
                    $segment = "";
                    foreach ($params as $param)
                    {
                        // Add the param to the params array. IMPORTANT: We are using the $i value to keep track of
                        // the part of the URL this param is occupying
                        $route_params[$k++] = $param;

                        $segment .= "{$param['prefix']}({$param['regex']}){$param['postfix']}";
                    }

                    // Update the path in the URL regex with the current path
                    $segments_regexes[$i] = "\/?{$segment}";
                }
                else
                {
                    // Update the path in the URL regex with the current path
                    $segments_regexes[$i] = "\/?({$path_regex})";
                }
            }

            if ($request->getRootUri() !== "/")
                \array_unshift($segments_regexes, $request->getRootUri());

            // Join the regexes (do not add slashes, they are added in previous loop)
            $route_full_regex = implode('', $segments_regexes);

            // 1) Add an optional trailing slash.
            // 2) HttpHandlerType::HttpClass matches the initial part of the regex, but the specific method to be
            // called is determined in another findRoute trip, so we are not looking for a complete match ($ symbol) right
            // now. Once we call findRoute with the matched controller, the handlerType will change to Method or Closure
            // so it will try to match the entire request then.
            $route_full_regex = "#^{$route_full_regex}(\/)?" . ($handler_type == HttpHandlerType::HttpClass ? "" : "$") . "#";

            // Get Request URL to be tested again $route_full_regex
            $request_url = $request->getURI()->getPath();

            if ($request_url[\strlen($request_url) - 1] != "/")
                $request_url .= "/";

            // Make some adjustments if needed:
            $last_index = strlen($request_url)-1;
            
            // 1) Remove the trailing slash
            if ($last_index > 0 && $request_url[$last_index] == '/')
                $request_url = substr($request_url, 0, $last_index);

            // 2) Add a leading slash
            if ($last_index > 0 && $request_url[0] != '/')
                $request_url = '/' . $request_url;

            // Find coincidences and filter results
            $matches = $this->tryMatchRoute($route_full_regex, $request_url);

            // No matches, "leave this route" (pun intended)
            if (empty($matches))
                continue;

            // Matched route! Look for routed parameters
            $route_params_obj = new RouteParams();
            $j = 0;
            // Becuase we saved $route_params with the key as the position they occupy in the URL
            // we can retrieve the matched value using that index.
            // If URL is /something/{param:}/test our $i value for the parameter will be 1
            foreach ($route_params as $i => $route_param) {

                if (!isset($matches[$i]))
                    continue;

                $param_value = $matches[$i];

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
                 *  if (empty($param_value))
                 *      continue;
                 */

                // Save the parameter with its name if it has one, or use a 0-based index
                $route_params_obj[($route_param['name']  != null ? $route_param['name'] : $j++)] = $param_value;
            }

            // If handler types are Closure or Method, we finished our search, but if the type is
            // HttpController, this is the first step, now we need to use the RoutingMap object
            // returned by IHttpController->routes() method that contains the controller's registerd
            // routes
            if ($handler_type == HttpHandlerType::Closure || $handler_type == HttpHandlerType::Method) {
                $this->injector->getContainer()->add(Route::class, ['reference' => new Route($handler, $route_params_obj, $routing_map)]);
                return true;
            } elseif ($handler_type == HttpHandlerType::HttpClass) {
                foreach ($handler as $handler_class) {
                    // Create a Routing Map for the specific IHttpController
                    $handler_routing_map = new RoutingMap($route_url, $handler_class);
                    $handler = $this->injector->make($handler_class);
                    $handler->routes($handler_routing_map);
                    
                    if ($this->findRoute($handler_routing_map, $request))
                        return true;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    protected function tryMatchRoute($route_regex, $request_url) : array
    {
        $matches = [];
        $matches_count = preg_match_all($route_regex, $request_url, $matches);

        // We should have at least 1 capturing group
        if ($matches_count === false || $matches_count === 0) {
            return [];
        }

        // Remove unnecessary offset 0
        unset($matches[0]);
        $matches = array_values($matches);
        
        // Return the single value of the match, instead of an array containing it.
        $matches = array_map(
            function ($exception) use ($matches) {
                return $exception[0];
            },
            $matches
        );

        // Clean empty groups and
        return $matches;
    }

    protected function parseParam(string $param_segment) : array
    {
        $params = [];
        $base_offset = 0;

        do {

            $start_brace = strpos($param_segment, "{", $base_offset);
            $end_brace = strpos($param_segment, "}", $start_brace);
            $next_start_brace = strpos($param_segment, "{", $start_brace + 1);

            $prefix = "";
            $postfix = "";
            
            if ($base_offset < $start_brace)
                $prefix = substr($param_segment, $base_offset, $start_brace);

            if ($next_start_brace === false)
                $postfix = substr($param_segment, $end_brace + 1);
            else
                $postfix = substr($param_segment, $end_brace + 1, $next_start_brace - $end_brace - 1);

            // Remove {}
            $name_and_regex = substr($param_segment, $start_brace + 1, $end_brace - $start_brace - 1);
            // Get position of :
            $pos = strpos($name_and_regex, ":");
            // Get param name (if it has one)
            $name = substr($name_and_regex, 0, $pos);
            // Get and sanitize param regex
            $regex = $this->sanitizeRegex(substr($name_and_regex, $pos+1));

            $params[] = [
                'prefix' => $prefix,
                'name' => strlen($name) == 0 ? null : $name,
                // Default regex for parameters [^/#?]+?
                'regex' => strlen($regex) == 0 ? "[^\/\#\?]+" : $regex,
                'postfix' => $postfix
            ];

            $base_offset = $next_start_brace;
        } while ($base_offset !== false);

        return $params;
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
        $this->routing_map->exception($path);
    }

    public function exceptions(array $paths)
    {
        $this->routing_map->exceptions($paths);
    }

    public function register(RoutingMap $map)
    {
        $this->routing_map->register($map);
    }

    public function get(string $route_url, $handler)
    {
        $this->routing_map->get($route_url, $handler);
    }

    public function post(string $route_url, $handler)
    {
        $this->routing_map->post($route_url, $handler);
    }

    public function put(string $route_url, $handler)
    {
        $this->routing_map->put($route_url, $handler);
    }

    public function delete(string $route_url, $handler)
    {
        $this->routing_map->delete($route_url, $handler);
    }

    public function head(string $route_url, $handler)
    {
        $this->routing_map->head($route_url, $handler);
    }

    public function options(string $route_url, $handler)
    {
        $this->routing_map->options($route_url, $handler);
    }

    public function patch(string $route_url, $handler)
    {
        $this->routing_map->patch($route_url, $handler);
    }
}
