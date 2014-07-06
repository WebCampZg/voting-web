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
        $numVotesCast = [];
        foreach ($users as $user) {
            $numVotesCast[$user] = 0;
        }

        $talksScores = [];

        // Process data
        foreach($talks as $talk) {
            foreach ($talk['scores'] as $user => $score) {
                $scoresByUser[$user][] = $score;
                $scoreCounts[$user][$score]++;
                $numVotesCast[$user]++;
                $talksScores[] = $talk['scores'];
            }
        }

        // Gather chart data
        $charts = [
            'vote_counts' => $this->chartVotesCast($users, $numVotesCast),
            'average_vote' => $this->chartAverageVote($users, $scoresByUser),
            'score_distribution' => $this->chartScoreDistribution($users, $scoreCounts),
            'user_agreement' => $this->chartUserAgreement($users, $talksScores),
        ];

        return $app['twig']->render('stats.twig', [
            'charts' => $charts
        ]);
    }

    /**
     * Total number of votes cast per user group.
     */
    private function chartVotesCast($users, $votesCast)
    {
        return [
            "chart" => [
                "type" => "column"
            ],
            "title" => [
                "text" => ""
            ],
            "xAxis" => [
                "categories" => $users
            ],
            "yAxis" => [
                "title" => [
                    "text" => "Number of votes"
                ]
            ],
            "legend" => [
                "enabled" => false
            ],
            "series" => [[
                "name" => "Votes cast",
                "data" => array_values($votesCast)
            ]]
        ];
    }

    /**
     * Average vote cast per user group.
     */
    private function chartAverageVote($users, $scoresByUser)
    {
        $data = [];
        foreach($users as $user) {
            if (!empty($scoresByUser[$user])) {
                $data[] = array_sum($scoresByUser[$user]) / count($scoresByUser[$user]);
            } else {
                $data[] = 0;
            }
        }

        return [
            "chart" => [
                "type" => "column"
            ],
            "title" => [
                "text" => ""
            ],
            "xAxis" => [
                "categories" => $users
            ],
            "yAxis" => [
                "title" => [
                    "text" => "Number of votes"
                ]
            ],
            "legend" => [
                "enabled" => false
            ],
            "series" => [[
                "name" => "Votes cast",
                "data" => $data
            ]]
        ];
    }

    private function chartScoreDistribution($users, $scoreCounts)
    {
        $series = [];
        foreach (range(1, 5) as $score) {
            $series[$score] = [
                "name" => $score,
                "data" => []
            ];
        }

        foreach ($scoreCounts as $user => $scores) {
            foreach ($scores as $score => $count) {
                $series[$score]["data"][] = $count;
            }
        }

        return [
            "chart" => [
                "type" => "column"
            ],
            "title" => [
                "text" => ""
            ],
            "xAxis" => [
                "categories" => $users
            ],
            "yAxis" => [
                "title" => [
                    "text" => "Number of votes"
                ]
            ],
            "series" => array_values($series)
        ];
    }

    private function chartUserAgreement($users, $talksScores)
    {
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

        $data = [];
        foreach ($sums as $key1 => $sums1) {
            foreach ($sums1 as $key2 => $value) {
                $data[] = [$key1, $key2, $value];
            }
        }

        return [
            "chart" => [
                "type" => "heatmap",
                "marginTop" =>  40,
                "marginBottom" =>  40,
            ],
            "title" => [
                "text" => ""
            ],
            "xAxis" => [
                "categories" => $users
            ],
            "yAxis" => [
                "categories" => $users
            ],
            "colorAxis" => [
                "min" => 0,
                "minColor" => '#FFFFFF',
                "maxColor" => '#FF0000'
            ],
            "legend" => [
                "align" => 'right',
                "layout" => 'vertical',
                "margin" => 0,
                "verticalAlign" => 'top',
                "y" => 25,
                "symbolHeight" => 320
            ],
            "series" => [[
                "name" => "User agreement",
                "data" => $data
            ]]
        ];
    }

    private function getScoreDiff($scores, $user1, $user2)
    {
        if (!isset($scores[$user1]) || !isset($scores[$user2])) {
            return 0;
        }

        return abs($scores[$user1] - $scores[$user2]);
    }
}
