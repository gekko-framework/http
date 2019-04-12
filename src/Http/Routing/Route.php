<?php

namespace Gekko\Http\Routing;

class Route
{
    /**
     * @var mixed
     */
    private $handler;

    /**
     * @var RouteParams
     */
    private $params;

    /**
     * @var RoutingMap
     */
    private $routingMap;

    public function __construct($handler, RouteParams $params, RoutingMap $routingMap)
    {
        $this->handler = $handler;
        $this->params = $params;
        $this->routingMap = $routingMap;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getParameters() : RouteParams
    {
        return $this->params;
    }

    public function getRoutingMap() : RoutingMap
    {
        return $this->routingMap;
    }
}
