<?php

use Symfony\Component\HttpFoundation\Request;

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

// -- New Relic ----------------------------------------------------------------

if (extension_loaded('newrelic')) {
    newrelic_set_appname("WebCamp Vote");

    // Route naming
    $app->before(function (Request $request) use ($app) {
        newrelic_name_transaction($request->get("_route"));
    });

    // Log uncaught errors
    $app->error(function (Exception $ex) use ($app) {
        newrelic_notice_error($ex->getMessage(), $ex);
    });
}

// -- Templating ---------------------------------------------------------------

$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => abspath('templates')
]);

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addExtension(new Twig_Extension_Debug($app));
    $twig->addFilter(new Twig_SimpleFilter('vote_color', function ($vote) {
        return voteColor($vote);
    }));
    return $twig;
}));

// -- Security -----------------------------------------------------------------

use WebCampZg\VotingWeb\UserProvider;

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
            'users' => $app->share(function() use ($app) {
                return new UserProvider($app['db']);
            }),
            'logout' => [
                'logout_path' => '/logout'
            ],
        ]
    ]
]);

$app['db'] = $app->share(function() use ($config) {
    $client = new MongoClient($config['mongo_url']);
    return $client->webcamp;
});

if ($config['debug']) {
    $app['debug'] = true;
}

// -- Controllers --------------------------------------------------------------

use WebCampZg\VotingWeb\Controllers\StatsController;
use WebCampZg\VotingWeb\Controllers\TalksController;

$app['talks.controller'] = $app->share(function() use ($app) {
    return new TalksController($app['db']);
});

$app['stats.controller'] = $app->share(function() use ($app) {
    return new StatsController($app['db']);
});
