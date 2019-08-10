<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http\Routing;

use \Gekko\Http\HttpHandlerType;

class RoutingMap
{
    /**
     * @var bool
     */
    protected $is_class;

    /**
     * @var string
     */
    protected $class_name;
    
    /**
     * @var string
     */
    protected $base_url;

    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $exceptions = [];

    public function __construct(string $url = null, string $class = null)
    {
        if (!isset($url) || $url === "/")
            $this->base_url = null;
        else
            $this->base_url = \str_replace("//", "/", $url);

        $this->is_class = $class != null;
        $this->class_name = $class;
    }

    public function getBaseUrl() : string
    {
        return $this->base_url ?? "/";
    }

    public function getRoutes() : array
    {
        return $this->routes;
    }

    public function getExceptions() : array
    {
        return $this->exceptions;
    }

    public function exception(string $path) : void
    {
        $this->exceptions[] = $this->makeRoute($path);
    }

    public function exceptions(array $paths) : void
    {
        $this->exceptions = array_merge($this->exceptions, $paths);
    }

    protected function getHandlerType($handler) : int
    {
        if ($handler instanceof \Closure) {
            return HttpHandlerType::Closure;
        }
        
        if (is_string($handler)) {
            // If the routing map is for an HttpController, $handler MUST be a method name
            return $this->is_class ? HttpHandlerType::Method : HttpHandlerType::HttpClass;
        }
        
        if (is_array($handler) && count($handler) == 2 && method_exists($handler[0], $handler[1])) {
            return HttpHandlerType::Method;
        }
        
        return HttpHandlerType::Unknown;
    }

    protected function addRoute(array $methods, $url, $handler) : void
    {
        $routehandler = null;
        $htype = $this->getHandlerType($handler);

        if ($htype == HttpHandlerType::HttpClass && is_string($handler)) {
            if (!in_array(\Gekko\Http\Routing\IHttpController::class, class_implements($handler))) {
                throw new \Exception("Class does not implement ". \Gekko\Http\Routing\IHttpController::class . ", it cannot be used as HttpController");
            }
            // If the handler is a class name, wrap it in an array
            $routehandler = [$handler];
        } elseif ($htype == HttpHandlerType::Method && $this->is_class && !is_array($handler)) {
            // If the handler is a controller's method, register it as [controllerClass, methodName]
            $routehandler = [$this->class_name, $handler];
        } else {
            // Closure
            $routehandler = $handler;
        }

        $this->routes[] = [
            'url' => $this->makeRoute($url),
            'handler' => $routehandler,
            'htype' => $htype,
            'methods' => $methods
        ];
    }

    public function register(RoutingMap $map) : void
    {
        foreach ($map->routes as $route) {
            $this->routes[] = [
                'url' => $route['url'],
                'handler' => $route['handler'],
                'htype' => $route['htype'],
                'methods' => $route['methods']
            ];
        }

        foreach ($map->exceptions as $exception) {
            $this->exceptions[] = $exception;
        }
    }

    public function controller(string $url, string $class)
    {
        $this->get($url, $class);
        $this->get($url, $class);
        $this->post($url, $class);
        $this->put($url, $class);
        $this->patch($url, $class);
        $this->delete($url, $class);
        $this->head($url, $class);
        $this->options($url, $class);        
    }

    public function get($url, $handler = null) : void
    {
        $this->addRoute(['GET'], $url, $handler);
    }

    public function post($url, $handler = null) : void
    {
        $this->addRoute(['POST'], $url, $handler);
    }

    public function put($url, $handler = null) : void
    {
        $this->addRoute(['PUT'], $url, $handler);
    }

    public function delete($url, $handler = null) : void
    {
        $this->addRoute(['DELETE'], $url, $handler);
    }

    public function head($url, $handler = null) : void
    {
        $this->addRoute(['HEAD'], $url, $handler);
    }

    public function options($url, $handler = null) : void
    {
        $this->addRoute(['OPTIONS'], $url, $handler);
    }

    public function patch($url, $handler = null) : void
    {
        $this->addRoute(['PATCH'], $url, $handler);
    }

    private function makeRoute($url) : string
    {
        if (strlen($this->base_url) == 0)
            return $url;
            
        $fullurl = "{$this->base_url}/{$url}";
        return str_replace("//", "/", $fullurl);
    }
}
