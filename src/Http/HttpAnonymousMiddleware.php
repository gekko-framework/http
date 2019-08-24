<?php

namespace Gekko\Http;

class HttpAnonymousMiddleware implements IHttpMiddleware
{
    /**
     * Callable middleware
     *
     * @param callable $callable
     */
    private $callable;

    public function __construct($callable)
    {
        if (!is_callable($callable))
            throw new Exception("Middleware is not callable", 1);

        $this->callable = $callable;
    }

    public function apply(IHttpRequest $request, IHttpResponse $response, callable $next)
    {
        return $this->callable->__invoke($request, $response, $next);
    }
}