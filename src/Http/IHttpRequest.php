<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

interface IHttpRequest
{
    public function addProperty(string $name, $val) : void;

    public function setProperty(string $name, $val) : void;

    public function getProperty(string $name);

    public function isPost() : bool;

    public function isGet() : bool;

    public function isPut() : bool;

    public function isDelete() : bool;

    public function isOptions() : bool;

    public function hasParameter($name) : bool;

    public function getParameters() : array;

    public function getParameter($name);

    public function hasMethodParameter($method, $name) : bool;

    public function getMethodParameter($method, $name);

    public function getMethod() : string;

    public function getURI() : URI;

    public function getHeaders() : array;

    public function hasHeader($name) : bool;

    public function getHeader($name) : ?string;

    public function getCookies() : array;

    public function hasCookie($name) : bool;

    public function getCookie($name) : string;

    public function hostname($uri = "") : string;

    public function toLocalPath(string $path) : string;

    public function getRootUri() : string;

    public function toUri(string $path) : string;
}
