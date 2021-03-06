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

        $speakersIt = $this->db->speakers->find();

        $speakers = [];
        foreach ($speakersIt as $speaker) {
            $speakerID = (string) $speaker['_id'];
            $speakers[$speakerID] = $speaker;
        }

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
        foreach ($users as $user) {
            $averageScores[$user] = 0;
        }

        foreach ($scoresByUser as $user => $scores) {
            if (empty($scores)) {
                $averageScores[$user] = null;
            }
            $averageScores[$user] = round(array_sum($scores) / count($scores), 3);
        }
        ksort($averageScores);

        // Standard deviation in scores per talk
        $stdev = [];
        foreach ($talks as $talk) {
            $speakerID = (string) $talk['speaker_id'];
            $stdev[] = [
                "title" => $talk["title"],
                "speaker" => $speakers[$speakerID],
                "average" => array_sum($talk['scores']) / count($talk['scores']),
                "stdev" => $this->stdev($talk['scores']),
            ];
        }
        usort($stdev, function ($a, $b) {
            if ($a['stdev'] == $b['stdev']) {
                return 0;
            }
            return $a['stdev'] > $b['stdev'] ? -1 : 1;
        });

        return $app['twig']->render('stats.twig', [
            'score_counts' => $scoreCounts,
            'scores_by_user' => $scoresByUser,
            'vote_counts' => $voteCounts,
            'average_scores' => $averageScores,
            'heatmap_data' => $heatmapData,
            'users' => $users,
            'stdev' => $stdev,
        ]);
    }

    private function getScoreDiff($scores, $user1, $user2)
    {
        if (!isset($scores[$user1]) || !isset($scores[$user2])) {
            return 0;
        }

        return abs($scores[$user1] - $scores[$user2]);
    }

    private function stdev(array $scores)
    {
        if (empty($scores)) {
            return null;
        }
        $count = count($scores);

        $mean = array_sum($scores) / $count;

        $sum = 0;
        foreach ($scores as $score) {
            $sum += pow($score - $mean, 2);
        }

        return sqrt($sum / $count);
    }
}
