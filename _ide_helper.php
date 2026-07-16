<?php
/**
 * CodeIgniter 4 IDE Helper
 *
 * File ini HANYA digunakan oleh IDE (Intelephense, PHPStorm, dsb.) untuk
 * mengenali function signatures dari framework CI4. File ini TIDAK pernah
 * di-include/require di runtime.
 *
 * Menghilangkan false-positive "Not enough arguments" pada fungsi global
 * CI4 yang definisinya dibungkus `if (!function_exists(...))`.
 *
 * @see vendor/codeigniter4/framework/system/Common.php
 */

// Prevent actual execution — this file is purely for static analysis.
if (false) {

    /**
     * Convenience method that works with the current global $request and
     * $router instances to redirect using named/reverse-routed routes.
     *
     * If more control is needed, you must use $response->redirect explicitly.
     *
     * @param string|null $route Route name or URI (optional)
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    function redirect(?string $route = null): \CodeIgniter\HTTP\RedirectResponse {}

    /**
     * A convenience method for loading a View and returning its rendered output.
     *
     * @param string               $name    View filename
     * @param array<string, mixed> $data    Data to pass to the view
     * @param array<string, mixed> $options Options for the view renderer
     * @return string
     */
    function view(string $name, array $data = [], array $options = []): string {}

    /**
     * Return the session instance, optionally retrieving a single key.
     *
     * @param string|null $val Session key to retrieve (null returns Session object)
     * @return mixed|\CodeIgniter\Session\Session
     */
    function session(?string $val = null) {}

    /**
     * Return the base URL with an optional relative path appended.
     *
     * @param string|array<int, string> $relativePath URI segments
     * @param string|null               $scheme       URI scheme
     * @return string
     */
    function base_url($relativePath = '', ?string $scheme = null): string {}

    /**
     * Return the site URL with an optional relative path appended.
     *
     * @param string|array<int, string> $relativePath URI segments
     * @param string|null               $scheme       URI scheme
     * @param \Config\App|null          $config       App config
     * @return string
     */
    function site_url($relativePath = '', ?string $scheme = null, ?\Config\App $config = null): string {}

    /**
     * Loads helper file(s) into memory.
     *
     * @param array<int, string>|string $filenames Helper name or array of names
     * @return void
     */
    function helper($filenames): void {}

    /**
     * Get a service instance.
     *
     * @param string $name   Service name
     * @param mixed  ...$params Additional parameters
     * @return object|null
     */
    function service(string $name, ...$params): ?object {}

    /**
     * Get a single (non-shared) service instance.
     *
     * @param string $name   Service name
     * @param mixed  ...$params Additional parameters
     * @return object|null
     */
    function single_service(string $name, ...$params): ?object {}

    /**
     * Get the IncomingRequest or CLIRequest instance.
     *
     * @return \CodeIgniter\HTTP\CLIRequest|\CodeIgniter\HTTP\IncomingRequest
     */
    function request() {}

    /**
     * Get the Response instance.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    function response(): \CodeIgniter\HTTP\ResponseInterface {}

    /**
     * Get a configuration class instance.
     *
     * @param string $name      Config class name
     * @param bool   $getShared Whether to return a shared instance
     * @return object|null
     */
    function config(string $name, bool $getShared = true) {}

    /**
     * Get the current URL.
     *
     * @param bool                                   $returnObject Whether to return URI object
     * @param \CodeIgniter\HTTP\IncomingRequest|null $request      Request instance
     * @return string|\CodeIgniter\HTTP\URI
     */
    function current_url(bool $returnObject = false, ?\CodeIgniter\HTTP\IncomingRequest $request = null) {}

    /**
     * Get the previous URL.
     *
     * @param bool $returnObject Whether to return URI object
     * @return string|\CodeIgniter\HTTP\URI
     */
    function previous_url(bool $returnObject = false) {}

    /**
     * Get the URI string.
     *
     * @return string
     */
    function uri_string(): string {}

    /**
     * Get a model instance.
     *
     * @param string $name      Model class name
     * @param bool   $getShared Whether to return a shared instance
     * @param \CodeIgniter\Database\ConnectionInterface|null $conn Database connection
     * @return object|null
     */
    function model(string $name, bool $getShared = true, ?\CodeIgniter\Database\ConnectionInterface &$conn = null) {}

    /**
     * Get an environment variable.
     *
     * @param string $key     Variable name
     * @param mixed  $default Default value
     * @return mixed
     */
    function env(string $key, $default = null) {}

    /**
     * Perform context-sensitive escaping.
     *
     * @param array<string, string>|string $data     Data to escape
     * @param string                       $context  Escaping context
     * @param string|null                  $encoding Character encoding
     * @return array<string, string>|string
     */
    function esc($data, string $context = 'html', ?string $encoding = null) {}

    /**
     * Get a language string.
     *
     * @param string               $line   Language line key
     * @param array<int, string>   $args   Arguments for placeholders
     * @param string|null          $locale Locale
     * @return string
     */
    function lang(string $line, array $args = [], ?string $locale = null) {}

    /**
     * Log a message.
     *
     * @param string               $level   Log level
     * @param string               $message Message
     * @param array<string, mixed> $context Context
     * @return void
     */
    function log_message(string $level, string $message, array $context = []): void {}

    /**
     * Retrieve old input value (from redirect()->withInput()).
     *
     * @param string      $key     Input key
     * @param mixed       $default Default value
     * @param string|bool $escape  Escape context
     * @return mixed
     */
    function old(string $key, $default = null, $escape = 'html') {}

    /**
     * Get a named/reverse-routed URL.
     *
     * @param string $method Controller method
     * @param mixed  ...$params Route parameters
     * @return false|string
     */
    function route_to(string $method, ...$params) {}

    /**
     * A timer utility.
     *
     * @param string|null   $name     Timer name
     * @param callable|null $callable Callable to time
     * @return mixed|\CodeIgniter\Debug\Timer
     */
    function timer(?string $name = null, ?callable $callable = null) {}

    /**
     * Render a view cell.
     *
     * @param string       $library    Cell class::method
     * @param array|null   $params     Parameters
     * @param int          $ttl        Cache TTL
     * @param string|null  $cacheName  Cache key
     * @return string
     */
    function view_cell(string $library, $params = null, int $ttl = 0, ?string $cacheName = null): string {}

    /**
     * Check if running in CLI mode.
     *
     * @return bool
     */
    function is_cli(): bool {}

    /**
     * Get the CSRF token value.
     *
     * @return string
     */
    function csrf_token(): string {}

    /**
     * Get the CSRF hash value.
     *
     * @return string
     */
    function csrf_hash(): string {}

    /**
     * Get the CSRF header name.
     *
     * @return string
     */
    function csrf_header(): string {}

    /**
     * Generate a hidden CSRF input field.
     *
     * @param string|null $id Element ID
     * @return string
     */
    function csrf_field(?string $id = null): string {}

    /**
     * Generate a CSRF meta tag.
     *
     * @param string|null $id Element ID
     * @return string
     */
    function csrf_meta(?string $id = null): string {}

    /**
     * Database connection helper.
     *
     * @param \CodeIgniter\Database\BaseConnection|array<string, mixed>|string|null $db Database group or config
     * @param bool                                                                  $getShared Shared instance
     * @return \CodeIgniter\Database\BaseConnection
     */
    function db_connect($db = null, bool $getShared = true) {}

    /**
     * Cache helper — get, set, or return cache instance.
     *
     * @param string|null $key Cache key
     * @return mixed|\CodeIgniter\Cache\CacheInterface
     */
    function cache(?string $key = null) {}

    /**
     * Get the index page setting.
     *
     * @param \Config\App|null $altConfig App config
     * @return string
     */
    function index_page(?\Config\App $altConfig = null): string {}

    /**
     * Clean a system path for display.
     *
     * @param string $path File path
     * @return string
     */
    function clean_path(string $path): string {}

    /**
     * Run a Spark command programmatically.
     *
     * @param string $command Command string
     * @return false|string
     */
    function command(string $command) {}
}
