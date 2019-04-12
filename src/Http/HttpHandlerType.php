<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

abstract class HttpHandlerType
{
    const Unknown = -1;
    const Closure = 1;
    const Method = 2;
    const HttpClass = 3;
}
