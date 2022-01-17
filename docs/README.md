# What is Sulis?

A tiny php framework

```php
require 'src/Sulis/Sulis.php';

Sulis::route('/', function () {
    echo 'hello world!';
});

Sulis::start();
```

[Learn more](http://esyede.github.io/sulis)

# Requirements

Sulis requires `PHP 7.4` or greater.

# License

Sulis is released under the [MIT](https://github.com/esyede/sulis/blob/main/LICENSE) license.

# Installation

1\. Download the files.

If you're using [Composer](https://getcomposer.org/), you can run the following command:

```
composer require esyede/sulis
```

OR you can [download](https://github.com/esyede/sulis/archive/master.zip) them directly
and extract them to your web directory.

2\. Configure your webserver.

For *Apache*, edit your `.htaccess` file with the following:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Note**: If you need to use sulis in a subdirectory add the line `RewriteBase /subdir/` just after `RewriteEngine On`.

For *Nginx*, add the following to your server declaration:

```
server {
    location / {
        try_files $uri $uri/ /index.php;
    }
}
```
3\. Create your `index.php` file.

First include the framework.

```php
require 'src/Sulis/Sulis.php';
```

If you're using Composer, run the autoloader instead.

```php
require 'vendor/autoload.php';
```

Then define a route and assign a function to handle the request.

```php
Sulis::route('/', function () {
    echo 'hello world!';
});
```

Finally, start the framework.

```php
Sulis::start();
```

# Routing

Routing in Sulis is done by matching a URL pattern with a callback function.

```php
Sulis::route('/', function () {
    echo 'hello world!';
});
```

The callback can be any object that is callable. So you can use a regular function:

```php
function hello () {
    echo 'hello world!';
}

Sulis::route('/', 'hello');
```

Or a class method:

```php
class Greeting
{
    public static function hello()
    {
        echo 'hello world!';
    }
}

Sulis::route('/', array('Greeting', 'hello'));
```

Or an object method:

```php
class Greeting
{
    public function __construct()
    {
        $this->name = 'John Doe';
    }

    public function hello()
    {
        echo "Hello, {$this->name}!";
    }
}

$greeting = new Greeting();

Sulis::route('/', array($greeting, 'hello'));
```

Routes are matched in the order they are defined. The first route to match a
request will be invoked.

## Method Routing

By default, route patterns are matched against all request methods. You can respond
to specific methods by placing an identifier before the URL.

```php
Sulis::route('GET /', function () {
    echo 'I received a GET request.';
});

Sulis::route('POST /', function () {
    echo 'I received a POST request.';
});
```

You can also map multiple methods to a single callback by using a `|` delimiter:

```php
Sulis::route('GET|POST /', function () {
    echo 'I received either a GET or a POST request.';
});
```

## Regular Expressions

You can use regular expressions in your routes:

```php
Sulis::route('/user/[0-9]+', function () {
    // This will match /user/1234
});
```

## Named Parameters

You can specify named parameters in your routes which will be passed along to
your callback function.

```php
Sulis::route('/@name/@id', function ($name, $id) {
    echo "hello, $name ($id)!";
});
```

You can also include regular expressions with your named parameters by using
the `:` delimiter:

```php
Sulis::route('/@name/@id:[0-9]{3}', function ($name, $id) {
    // This will match /bob/123
    // But will not match /bob/12345
});
```

Matching regex groups `()` with named parameters isn't supported.

## Optional Parameters

You can specify named parameters that are optional for matching by wrapping
segments in parentheses.

```php
Sulis::route('/blog(/@year(/@month(/@day)))', function ($year, $month, $day) {
    // This will match the following URLS:
    // /blog/2012/12/10
    // /blog/2012/12
    // /blog/2012
    // /blog
});
```

Any optional parameters that are not matched will be passed in as NULL.

## Wildcards

Matching is only done on individual URL segments. If you want to match multiple
segments you can use the `*` wildcard.

```php
Sulis::route('/blog/*', function () {
    // This will match /blog/2000/02/01
});
```

To route all requests to a single callback, you can do:

```php
Sulis::route('*', function () {
    // Do something
});
```

## Passing

You can pass execution on to the next matching route by returning `true` from
your callback function.

```php
Sulis::route('/user/@name', function ($name) {
    // Check some condition
    if ($name != "Bob") {
        // Continue to next route
        return true;
    }
});

Sulis::route('/user/*', function () {
    // This will get called
});
```

## Route Info

If you want to inspect the matching route information, you can request for the route
object to be passed to your callback by passing in `true` as the third parameter in
the route method. The route object will always be the last parameter passed to your
callback function.

```php
Sulis::route('/', function ($route) {
    // Array of HTTP methods matched against
    $route->methods;

    // Array of named parameters
    $route->params;

    // Matching regular expression
    $route->regex;

    // Contains the contents of any '*' used in the URL pattern
    $route->splat;
}, true);
```

# Extending

Sulis is designed to be an extensible framework. The framework comes with a set
of default methods and components, but it allows you to map your own methods,
register your own classes, or even override existing classes and methods.

## Mapping Methods

To map your own custom method, you use the `map` function:

```php
// Map your method
Sulis::map('hello', function ($name) {
    echo "hello $name!";
});

// Call your custom method
Sulis::hello('Bob');
```

## Registering Classes

To register your own class, you use the `register` function:

```php
// Register your class
Sulis::register('user', 'User');

// Get an instance of your class
$user = Sulis::user();
```

The register method also allows you to pass along parameters to your class
constructor. So when you load your custom class, it will come pre-initialized.
You can define the constructor parameters by passing in an additional array.
Here's an example of loading a database connection:

```php
// Register class with constructor parameters
Sulis::register('db', 'PDO', array('mysql:host=localhost;dbname=test','user','pass'));

// Get an instance of your class
// This will create an object with the defined parameters
//
//     new PDO('mysql:host=localhost;dbname=test','user','pass');
//
$db = Sulis::db();
```

If you pass in an additional callback parameter, it will be executed immediately
after class construction. This allows you to perform any set up procedures for your
new object. The callback function takes one parameter, an instance of the new object.

```php
// The callback will be passed the object that was constructed
Sulis::register('db', 'PDO', array('mysql:host=localhost;dbname=test','user','pass'), function ($db) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
});
```

By default, every time you load your class you will get a shared instance.
To get a new instance of a class, simply pass in `false` as a parameter:

```php
// Shared instance of the class
$shared = Sulis::db();

// New instance of the class
$new = Sulis::db(false);
```

Keep in mind that mapped methods have precedence over registered classes. If you
declare both using the same name, only the mapped method will be invoked.

# Overriding

Sulis allows you to override its default functionality to suit your own needs,
without having to modify any code.

For example, when Sulis cannot match a URL to a route, it invokes the `notFound`
method which sends a generic `HTTP 404` response. You can override this behavior
by using the `map` method:

```php
Sulis::map('notFound', function () {
    // Display custom 404 page
    include 'errors/404.html';
});
```

Sulis also allows you to replace core components of the framework.
For example you can replace the default Router class with your own custom class:

```php
// Register your custom class
Sulis::register('router', 'MyRouter');

// When Sulis loads the Router instance, it will load your class
$myrouter = Sulis::router();
```

Framework methods like `map` and `register` however cannot be overridden. You will
get an error if you try to do so.

# Filtering

Sulis allows you to filter methods before and after they are called. There are no
predefined hooks you need to memorize. You can filter any of the default framework
methods as well as any custom methods that you've mapped.

A filter function looks like this:

```php
function (&$params, &$output) {
    // Filter code
}
```

Using the passed in variables you can manipulate the input parameters and/or the output.

You can have a filter run before a method by doing:

```php
Sulis::before('start', function (&$params, &$output) {
    // Do something
});
```

You can have a filter run after a method by doing:

```php
Sulis::after('start', function (&$params, &$output) {
    // Do something
});
```

You can add as many filters as you want to any method. They will be called in the
order that they are declared.

Here's an example of the filtering process:

```php
// Map a custom method
Sulis::map('hello', function ($name) {
    return "Hello, $name!";
});

// Add a before filter
Sulis::before('hello', function (&$params, &$output) {
    // Manipulate the parameter
    $params[0] = 'Fred';
});

// Add an after filter
Sulis::after('hello', function (&$params, &$output) {
    // Manipulate the output
    $output .= " Have a nice day!";
});

// Invoke the custom method
echo Sulis::hello('Bob');
```

This should display:

    Hello Fred! Have a nice day!

If you have defined multiple filters, you can break the chain by returning `false`
in any of your filter functions:

```php
Sulis::before('start', function (&$params, &$output) {
    echo 'one';
});

Sulis::before('start', function (&$params, &$output) {
    echo 'two';

    // This will end the chain
    return false;
});

// This will not get called
Sulis::before('start', function (&$params, &$output) {
    echo 'three';
});
```

Note, core methods such as `map` and `register` cannot be filtered because they
are called directly and not invoked dynamically.

# Variables

Sulis allows you to save variables so that they can be used anywhere in your application.

```php
// Save your variable
Sulis::set('id', 123);

// Elsewhere in your application
$id = Sulis::get('id');
```
To see if a variable has been set you can do:

```php
if (Sulis::has('id')) {
     // Do something
}
```

You can clear a variable by doing:

```php
// Clears the id variable
Sulis::clear('id');

// Clears all variables
Sulis::clear();
```

Sulis also uses variables for configuration purposes.

```php
Sulis::set('sulis.log_errors', true);
```

# Views

Sulis provides some basic templating functionality by default. To display a view
template call the `render` method with the name of the template file and optional
template data:

```php
Sulis::render('hello.php', array('name' => 'Bob'));
```

The template data you pass in is automatically injected into the template and can
be reference like a local variable. Template files are simply PHP files. If the
content of the `hello.php` template file is:

```php
Hello, '<?php echo $name; ?>'!
```

The output would be:

    Hello, Bob!

You can also manually set view variables by using the set method:

```php
Sulis::view()->set('name', 'Bob');
```

The variable `name` is now available across all your views. So you can simply do:

```php
Sulis::render('hello');
```

Note that when specifying the name of the template in the render method, you can
leave out the `.php` extension.

By default Sulis will look for a `views` directory for template files. You can
set an alternate path for your templates by setting the following config:

```php
Sulis::set('sulis.views.path', '/path/to/views');
```

## Layouts

It is common for websites to have a single layout template file with interchanging
content. To render content to be used in a layout, you can pass in an optional
parameter to the `render` method.

```php
Sulis::render('header', array('heading' => 'Hello'), 'header_content');
Sulis::render('body', array('body' => 'World'), 'body_content');
```

Your view will then have saved variables called `header_content` and `body_content`.
You can then render your layout by doing:

```php
Sulis::render('layout', array('title' => 'Home Page'));
```

If the template files looks like this:

`header.php`:

```php
<h1><?php echo $heading; ?></h1>
```

`body.php`:

```php
<div><?php echo $body; ?></div>
```

`layout.php`:

```php
<html>
<head>
<title><?php echo $title; ?></title>
</head>
<body>
<?php echo $header_content; ?>
<?php echo $body_content; ?>
</body>
</html>
```

The output would be:
```html
<html>
<head>
<title>Home Page</title>
</head>
<body>
<h1>Hello</h1>
<div>World</div>
</body>
</html>
```

## Custom Views

Sulis allows you to swap out the default view engine simply by registering your
own view class. Here's how you would use the [Smarty](http://www.smarty.net/)
template engine for your views:

```php
// Load Smarty library
require './Smarty/libs/Smarty.class.php';

// Register Smarty as the view class
// Also pass a callback function to configure Smarty on load
Sulis::register('view', 'Smarty', array(), function ($smarty) {
    $smarty->template_dir = './templates/';
    $smarty->compile_dir = './templates_c/';
    $smarty->config_dir = './config/';
    $smarty->cache_dir = './cache/';
});

// Assign template data
Sulis::view()->assign('name', 'Bob');

// Display the template
Sulis::view()->display('hello.tpl');
```

For completeness, you should also override Sulis's default render method:

```php
Sulis::map('render', function ($template, $data) {
    Sulis::view()->assign($data);
    Sulis::view()->display($template);
});
```
# Error Handling

## Errors and Exceptions

All errors and exceptions are caught by Sulis and passed to the `error` method.
The default behavior is to send a generic `HTTP 500 Internal Server Error`
response with some error information.

You can override this behavior for your own needs:

```php
Sulis::map('error', function (Exception $ex) {
    // Handle error
    echo $ex->getTraceAsString();
});
```

By default errors are not logged to the web server. You can enable this by
changing the config:

```php
Sulis::set('sulis.log_errors', true);
```

## Not Found

When a URL can't be found, Sulis calls the `notFound` method. The default
behavior is to send an `HTTP 404 Not Found` response with a simple message.

You can override this behavior for your own needs:

```php
Sulis::map('notFound', function () {
    // Handle not found
});
```

# Redirects

You can redirect the current request by using the `redirect` method and passing
in a new URL:

```php
Sulis::redirect('/new/location');
```

By default Sulis sends a HTTP 303 status code. You can optionally set a
custom code:

```php
Sulis::redirect('/new/location', 401);
```

# Requests

Sulis encapsulates the HTTP request into a single object, which can be
accessed by doing:

```php
$request = Sulis::request();
```

The request object provides the following properties:

```
url - The URL being requested
base - The parent subdirectory of the URL
method - The request method (GET, POST, PUT, DELETE)
referrer - The referrer URL
ip - IP address of the client
ajax - Whether the request is an AJAX request
scheme - The server protocol (http, https)
user_agent - Browser information
type - The content type
length - The content length
query - Query string parameters
data - Post data or JSON data
cookies - Cookie data
files - Uploaded files
secure - Whether the connection is secure
accept - HTTP accept parameters
proxy_ip - Proxy IP address of the client
host - The request host name
```

You can access the `query`, `data`, `cookies`, and `files` properties
as arrays or objects.

So, to get a query string parameter, you can do:

```php
$id = Sulis::request()->query['id'];
```

Or you can do:

```php
$id = Sulis::request()->query->id;
```

## RAW Request Body

To get the raw HTTP request body, for example when dealing with PUT requests, you can do:

```php
$body = Sulis::request()->getBody();
```

## JSON Input

If you send a request with the type `application/json` and the data `{"id": 123}` it will be available
from the `data` property:

```php
$id = Sulis::request()->data->id;
```

# HTTP Caching

Sulis provides built-in support for HTTP level caching. If the caching condition
is met, Sulis will return an HTTP `304 Not Modified` response. The next time the
client requests the same resource, they will be prompted to use their locally
cached version.

## Last-Modified

You can use the `lastModified` method and pass in a UNIX timestamp to set the date
and time a page was last modified. The client will continue to use their cache until
the last modified value is changed.

```php
Sulis::route('/news', function () {
    Sulis::lastModified(1234567890);
    echo 'This content will be cached.';
});
```

## ETag

`ETag` caching is similar to `Last-Modified`, except you can specify any id you
want for the resource:

```php
Sulis::route('/news', function () {
    Sulis::etag('my-unique-id');
    echo 'This content will be cached.';
});
```

Keep in mind that calling either `lastModified` or `etag` will both set and check the
cache value. If the cache value is the same between requests, Sulis will immediately
send an `HTTP 304` response and stop processing.

# Stopping

You can stop the framework at any point by calling the `halt` method:

```php
Sulis::halt();
```

You can also specify an optional `HTTP` status code and message:

```php
Sulis::halt(200, 'Be right back...');
```

Calling `halt` will discard any response content up to that point. If you want to stop
the framework and output the current response, use the `stop` method:

```php
Sulis::stop();
```

# JSON

Sulis provides support for sending JSON and JSONP responses. To send a JSON response you
pass some data to be JSON encoded:

```php
Sulis::json(array('id' => 123));
```

For JSONP requests you, can optionally pass in the query parameter name you are
using to define your callback function:

```php
Sulis::jsonp(array('id' => 123), 'q');
```

So, when making a GET request using `?q=my_func`, you should receive the output:

```
my_func({"id":123});
```

If you don't pass in a query parameter name it will default to `jsonp`.


# Configuration

You can customize certain behaviors of Sulis by setting configuration values
through the `set` method.

```php
Sulis::set('sulis.log_errors', true);
```

The following is a list of all the available configuration settings:

    sulis.base_url - Override the base url of the request. (default: null)
    sulis.case_sensitive - Case sensitive matching for URLs. (default: false)
    sulis.handle_errors - Allow Sulis to handle all errors internally. (default: true)
    sulis.log_errors - Log errors to the web server's error log file. (default: false)
    sulis.views.path - Directory containing view template files. (default: ./views)
    sulis.views.cache - View template cache directory. (default: ./cache)

# Framework Methods

Sulis is designed to be easy to use and understand. The following is the complete
set of methods for the framework. It consists of core methods, which are regular
static methods, and extensible methods, which are mapped methods that can be filtered
or overridden.

## Core Methods

```php
Sulis::map($name, $callback) // Creates a custom framework method.
Sulis::register($name, $class, [$params], [$callback]) // Registers a class to a framework method.
Sulis::before($name, $callback) // Adds a filter before a framework method.
Sulis::after($name, $callback) // Adds a filter after a framework method.
Sulis::path($path) // Adds a path for autoloading classes.
Sulis::get($key) // Gets a variable.
Sulis::set($key, $value) // Sets a variable.
Sulis::has($key) // Checks if a variable is set.
Sulis::clear([$key]) // Clears a variable.
Sulis::init() // Initializes the framework to its default settings.
Sulis::app() // Gets the application object instance
```

## Extensible Methods

```php
Sulis::start() // Starts the framework.
Sulis::stop() // Stops the framework and sends a response.
Sulis::halt([$code], [$message]) // Stop the framework with an optional status code and message.
Sulis::route($pattern, $callback) // Maps a URL pattern to a callback.
Sulis::redirect($url, [$code]) // Redirects to another URL.
Sulis::render($file, [$data], [$key]) // Renders a template file.
Sulis::error($exception) // Sends an HTTP 500 response.
Sulis::notFound() // Sends an HTTP 404 response.
Sulis::etag($id, [$type]) // Performs ETag HTTP caching.
Sulis::lastModified($time) // Performs last modified HTTP caching.
Sulis::json($data, [$code], [$encode], [$charset], [$option]) // Sends a JSON response.
Sulis::jsonp($data, [$param], [$code], [$encode], [$charset], [$option]) // Sends a JSONP response.
```

Any custom methods added with `map` and `register` can also be filtered.


# Framework Instance

Instead of running Sulis as a global static class, you can optionally run it
as an object instance.

```php
require 'src/Sulis/autoload.php';

use Sulis\Engine;

$app = new Engine();

$app->route('/', function () {
    echo 'hello world!';
});

$app->start();
```

So instead of calling the static method, you would call the instance method with
the same name on the Engine object.
