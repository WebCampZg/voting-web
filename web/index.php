<?php

function abspath($path)
{
    $root = realpath(__DIR__ . '/../');
    $path = ltrim($path, '/\\');
    return "$root/$path";
}

require abspath('vendor/autoload.php');

$app = new Silex\Application();

require abspath('etc/config.php');
require abspath('web/setup.php');
require abspath('web/routing.php');

$app->run();
