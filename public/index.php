<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

if (!defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

/*
|--------------------------------------------------------------------------
| Auto-redirect to Installer if not configured
|--------------------------------------------------------------------------
*/
if (file_exists(__DIR__ . '/install.php') && !file_exists(__DIR__ . '/../.env')) {
    header('Location: install.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fix /public prefix issue on cPanel shared hosting
|--------------------------------------------------------------------------
| When document root is project root (not /public), URLs come in as
| /public/api instead of /api. Strip the /public prefix so Laravel
| can match routes correctly.
|--------------------------------------------------------------------------
*/
if (strpos($_SERVER['REQUEST_URI'], '/public') === 0) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 7) ?: '/';
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
