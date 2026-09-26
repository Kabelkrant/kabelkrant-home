<?php
declare(strict_types=1);

/**
 * Enige toegangspunt van de applicatie (front controller).
 *
 *   /                 startpagina (login of bookmarks)
 *   /?p=admin         beheer: bookmarks, icons en vormgeving
 *   /?p=logout        uitloggen
 *   /?api=…           JSON-API voor het beheerscherm
 */

// Alleen deze map (public/) is via de webserver bereikbaar; de code staat erboven.
define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', __DIR__);

require APP_ROOT . '/app/bootstrap.php';

(new App\App(App\Config::fromEnvironment()))->run();
