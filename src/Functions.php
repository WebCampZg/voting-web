<?php

function abspath($path)
{
    $root = realpath(__DIR__ . '/../');
    $path = ltrim($path, '/\\');
    return "$root/$path";
}
