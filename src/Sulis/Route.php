<?php

declare(strict_types=1);

namespace Sulis;

final class Route
{
    public string $pattern;
    public $callback;
    public array $methods = [];
    public array $params = [];
    public ?string $regex = null;
    public string $splat = '';
    public bool $pass = false;

    public function __construct(string $pattern, $callback, array $methods, bool $pass)
    {
        $this->pattern = $pattern;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->pass = $pass;
    }

    public function matchUrl(string $url, bool $case_sensitive = false): bool
    {
        if ('*' === $this->pattern || $this->pattern === $url) {
            return true;
        }

        $ids = [];
        $last_char = substr($this->pattern, -1);

        if ('*' === $last_char) {
            $n = 0;
            $len = strlen($url);
            $count = substr_count($this->pattern, '/');

            for ($i = 0; $i < $len; $i++) {
                if ('/' === $url[$i]) {
                    $n++;
                }

                if ($n === $count) {
                    break;
                }
            }

            $this->splat = (string) substr($url, $i + 1);
        }

        $regex = str_replace([')', '/*'], [')?', '(/?|/.*?)'], $this->pattern);
        $regex = preg_replace_callback('#@([\w]+)(:([^/\(\)]*))?#', function ($matches) use (&$ids) {
            $ids[$matches[1]] = null;
            return isset($matches[3])
                    ? '(?P<' . $matches[1] . '>' . $matches[3] . ')'
                    : '(?P<' . $matches[1] . '>[^/\?]+)';
        }, $regex);

        if ('/' === $last_char) {
            $regex .= '?';
        } else {
            $regex .= '/?';
        }

        if (preg_match('#^' . $regex . '(?:\?.*)?$#' . ($case_sensitive ? '' : 'i'), $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = array_key_exists($k, $matches) ? urldecode($matches[$k]) : null;
            }

            $this->regex = $regex;
            return true;
        }

        return false;
    }

    public function matchMethod(string $method): bool
    {
        return count(array_intersect([$method, '*'], $this->methods)) > 0;
    }
}
