<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

interface IHttpContext
{
    /**
     * Dispatch an HTTP request and return an HTTP response
     *
     * @param IHttpRequest $request Incoming HTTP Request
     * @return IHttpResponse HTTP Response for the incoming request
     */
    public function dispatch(IHttpRequest $request) : IHttpResponse;
}
