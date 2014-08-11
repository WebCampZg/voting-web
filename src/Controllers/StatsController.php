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
        // Fetch data
        $talks = $this->db->talks->find();
        $users = $this->db->users
            ->distinct('username', ['roles' => 'ROLE_VOTER']);

        sort($users);

        // All scores given per group
        $scoresByUser = [];
        foreach ($users as $user) {
            $scoreCounts[$user] = [];
        }

        // How many times each score was given per group
        $scoreCounts = [];
        foreach ($users as $user) {
            $scoreCounts[$user] = [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
            ];
        }

        // How many votes were cast per group
        $voteCounts = [];
        foreach ($users as $user) {
            $voteCounts[$user] = 0;
        }

        // Scores for all talks
        $talksScores = [];

        // Process data
        foreach($talks as $talk) {
            foreach ($talk['scores'] as $user => $score) {
                $scoresByUser[$user][] = $score;
                $scoreCounts[$user][$score]++;
                $voteCounts[$user]++;
                $talksScores[] = $talk['scores'];
            }
        }

        // Heatmap data (user disagreement)
        $sums = [];
        foreach ($users as $key1 => $user1) {
            foreach ($users as $key2 => $user2) {
                foreach($talksScores as $scores) {
                    if (!isset($sums[$key1][$key2])) {
                        $sums[$key1][$key2] = 0;
                    }
                    $sums[$key1][$key2] += $this->getScoreDiff($scores, $user1, $user2);
                }
            }
        }

        $heatmapData = [];
        foreach ($sums as $key1 => $sums1) {
            foreach ($sums1 as $key2 => $value) {
                $heatmapData[] = [$key1, $key2, $value];
            }
        }

        // Average score by user
        $averageScores = [];
        foreach ($scoresByUser as $user => $scores) {
            if (empty($scores)) {
                $averageScores[$user] = null;
            }
            $averageScores[$user] = array_sum($scores) / count($scores);
        }
        ksort($averageScores);

        return $app['twig']->render('stats.twig', [
            'score_counts' => $scoreCounts,
            'scores_by_user' => $scoresByUser,
            'vote_counts' => $voteCounts,
            'average_scores' => $averageScores,
            'heatmap_data' => $heatmapData,
            'users' => $users
        ]);
    }

    private function getScoreDiff($scores, $user1, $user2)
    {
        if (!isset($scores[$user1]) || !isset($scores[$user2])) {
            return 0;
        }

        return abs($scores[$user1] - $scores[$user2]);
    }
}
