<?php

/**
 * Proxy to public/index.php
 * For cPanel where document root is project root, not /public
 */

// Installer check
if (file_exists(__DIR__ . '/public/install.php') && !file_exists(__DIR__ . '/.env')) {
    header('Location: public/install.php');
    exit;
}

$publicPath = __DIR__ . '/public';

// Fix all server variables so Laravel resolves routes correctly
$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $publicPath;

// Critical: Fix REQUEST_URI — remove /index.php prefix if Apache added it
if (strpos($_SERVER['REQUEST_URI'], '/index.php') === 0) {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen('/index.php')) ?: '/';
}

chdir($publicPath);

// Use require (not require_once) to ensure Laravel boots
require $publicPath . '/index.php';
