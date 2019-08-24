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
    
    public function getStatusLine() : string;
    
    public function setStatusLine(string $value) : void;
    
    public function setHeader(string $header, string $value) : void;

    public function getHeader(string $header) : ?string;

    public function setHeaders(array $headers) : void;

    public function getHeaders() : array;

    public function setCookie(HttpCookie $cookie) : void;

    public function getCookie(string $cookie_name) : ?HttpCookie;    

    public function setCookies(array $cookies) : void;
    
    public function getCookies() : array;        
}
