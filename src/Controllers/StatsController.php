<?php

namespace WebCampZg\VotingWeb\Controllers;

use MongoDB;
use MongoID;

use Silex\Application;

class StatsController
{
    protected $db;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
    }

    public function indexAction(Application $app)
    {
        // Array holding all scores given by each group
        $scoresByUser = [];

        // How many times each score was given
        $scoreCounts = [];

        // How many votes were cast by each group
        $votesCast = [];

        $talks = $this->db->talks->find();

        foreach($talks as $talk) {
            foreach ($talk['scores'] as $user => $score) {
                $scoresByUser[$user][] = $score;

                if (!isset($scoreCounts[$user][$score])) {
                    $scoreCounts[$user][$score] = 0;
                }
                $scoreCounts[$user][$score]++;

                if (!isset($votesCast[$user])) {
                    $votesCast[$user] = 0;
                }

                $votesCast[$user]++;
            }
        }

        arsort($votesCast);

        foreach ($scoreCounts as $user => &$scores) {
            ksort($scores);
            $scores = array_values($scores);
        }
        unset($scores);

        return $app['twig']->render('stats.twig', [
            'score_counts' => $scoreCounts,

            'votes_cast_categories' => array_keys($votesCast),
            'votes_cast_data' => array_values($votesCast)
        ]);
    }
}