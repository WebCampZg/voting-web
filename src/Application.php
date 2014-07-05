<?php

namespace WebCampZg\VotingWeb;

use Silex;

class Application extends Silex\Application
{
    use Silex\Application\SecurityTrait;
    use Silex\Application\TwigTrait;
    use Silex\Application\UrlGeneratorTrait;
}
