<?php

namespace Gekko\Http;

use DateTime;

class HttpClient
{
    public function __construct()
    {
    }

    public function exec(HttpRequest $request, bool $verbose = false, ?string $stderr_file = null, ?array &$redirects = null) : IHttpResponse
    {
        $headers = [];
        foreach ($request->getHeaders() as $header_name => $header_value)
            $headers[] = "{$header_name}: {$header_value}";

        $cookies = [];
        foreach ($request->getCookies() as $cookie_name => $cookie_value)
            $cookies[] = "{$cookie_name}={$cookie_value}";

        if (count($cookies) > 0)
            $headers[] = "Cookie: " . implode("; ", $cookies);

        // Get the HTTP protocol version
        $curl_proto_ver = CURL_HTTP_VERSION_NONE;

        $req_proto_ver = $request->getProtocolVersion();

        if ($req_proto_ver === HttpRequest::PROTO_VER_1_0)
            $curl_proto_ver = CURL_HTTP_VERSION_1_0;
        else if ($req_proto_ver === HttpRequest::PROTO_VER_1_1)
            $curl_proto_ver = CURL_HTTP_VERSION_1_1;
        else if ($req_proto_ver === HttpRequest::PROTO_VER_2_0)
            $curl_proto_ver = CURL_HTTP_VERSION_2;
        
        $options = [
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => true,     // return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_POST => $request->getMethod() === "POST",
            CURLOPT_POSTFIELDS => $request->getBody(),
            CURLOPT_HTTP_VERSION => $curl_proto_ver,
            CURLOPT_VERBOSE => $verbose
        ];

        if ($stderr_file !== null)
            $options[CURLOPT_STDERR] = fopen($stderr_file, 'a+');

        $ch = curl_init($request->getURI());
        curl_setopt_array($ch, $options);
        
        $content = curl_exec($ch);        
        $err = curl_errno($ch);

        $response = null;

        if ($err !== 0)
        {
            $response = new HttpResponse();
            $response->setStatus($request->getProtocolVersion(), 500, "Internal Server Error");

            if ($verbose)
                $response->setBody(curl_error($ch));
        }
        else
        {
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

            $full_headers_str = trim(substr($content, 0, $header_size));
            $full_headers = explode("\r\n\r\n", $full_headers_str);        
            $full_header = array_pop($full_headers);
            
            if ($redirects !== null)
            {
                foreach ($full_headers as $redirected_header)
                {
                    $redirects[] = $this->createHttpResponse($redirected_header);
                }
            }
            
            $body = substr($content, $header_size);
            $response = $this->createHttpResponse($full_header, $body);
        }

        curl_close($ch);

        if ($stderr_file !== null)
            fclose($options[CURLOPT_STDERR]);

        return $response;
    }

    protected function createHttpResponse(string $full_header, string $body = "")
    {
        $response = new HttpResponse();
        $response->setBody($body);

        $header_lines = explode("\r\n", $full_header);

        // Status line
        $status_line = array_shift($header_lines);
        $response->setStatusLine($status_line);

        // Headers and cookies        
        foreach ($header_lines as $header_line)
        {
            list($header_name, $header_value) = $this->parseHeaderLine($header_line);

            if (strtolower($header_name) === "set-cookie")
            {
                $response->setCookie($this->parseCookieLine($header_value));
            }
            else
            {
                $response->setHeader($header_name, $header_value);
            }            
        }

        return $response;
    }

    protected function parseHeaderLine(string $line)
    {
        list($header_name, $header_value) = array_map('trim', explode(":", $line, 2));

        return [ $header_name, $header_value ];
    }

    protected function parseCookieLine(string $line)
    {
        $cookie_name = "";
        $cookie_value = "";
        $cookie_expires= 0;
        $cookie_path = "";
        $cookie_domain = "";
        $cookie_secure = false;
        $cookie_http_only = false;

        $parts = explode(";", $line);

        // 0 is the name=value pair
        $part = array_shift($parts);
        list($cookie_name, $cookie_value) = array_map('trim', explode("=", $part, 2));

        // Check the rest of the options
        foreach ($parts as $part)
        {
            list($key, $value) = array_pad(array_map('trim', explode("=", $part, 2)), 2, "");

            $key = strtolower($key);

            if ($key === "expires")
            {
                $cookie_expires = (DateTime::createFromFormat(DateTime::COOKIE, $value))->getTimestamp();
            }
            else if ($key === "path")
            {
                $cookie_path = $value;
            }
            else if ($key === "domain")
            {
                $cookie_domain = $value;
            }
            else if ($key === "secure")
            {
                $cookie_secure = true;
            }
            else if ($key === "httponly")
            {
                $cookie_http_only = true;
            }
        }
        
        return new HttpCookie($cookie_name, $cookie_value, $cookie_expires, $cookie_path, $cookie_domain, $cookie_secure, $cookie_http_only);
    }
}