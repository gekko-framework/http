<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http\Routing;

class RouteParams implements \ArrayAccess
{
    /**
     * @var array
     */
    private $params;

    public function __construct(array $params = [])
    {
        $this->params = $params ?: [];
    }

    public function offsetSet($name, $value)
    {
        $this->params[$name] = $value;
    }

    public function offsetExists($name)
    {
        return isset($this->params[$name]);
    }

    public function offsetUnset($name)
    {
        unset($this->params[$name]);
    }

    public function offsetGet($name)
    {
        return isset($this->params[$name]) ? $this->params[$name] : null;
    }

    public function has($name)
    {
        $param = $this->params ?: [];
        return isset($param[$name]);
    }

    public function get($name)
    {
        $param = $this->params ?: [];
        return isset($param[$name]) ? $param[$name] : null;
    }
}
