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
        $scoresByUser = [];
        $scoreCounts = [];

        $talks = $this->db->talks->find();
        foreach($talks as $talk) {
            foreach ($talk['scores'] as $user => $score) {
                $scoresByUser[$user][] = $score;
                $scoreCounts[$user][$score]++;
            }
        }

        foreach ($scoreCounts as $user => &$scores) {
            ksort($scores);
            $scores = array_values($scores);
        }
        unset($scores);

        // $data = [];
        // foreach ($scoresByUser as $user => $scores) {
        // }

        return $app['twig']->render('stats.twig', [
            'score_counts' => $scoreCounts
        ]);
    }
}