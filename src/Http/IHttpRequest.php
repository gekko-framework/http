<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

interface IHttpRequest
{
    public function getBody() : string;

    public function addProperty(string $name, $val) : void;

    public function setProperty(string $name, $val) : void;

    public function getProperty(string $name);

    public function getProtocolVersion() : int;

    public function isPost() : bool;

    public function isGet() : bool;

    public function isPut() : bool;

    public function isDelete() : bool;

    public function isOptions() : bool;

    public function getMethod() : string;

    public function getURI() : URI;

    public function getHeaders() : array;

    public function hasHeader($name) : bool;

    public function getHeader($name) : ?string;

    public function getCookies() : array;

    public function hasCookie($name) : bool;

    public function getCookie($name) : string;

    public function hostname() : string;

    public function toLocalPath(string $path) : string;

    public function getRootUri() : string;

    public function toUri(string $path) : string;

    public function toRelativeUri(string $path) : string;

    public function createHttpResponse(int $status = 200, string $description = "OK") : IHttpResponse;
}
