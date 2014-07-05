<?php

namespace WebCampZg\VotingWeb\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

class WebcampApplication extends Application
{
    private static $logo = <<<ENDLOGO
 __    __     _       ___
/ / /\ \ \___| |__   / __\__ _ _ __ ___  _ __
\ \/  \/ / _ \ '_ \ / /  / _` | '_ ` _ \| '_ \
 \  /\  /  __/ |_) / /__| (_| | | | | | | |_) |
  \/  \/ \___|_.__/\____/\__,_|_| |_| |_| .__/
                                        |_|
ENDLOGO;

    public function getHelp()
    {
        return self::$logo . PHP_EOL . parent::getHelp();
    }
}