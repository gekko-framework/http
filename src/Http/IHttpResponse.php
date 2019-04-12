<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

interface IHttpResponse
{
    public function setBody(string $content) : void;

    public function appendToBody(string $content) : void;

    public function setHeader(string $header, string $value) : void;

    public function setHeaderLine(string $value) : void;

    public function setHeaders(array $headers) : void;

    public function getHeader(string $header) : ?string;

    public function getHeaders($headers) : array;

    public function setCookie(string $name, string $value, int $exp = null, string $path = null) : void;
}
