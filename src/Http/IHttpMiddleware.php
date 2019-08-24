<?php

namespace Gekko\Http;

interface IHttpMiddleware
{
    function apply(IHttpRequest $request, IHttpResponse $response, callable $next);
}