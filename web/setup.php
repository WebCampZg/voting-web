<?php

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => abspath('templates')
]);

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addExtension(new Twig_Extension_Debug($app));
    return $twig;
}));

$app->register(new Silex\Provider\SecurityServiceProvider(), [
    'security.firewalls' => [
        'login' => array(
            'pattern' => '^/login$',
        ),
        'secured' => [
            'pattern' => '^.*$',
            'form' => [
                'login_path' => '/login',
                'check_path' => '/login_check'
            ],
            'users' => $config['users'],
            'logout' => [
                'logout_path' => '/logout'
            ],
        ]
    ]
]);

$app['mongo'] = function() use ($config) {
    // TODO: Deprecated, remove
    return new MongoClient($config['mongo_url']);
};

$app['db'] = function() use ($config) {
    $client = new MongoClient($config['mongo_url']);
    return $client->webcamp;
};

if ($config['debug']) {
    $app['debug'] = true;
}

// -- Controllers --------------------------------------------------------------

use WebCampZg\VotingWeb\Controllers\StatsController;
use WebCampZg\VotingWeb\Controllers\TalksController;

$app['talks.controller'] = function() use ($app) {
    return new TalksController($app['db']);
};

$app['stats.controller'] = function() use ($app) {
    return new StatsController($app['db']);
};
