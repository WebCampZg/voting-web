<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new WebCampZg\VotingWeb\Application();

require abspath('etc/config.php');
require abspath('web/setup.php');
require abspath('web/routing.php');

$app->run();
