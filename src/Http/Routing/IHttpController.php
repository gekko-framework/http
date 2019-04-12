<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http\Routing;

interface IHttpController
{
    public function routes(RoutingMap $routingmap);
}
