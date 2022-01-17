# What is Sulis?
A tiny php framework

```php
require 'src/Sulis/Sulis.php';

Sulis::route('/', function () {
    echo 'hello world!';
});

Sulis::start();
```

<!-- MarkdownTOC autolink="true" autoanchor="true" levels="1,3" bracket="round" lowercase="only_ascii" -->

# Requirements
Sulis membutuhkan `PHP 7.4` atau lebih tinggi.

# License
Sulis dirilis di bawah lisensi [MIT](https://github.com/esyede/sulis/blob/main/LICENSE).

# Installation
**1. Unduh file**

Jika anda menggunakan [Composer](https://getcomposer.org/), anda dapat menjalankan perintah berikut:
```bash
composer require esyede/sulis
```

ATAU, Anda dapat [mengunduh](https://github.com/esyede/sulis/archive/master.zip) secara langsung dan mengekstraknya ke direktori web anda.

**2. Konfigurasikan server web anda.**

Untuk *Apache*, edit file `.htaccess` anda seperti berikut ini:

```apacheconf
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

  > [!TIP] Jika anda perlu menggunakan sulis dalam subdirektori,
tambahkan baris `RewriteBase /subdir/` tepat setelah `RewriteEngine On`.

Untuk *Nginx*, tambahkan berikut ini ke deklarasi server anda:
```nginx
server {
    location / {
        try_files $uri $uri/ /index.php;
    }
}
```

**3. Buat file `index.php` anda.**

Pertama, include frameworknya:
```php
require 'src/Sulis/Sulis.php';
```

Jika anda menggunakan Composer, jalankan autoloader sebagai gantinya:
```php
require 'vendor/autoload.php';
```

Kemudian tentukan rute dan tetapkan fungsi untuk menangani rute tersebut:

```php
Sulis::route('/', function () {
    echo 'hello world!';
});
```

Terakhir, jalankan.
```php
Sulis::start();
```

# Routing
Routing di sulis dilakukan dengan mencocokkan pola URL dengan fungsi callback.
```php
Sulis::route('/', function () {
    echo 'hello world!';
});
```

Callback dapat berupa objek apa pun yang sifatnya `callable`. Jadi, anda juga dapat menggunakan fungsi reguler:
```php
function hello () {
    echo 'hello world!';
}

Sulis::route('/', 'hello');
```

Atau method milik sebuah kelas:
```php
class Greeting
{
    public static function hello()
    {
        echo 'hello world!';
    }
}

Sulis::route('/', [Greeting::class, 'hello']);
```

Atau methode milik object:

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

Sulis::route('/', [$greeting, 'hello']);
```

Rute dicocokkan sesuai urutan yang anda ditentukan. Rute pertama yang cocok dengan request akan dijalankan.

## Method Routing
Secara default, pola rute dicocokkan dengan semua http request method. Anda dapat merespon
ke method tertentu dengan menempatkan identifier sebelum URL.
```php
Sulis::route('GET /', function () {
    echo 'I received a GET request.';
});

Sulis::route('POST /', function () {
    echo 'I received a POST request.';
});
```

Anda juga dapat memetakan beberapa method ke satu callback dengan menggunakan pembatas `|`:

```php
Sulis::route('GET|POST /', function () {
    echo 'I received either a GET or a POST request.';
});
```

## Regular Expressions
Anda dapat menggunakan regular expression di rute anda:
```php
Sulis::route('/user/[0-9]+', function () {
    // Ini akan cocok dengan /user/1234
});
```

## Named Parameters
Anda dapat menentukan named parameter di rute anda yang nantinya akan dioper ke fungsi callback anda.
```php
Sulis::route('/@name/@id', function ($name, $id) {
    echo "hello, $name ($id)!";
});
```

Anda juga dapat menyertakan regular expression dengan named parameter anda dengan menggunakan pembatas `:`:
```php
Sulis::route('/@name/@id:[0-9]{3}', function ($name, $id) {
    // Ini akan cocok dengan /bob/123
    // Tetapi tidak akan cocok dengan /bob/12345
});
```
  > [!NOTE] Pencocokan grup regex `()` dengan named parameter tidak didukung.

## Optional Parameters
Anda dapat menentukan named parameter opsional untuk pencocokan dengan membungkus segmen dalam tanda kurung.
```php
Sulis::route('/blog(/@year(/@month(/@day)))', function ($year, $month, $day) {
    // Ini akan cocok dengan URL berikut:
    // /blog/2012/12/10
    // /blog/2012/12
    // /blog/2012
    // /blog
});
```

Parameter opsional apa pun yang tidak cocok akan dioper sebagai `NULL`.

## Wildcards
Pencocokan hanya dilakukan pada segmen URL individual. Jika anda ingin mencocokkan beberapa
segmen, anda dapat menggunakan karakter `*`:
```php
Sulis::route('/blog/*', function () {
    // Ini akan cocok dengan /blog/2000/02/01
});
```

Untuk merutekan semua request ke satu callback, anda dapat melakukan:
```php
Sulis::route('*', function () {
    // Lakukan sesuatu disini
});
```

## Passing
Anda dapat mereturn eksekusi ke rute pencocokan berikutnya
dengan mereturn `true` dari fungsi callback anda.
```php
Sulis::route('/user/@name', function ($name) {
    // Periksa beberapa kondisi
    if ($name !== "Bob") {
        // Lanjutkan ke rute berikutnya
        return true;
    }
});

Sulis::route('/user/*', function () {
    // Ini akan dieksekusi
});
```

## Route Info
Jika Anda ingin memeriksa informasi rute, anda dapat mengambilnya dari object rute
yang akan dioper ke callback anda dengan mereturn `true` sebagai parameter ketiga di
method `route()`. Objek route akan selalu menjadi parameter terakhir yang dioper ke fungsi callback anda.

```php
Sulis::route('/', function ($route) {
    // Array http method milik request saat ini
    $route->methods;

    // Array named milik request saat ini
    $route->params;

    // Regular expression yang cocok milik request saat ini
    $route->regex;

    // Berisi string '*' yang digunakan dalam pola URL
    $route->splat;
}, true);
```

# Extending
Sulis dirancang untuk menjadi framework yang mudah dikustomisasi. Framework ini datang dengan satu set
method dan komponen default, tetapi anda juga boleh memetakan komponen baru ,
maupun menimpa kelas dan method yang ada.

## Mapping Methods
Untuk memetakan method kustom anda sendiri, gunakan method `map()`:
```php
// Cara mapping
Sulis::map('hello', function ($name) {
    echo "hello $name!";
});

// Cara pemanggilan
Sulis::hello('Bob');
```

## Registering Classes
Untuk mendaftarkan kelas anda sendiri, gunakan method `register()`:
```php
// Cara pendaftaran
Sulis::register('user', User::class);

// Cara instansiasi
$user = Sulis::user();
```

Method `register()` juga memungkinkan anda mengoper parameter ke konstruktor kelas anda.
Jadi ketika anda memuat kelas kustom anda, kelas itu akan dipra-inisialisasi.
Anda dapat menentukan parameter konstruktor dengan mengoper array tambahan.
Berikut ini contoh memuat koneksi database:
```php
// Daftarkan kelas dengan parameter konstruktor
Sulis::register('db', PDO::class, ['mysql:host=localhost;dbname=test', 'user', 'pass']);

// Cara instansiasi kelas anda
// Ini akan membuat objek dengan parameter yang anda tentukan tadi
//
//     new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
//
$db = Sulis::db();
```

Jika anda mengoper parameter callback tambahan, ia akan segera dieksekusi
setelah konstruksi kelas dilakukan. Ini memungkinkan anda untuk melakukan prosedur pengaturan apa pun untuk
objek baru. Fungsi callback ini menerima satu parameter, sebuah instance dari objek baru.
```php
//  Callback akan dioper ke objek yang dibuat
Sulis::register('db', PDO::class, ['mysql:host=localhost;dbname=test', 'user', 'pass'], function ($db) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
});
```

Secara default, setiap kali anda memuat kelas anda, anda akan mendapatkan shared instance (singleton).
Untuk mendapatkan instance baru dari sebuah kelas, cukup berikan `false` sebagai parameter:
```php
// Mengambil instance singleton
$shared = Sulis::db();

// Mengambil instance baru
$new = Sulis::db(false);
```

Perlu diingat bahwa method yang dipetakan lebih diprioritaskan daripada kelas yang terdaftar. Jika anda
mendeklarasikan keduanya menggunakan nama yang sama, hanya method yang dipetakan yang akan dieksekusi.

# Overriding
Anda juga dapat mengganti fungsionalitas default sulis dengan kebutuhan anda sendiri, tanpa harus mengubah kode apapun.

Misalnya, ketika sulis tidak dapat mencocokkan URL dengan rute, sulis memanggil method `notFound()`
yang mengirimkan respon generik `HTTP 404`. Anda dapat mengganti perilaku ini dengan menggunakan method `map()`:
```php
Sulis::map('notFound', function () {
    // Tampilkan halaman 404 kustom
    include 'custom-errors/404.html';
});
```

Juga memungkinkan untuk mengganti komponen inti dari framework ini.
Misalnya, anda dapat mengganti kelas `Router` default dengan kelas kustom anda sendiri:
```php
// Mendaftarkan kelas kustom anda
Sulis::register('router', MyRouter::class);

// Ketika sulis memuat instance Router, kelas kustom anda yang akan dimuat
$myrouter = Sulis::router();
```
  > [!NOTE] Metode inti seperti `map()` dan `register()` tidak dapat diganti.

# Filtering
Sulis memungkinkan anda untuk memfilter method sebelum dan sesudah dieksekusi.
Anda dapat memfilter method default bawaan framework maupun method kustom yang telah anda petakan.

Fungsi filter terlihat seperti ini:
```php
function (&$params, &$output) {
    // Kode filter anda disini
}
```

Dengan menggunakan variabel yang dioper (pada contoh diatas variabel `&$params` dan `&$output`),
anda dapat memanipulasi parameter input dan/atau output.

Anda dapat menjalankan filter sebelum suatu method dieksekusi:
```php
Sulis::before('start', function (&$params, &$output) {
    // Lakukan sesuatu disini
});
```

Anda juga dapat menjalankan filter setelahnya:
```php
Sulis::after('start', function (&$params, &$output) {
    // Lakukan sesuatu disini
});
```

Anda dapat menambahkan filter sebanyak yang anda inginkan, ke method manapun.
Mereka akan dieksekusi sesuai urutan yang anda tentukan.

Berikut adalah contoh proses filtering:

```php
// Petakan method kustom
Sulis::map('hello', function ($name) {
    return "Hello, $name!";
});

// Tambahkan filter 'before'
Sulis::before('hello', function (&$params, &$output) {
    // Memanipulasi parameter
    $params[0] = 'Fred';
});

// Tambahkan filter 'after'
Sulis::after('hello', function (&$params, &$output) {
    // Memanipulasi parameter
    $output .= " Have a nice day!";
});

// Panggil method kustomya
echo Sulis::hello('Bob');
```

Hasil yang akan anda dapatkan:
```
Hello Fred! Have a nice day!
```

Jika anda telah mendeklarasikan beberapa filter,
anda dapat memutuskan rantai eksekusinya dengan mereturn `false` di salah satu fungsi filter:

```php
Sulis::before('start', function (&$params, &$output) {
    echo 'one';
});

Sulis::before('start', function (&$params, &$output) {
    echo 'two';

    // Ini akan memutuskan rantai eksekusinya
    return false;
});

// Ini tidak akan dieksekusi
Sulis::before('start', function (&$params, &$output) {
    echo 'three';
});
```
  > [!NOTE] Method inti seperti `map()` dan `register()` tidak bisa difilter.

# Variables
Sulis memungkinkan anda untuk menyimpan variabel secara global sehingga dapat digunakan di mana saja di aplikasi anda.
```php
// Contoh menyimpan variabel
Sulis::set('id', 123);

// Contoh mengakses variabel
$id = Sulis::get('id');
```
Untuk memeriksa apakah variabel ada atau tidak, gunakan `has()`:
```php
if (Sulis::has('id')) {
     // Variabel 'id' ada!
}
```

Untuk menghapusnya, gunakan method `clear()`:
```php
// Hapus variabel 'id'
Sulis::clear('id');

// Hapus seluruh variabel
Sulis::clear();
```
  > [!TIP] Sulis juga menggunakan variabel untuk keperluan konfigurasi framework.

```php
Sulis::set('sulis.log_errors', true);
```

## Errors & Exceptions
Semua error dan exception ditangkap oleh sulis dan dioper ke method `error`.
Perilaku defaultnya adalah mengirim respon `HTTP 500 Internal Server Error` generik
dengan beberapa informasi kesalahan tambahan.

Anda dapat mengganti perilaku ini untuk kebutuhan anda:
```php
Sulis::map('error', function (Throwable $e) {
    // Tangani error
    echo $e->getTraceAsString();
});
```

Secara default, error tidak di-log ke webserver. Anda dapat mengaktifkan ini dengan mengubah konfigurasi:

```php
Sulis::set('sulis.log_errors', true);
```

## Not Found
Ketika URL tidak ditemukan, sulis akan memanggil method `notFound()`.
Perilaku defaultnya adalah mengirim respon `HTTP 404 Not Found` dengan pesan sederhana.

Anda juga dapat mengganti perilaku ini untuk kebutuhan anda:
```php
Sulis::map('notFound', function () {
    // Tangani not found
});
```

# Redirect
Anda dapat me-redirect request saat ini dengan menggunakan method `redirect()`:
```php
Sulis::redirect('/new/location');
```

Secara default, sulis mengirimkan kode status `HTTP 303`. Anda dapat mengubahnya jika diperlukan:
```php
Sulis::redirect('/new/location', 401);
```

# Request
Sulis membungkus HTTP request menjadi satu objek, yang dapat diakses dengan memanggil:
```php
$request = Sulis::request();
```

Objek tersebut berisi properti:
```
url - URL saat ini
base - Subdirektori induk URL
method - Request method (GET, POST, PUT, DELETE)
referrer - Referrer URL
ip - IP klien
ajax - Indikator apakah request tersebut merupakan request AJAX
scheme - Protokol request (http, https)
user_agent - User agent
type - Content type
length - Content length
query - Query string
data - Post data atau JSON data
cookies - Cookie data
files - File upload
secure - Indikator secure connection (https)
accept - HTTP Accept
proxy_ip - Proxy IP klien
host - Hostname
```

Anda dapat mengakses properti `query`, `data`, `cookies`, dan `files` sebagai array atau objek.

Jadi, untuk mengambil parameter query string, anda dapat melakukan:
```php
$id = Sulis::request()->query['id'];
```

Atau:
```php
$id = Sulis::request()->query->id;
```

## RAW Request Body
Untuk mengambil raw request body, misalnya saat menangani request PUT, anda dapat melakukan:
```php
$body = Sulis::request()->getBody();
```

## JSON Input
Jika anda mengirim request bertipe `application/json` dengan data `{"id": 123}`,
request tersebut akan tersedia di properti `data`:
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
```
sulis.base_url - Override the base url of the request. (default: null)
sulis.case_sensitive - Case sensitive matching for URLs. (default: false)
sulis.handle_errors - Allow Sulis to handle all errors internally. (default: true)
sulis.log_errors - Log errors to the web server's error log file. (default: false)
sulis.views.path - Directory containing view template files. (default: ./views)
sulis.views.cache - View template cache directory. (default: ./cache)
```

# Framework Method
Sulis is designed to be easy to use and understand. The following is the complete
set of methods for the framework. It consists of core methods, which are regular
static methods, and extensible methods, which are mapped methods that can be filtered
or overridden.

## Core Method
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

## Extensible Method
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
