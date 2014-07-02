<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// -- Setup --------------------------------------------------------------------

$app = new Silex\Application();
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__ . '/templates'
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
    return new MongoClient($config['mongo_url']);
};

if ($config['debug']) {
    $app['debug'] = true;
}

// -- Routing ------------------------------------------------------------------

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

// Display a list of talks
$app->get('/talks', function() use ($app) {
    $db = $app['mongo']->webcamp;

    $talksIt = $db->talks->find()->sort(['submitted' => 1]);
    $speakersIt = $db->speakers->find();

    $speakers = [];
    foreach ($speakersIt as $speaker) {
        $speakerID = (string) $speaker['_id'];
        $speakers[$speakerID] = $speaker;
    }

    $talks = [];
    foreach ($talksIt as $talk) {
        $speakerID = (string) $talk['speaker_id'];
        $talk['speaker'] = $speakers[$speakerID];
        $talk['submitted'] = date('d.m.Y H:i', $talk['submitted']->sec);
        $talk['avg_score'] = !empty($talk['scores']) ? array_sum($talk['scores']) / count($talk['scores']) : null;
        $talks[] = $talk;
    }

    return $app['twig']->render('talks.twig', [
        'talks' => $talks,
        'speakers' => $speakers,
    ]);
});

// Display a single talk
$app->get('/talks/{id}', function($id) use ($app) {
    $db = $app['mongo']->webcamp;

    $talkID = new MongoID($id);
    $talk = $db->talks->findOne(["_id" => $talkID]);
    if ($talk === null) {
        return $app->abort(404, "Talk not found");
    }

    // Find next and previous talks
    $next = $db->talks
        ->find(['submitted' => ['$gt' => $talk['submitted']]])
        ->sort(['submitted' => 1])
        ->limit(1)->getNext();

    $prev = $db->talks
        ->find(['submitted' => ['$lt' => $talk['submitted']]])
        ->sort(['submitted' => -1])
        ->limit(1)->getNext();

    $speakerID = new MongoID($talk['speaker_id']);
    $speaker = $db->speakers->findOne(["_id" => $speakerID]);
    if ($speaker === null) {
        return $app->abort(404, "Speaker not found");
    }

    $votes = count($talk['scores']);
    $avg = $votes > 0 ? round(array_sum($talk['scores']) / $votes, 3) : null;

    return $app['twig']->render('talk.twig', [
        'talk' => $talk,
        'speaker' => $speaker,
        'next' => $next,
        'prev' => $prev,
        'votes' => $votes,
        'avg_score' => $avg,
    ]);
})
->assert('id', '[0-9a-f]{24}')
->bind('talk');

// Rate a talk
$app->get('/talks/{id}/rate/{score}', function($id, $score) use ($app) {
    $db = $app['mongo']->webcamp;

    $talkID = new MongoID($id);
    $talk = $db->talks->findOne(["_id" => $talkID]);
    if ($talk === null) {
        return $app->abort(404, "Talk not found.");
    }

    $username = $app['security']->getToken()->getUser()->getUsername();
    $talk['scores'][$username] = (integer) $score;
    $db->talks->save($talk);

    $votes = count($talk['scores']);
    $avg = $votes > 0 ? round($score / $votes, 3) : null;

    return $app->json([
        'talk_id' => $id,
        'user' => $username,
        'score' => $score,
        'avg_score' => $avg,
        'votes' => $votes
    ]);
})
->assert('id', '[0-9a-f]{24}')
->assert('score', '[1-5]')
->bind('rate_talk');

// Unrate a talk
$app->get('/talks/{id}/unrate', function($id) use ($app) {
    $db = $app['mongo']->webcamp;

    $talkID = new MongoID($id);
    $talk = $db->talks->findOne(["_id" => $talkID]);
    if ($talk === null) {
        return $app->abort(404);
    }

    $username = $app['security']->getToken()->getUser()->getUsername();
    if (isset($talk['scores'][$username])) {
        unset($talk['scores'][$username]);
    }
    $db->talks->save($talk);

    $votes = count($talk['scores']);
    $avg = $votes > 0 ? round(array_sum($talk['scores']) / $votes, 3) : null;

    return $app->json([
        'talk_id' => $id,
        'user' => $username,
        'avg_score' => $avg,
        'votes' => $votes
    ]);
})
->assert('id', '[0-9a-f]{24}')
->assert('score', '[0-5]')
->bind('unrate_talk');

$app->run();
