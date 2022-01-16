<?php

declare(strict_types=1);

namespace Sulis;

use Sulis\Collection;

final class Request
{
    public string $url;
    public string $base;
    public string $method;
    public string $referrer;
    public string $ip;
    public bool $ajax;
    public string $scheme;
    public string $user_agent;
    public string $type;
    public int $length;
    public Collection $query;
    public Collection $data;
    public Collection $cookies;
    public Collection $files;
    public bool $secure;
    public string $accept;
    public string $proxy_ip;
    public string $host;

    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = [
                'url' => str_replace('@', '%40', self::getVar('REQUEST_URI', '/')),
                'base' => str_replace(['\\', ' '], ['/', '%20'], dirname(self::getVar('SCRIPT_NAME'))),
                'method' => self::getMethod(),
                'referrer' => self::getVar('HTTP_REFERER'),
                'ip' => self::getVar('REMOTE_ADDR'),
                'ajax' => 'XMLHttpRequest' === self::getVar('HTTP_X_REQUESTED_WITH'),
                'scheme' => self::getScheme(),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type' => self::getVar('CONTENT_TYPE'),
                'length' => (int) self::getVar('CONTENT_LENGTH', 0),
                'query' => new Collection($_GET),
                'data' => new Collection($_POST),
                'cookies' => new Collection($_COOKIE),
                'files' => new Collection($_FILES),
                'secure' => 'https' === self::getScheme(),
                'accept' => self::getVar('HTTP_ACCEPT'),
                'proxy_ip' => self::getProxyIpAddress(),
                'host' => self::getVar('HTTP_HOST'),
            ];
        }

        $this->init($config);
    }

    public function init(array $properties = [])
    {
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        if ('/' !== $this->base && '' !== $this->base && 0 === strpos($this->url, $this->base)) {
            $this->url = substr($this->url, \strlen($this->base));
        }

        if (empty($this->url)) {
            $this->url = '/';
        } else {
            $_GET = array_merge($_GET, self::parseQuery($this->url));
            $this->query->setData($_GET);
        }

        if (0 === strpos($this->type, 'application/json')) {
            $body = self::getBody();

            if ('' !== $body && null !== $body) {
                $data = json_decode($body, true);

                if (is_array($data)) {
                    $this->data->setData($data);
                }
            }
        }
    }

    public static function getBody(): ?string
    {
        static $body;

        if (null !== $body) {
            return $body;
        }

        $method = self::getMethod();

        if ('POST' === $method || 'PUT' === $method || 'DELETE' === $method || 'PATCH' === $method) {
            $body = file_get_contents('php://input');
        }

        return $body;
    }

    public static function getMethod(): string
    {
        $method = self::getVar('REQUEST_METHOD', 'GET');

        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        return strtoupper($method);
    }

    public static function getProxyIpAddress(): string
    {
        static $forwarded = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        foreach ($forwarded as $key) {
            if (array_key_exists($key, $_SERVER)) {
                sscanf($_SERVER[$key], '%[^,]', $ip);

                if (false !== filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
                    return $ip;
                }
            }
        }

        return "";
    }

    public static function getVar(string $var, $default = '')
    {
        return $_SERVER[$var] ?? $default;
    }

    public static function parseQuery(string $url): array
    {
        $args = parse_url($url);
        $params = [];

        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }

    public static function getScheme(): string
    {
        if ((isset($_SERVER['HTTPS']) && 'on' === strtolower($_SERVER['HTTPS']))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'])
        || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && 'on' === $_SERVER['HTTP_FRONT_END_HTTPS'])
        || (isset($_SERVER['REQUEST_SCHEME']) && 'https' === $_SERVER['REQUEST_SCHEME'])) {
            return 'https';
        }

        return 'http';
    }
}
