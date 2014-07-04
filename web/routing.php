<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Add user data to twig globals for displaying logged in user
$app->before(function() use ($app) {
    $token = $app['security']->getToken();
    if (isset($token)) {
        $user = $token->getUser();
        $app['twig']->addGlobal('user', [
            'username' => $user->getUsername()
        ]);
    }
});

// Redirect root URL to /talks
$app->get('/', function(Request $request) use ($app) {
    return $app->redirect('/talks');
});

// Provide a login route
$app->get('/login', function(Request $request) use ($app) {
    return $app['twig']->render("login.twig", [
        'error' => $app['security.last_error']($request),
        'last_username' => $app['session']->get('_security.last_username'),
    ]);
});

// -- Talks --------------------------------------------------------------------

$app->get('/talks', "talks.controller:listAction")
    ->bind('talks');

$app->get('/talks/{id}', "talks.controller:showAction")
    ->assert('id', '[0-9a-f]{24}')
    ->bind('talk');

$app->get('/talks/{id}/rate/{score}', "talks.controller:rateJsonAction")
    ->assert('id', '[0-9a-f]{24}')
    ->assert('score', '[1-5]')
    ->bind('rate_talk');

$app->get('/talks/{id}/unrate', "talks.controller:unrateJsonAction")
    ->assert('id', '[0-9a-f]{24}')
    ->bind('unrate_talk');

// -- Stats --------------------------------------------------------------------

$app->get('/stats', "stats.controller:indexAction")
    ->bind('stats');
