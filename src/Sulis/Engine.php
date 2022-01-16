<?php

declare(strict_types=1);

namespace sulis;

use ErrorException;
use Exception;
use Sulis\DB;
use Sulis\Dispatcher;
use Sulis\Jwt;
use Sulis\Loader;
use Sulis\Request;
use Sulis\Response;
use Sulis\Router;
use Sulis\Validator;
use Sulis\View;
use Throwable;

class Engine
{
    protected array $vars;
    protected Loader $loader;
    protected Dispatcher $dispatcher;

    public function __construct()
    {
        $this->vars = [];
        $this->loader = new Loader();
        $this->dispatcher = new Dispatcher();
        $this->init();
    }

    public function __call(string $name, array $params)
    {
        $callback = $this->dispatcher->get($name);

        if (is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        if (! $this->loader->get($name)) {
            throw new Exception("{$name} must be a mapped method.");
        }

        $shared = empty($params) || $params[0];

        return $this->loader->load($name, $shared);
    }

    public function init(): void
    {
        static $initialized = false;

        $self = $this;

        if ($initialized) {
            $this->vars = [];
            $this->loader->reset();
            $this->dispatcher->reset();
        }

        $this->loader->register('request', Request::class);
        $this->loader->register('response', Response::class);
        $this->loader->register('router', Router::class);
        $this->loader->register('db', DB::class);
        $this->loader->register('jwt', Jwt::class);
        $this->loader->register('validator', Validator::class);
        $this->loader->register('view', View::class, [], function ($view) use ($self) {
            $view->setViewFolder($self->get('sulis.views.path'));
            $view->setCacheFolder($self->get('sulis.views.cache'));
            $view->createCacheFolder();
        });

        $methods = [
            'start', 'stop', 'route', 'halt', 'error', 'notFound',
            'render', 'redirect',
            'etag', 'lastModified',
            'json', 'jsonp',
            'post',
            'put', 'patch', 'delete',
        ];

        foreach ($methods as $name) {
            $this->dispatcher->set($name, [$this, '_' . $name]);
        }

        $this->set('sulis.base_url');
        $this->set('sulis.case_sensitive', false);
        $this->set('sulis.handle_errors', true);
        $this->set('sulis.log_errors', false);
        $this->set('sulis.views.path', './views');
        $this->set('sulis.views.path', './cache');
        $this->set('sulis.content_length', true);

        $this->before('start', function () use ($self) {
            if ($self->get('sulis.handle_errors')) {
                set_error_handler([$self, 'handleError']);
                set_exception_handler([$self, 'handleException']);
            }

            $self->router()->case_sensitive = $self->get('sulis.case_sensitive');
            $self->response()->content_length = $self->get('sulis.content_length');
        });

        $initialized = true;
    }

    public function handleError(int $number, string $message, string $file, int $line)
    {
        if ($number & error_reporting()) {
            throw new ErrorException($message, $number, 0, $file, $line);
        }
    }

    public function handleException($e): void
    {
        if ($this->get('sulis.log_errors')) {
            error_log($e->getMessage());
        }

        $this->error($e);
    }

    public function map(string $name, callable $callback): void
    {
        if (method_exists($this, $name)) {
            throw new Exception('Cannot override an existing framework method.');
        }

        $this->dispatcher->set($name, $callback);
    }

    public function register(string $name, string $class, array $params = [], ?callable $callback = null): void
    {
        if (method_exists($this, $name)) {
            throw new Exception('Cannot override an existing framework method.');
        }

        $this->loader->register($name, $class, $params, $callback);
    }

    public function before(string $name, callable $callback): void
    {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    public function after(string $name, callable $callback): void
    {
        $this->dispatcher->hook($name, 'after', $callback);
    }

    public function get(?string $key = null)
    {
        if (null === $key) {
            return $this->vars;
        }

        return $this->vars[$key] ?? null;
    }

    public function set($key, $value = null): void
    {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->vars[$k] = $v;
            }
        } else {
            $this->vars[$key] = $value;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->vars[$key]);
    }

    public function clear(?string $key = null): void
    {
        if (null === $key) {
            $this->vars = [];
        } else {
            unset($this->vars[$key]);
        }
    }

    public function path(string $dir): void
    {
        $this->loader->addDirectory($dir);
    }

    public function _start(): void
    {
        $dispatched = false;
        $self = $this;
        $request = $this->request();
        $response = $this->response();
        $router = $this->router();

        $this->after('start', function () use ($self) {
            $self->stop();
        });

        if (ob_get_length() > 0) {
            $response->write(ob_get_clean());
        }

        ob_start();

        while ($route = $router->route($request)) {
            $params = array_values($route->params);

            if ($route->pass) {
                $params[] = $route;
            }

            $continue = $this->dispatcher->execute($route->callback, $params);
            $dispatched = true;

            if (! $continue) {
                break;
            }

            $router->next();

            $dispatched = false;
        }

        if (! $dispatched) {
            $this->notFound();
        }
    }

    public function _error($e): void
    {
        $msg = sprintf(
            '<h1>500 Internal Server Error</h1><h3>%s (%s)</h3><pre>%s</pre>',
            $e->getMessage(),
            $e->getCode(),
            $e->getTraceAsString()
        );

        try {
            $this->response()
                ->clear()
                ->status(500)
                ->write($msg)
                ->send();
        } catch (Throwable $e) {
            exit($msg);
        } catch (Exception $e) {
            exit($msg);
        }
    }

    public function _stop(?int $code = null): void
    {
        $response = $this->response();

        if (! $response->sent()) {
            if (null !== $code) {
                $response->status($code);
            }

            $response->write(ob_get_clean())->send();
        }
    }

    public function _route(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $this->router()->map($pattern, $callback, $pass_route);
    }

    public function _post(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $this->router()->map('POST ' . $pattern, $callback, $pass_route);
    }

    public function _put(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $this->router()->map('PUT ' . $pattern, $callback, $pass_route);
    }

    public function _patch(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $this->router()->map('PATCH ' . $pattern, $callback, $pass_route);
    }

    public function _delete(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $this->router()->map('DELETE ' . $pattern, $callback, $pass_route);
    }

    public function _halt(int $code = 200, string $message = ''): void
    {
        $this->response()
            ->clear()
            ->status($code)
            ->write($message)
            ->send();
        exit();
    }

    public function _notFound(): void
    {
        $this->response()
            ->clear()
            ->status(404)
            ->write(
                '<h1>404 Not Found</h1><h3>The page you have requested could not be found.</h3>' .
                str_repeat(' ', 512)
            )
            ->send();
    }

    public function _redirect(string $url, int $code = 303): void
    {
        $base = $this->get('sulis.base_url');

        if (null === $base) {
            $base = $this->request()->base;
        }

        if ('/' !== $base && false === strpos($url, '://')) {
            $url = $base . preg_replace('#/+#', '/', '/' . $url);
        }

        $this->response()
            ->clear()
            ->status($code)
            ->header('Location', $url)
            ->send();
    }

    public function _render(string $file, ?array $data = null, ?string $key = null): void
    {
        if (null !== $key) {
            $this->view()->set($key, $this->view()->fetch($file, $data));
        } else {
            $this->view()->render($file, $data);
        }
    }

    public function _json(
        $data,
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = 0
    ): void {
        $json = $encode ? json_encode($data, $option) : $data;

        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json; charset=' . $charset)
            ->write($json)
            ->send();
    }

    public function _jsonp(
        $data,
        string $param = 'jsonp',
        int $code = 200,
        bool $encode = true,
        string $charset = 'utf-8',
        int $option = 0
    ): void {
        $json = $encode ? json_encode($data, $option) : $data;
        $callback = $this->request()->query[$param];

        $this->response()
            ->status($code)
            ->header(
                'Content-Type',
                'application/javascript; charset=' . $charset
            )
            ->write($callback . '(' . $json . ');')
            ->send();
    }

    public function _etag(string $id, string $type = "strong"): void
    {
        $id = ('weak' === $type ? 'W/' : '') . $id;

        $this->response()->header('ETag', $id);

        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
        && $_SERVER['HTTP_IF_NONE_MATCH'] === $id) {
            $this->halt(304);
        }
    }

    public function _lastModified(int $time): void
    {
        $this->response()->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', $time));

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
        && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $time) {
            $this->halt(304);
        }
    }
}
