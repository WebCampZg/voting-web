<?php

namespace WebCampZg\VotingWeb\Controllers;

use MongoDB;
use MongoID;

use Silex\Application;

class TalksController
{
    protected $db;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
    }

    /**
     * Displays a list of talks.
     */
    public function listAction(Application $app)
    {
        $talksIt = $this->db->talks->find()->sort(['submitted' => 1]);
        $speakersIt = $this->db->speakers->find();

        $speakers = [];
        foreach ($speakersIt as $speaker) {
            $speakerID = (string) $speaker['_id'];
            $speakers[$speakerID] = $speaker;
        }

        $talks = [];
        foreach ($talksIt as $talk) {
            $speakerID = (string) $talk['speaker_id'];
            $talk['speaker'] = $speakers[$speakerID];
            $talk['submitted'] = $talk['submitted']->sec;
            $talk['avg_score'] = $this->getAverageScore($talk);
            $talks[] = $talk;
        }

        return $app['twig']->render('talks.twig', [
            'talks' => $talks,
        ]);
    }

    /**
     * Displays a single talk.
     */
    public function showAction(Application $app, $id)
    {
        $talkID = new MongoID($id);
        $talk = $this->db->talks->findOne(["_id" => $talkID]);
        if ($talk === null) {
            $app->abort(404, "Talk not found");
        }

        // Find next and previous talks
        $next = $this->db->talks
            ->find(['submitted' => ['$gt' => $talk['submitted']]])
            ->sort(['submitted' => 1])
            ->limit(1)->getNext();

        $prev = $this->db->talks
            ->find(['submitted' => ['$lt' => $talk['submitted']]])
            ->sort(['submitted' => -1])
            ->limit(1)->getNext();

        // Get a list of voting users to display non-voting users
        $users = $this->db->users
            ->distinct('username', ["roles" => "ROLE_VOTER"]);

        sort($users);

        $speakerID = new MongoID($talk['speaker_id']);
        $speaker = $this->db->speakers->findOne(["_id" => $speakerID]);
        if ($speaker === null) {
            $app->abort(404, "Speaker not found");
        }

        return $app['twig']->render('talk.twig', [
            'talk' => $talk,
            'speaker' => $speaker,
            'next' => $next,
            'prev' => $prev,
            'votes' => $this->getScoreCount($talk),
            'avg_score' => $this->getAverageScore($talk),
            'users' => $users,
        ]);
    }

    /**
     * Sets a talk rating for the currently logged in user.
     */
    public function rateJsonAction(Application $app, $id, $score)
    {
        $score = (integer) $score;

        $talkID = new MongoID($id);
        $talk = $this->db->talks->findOne(["_id" => $talkID]);
        if ($talk === null) {
            $app->abort(404, "Talk not found: $id");
        }

        $username = $app->user()->getUsername();
        $talk['scores'][$username] = $score;
        $this->db->talks->save($talk);

        return $app->json([
            'talk_id' => $id,
            'user' => $username,
            'score' => $score,
            'avg_score' => $this->getAverageScore($talk),
            'votes' => $this->getScoreCount($talk),
        ]);
    }

    /**
     * Removes a talk rating for the currently logged in user.
     */
    public function unrateJsonAction(Application $app, $id)
    {
        $talkID = new MongoID($id);
        $talk = $this->db->talks->findOne(["_id" => $talkID]);
        if ($talk === null) {
            $app->abort(404, "Talk not found: $id");
        }

        $username = $app->user()->getUsername();
        if (isset($talk['scores'][$username])) {
            unset($talk['scores'][$username]);
            $this->db->talks->save($talk);
        }

        return $app->json([
            'talk_id' => $id,
            'user' => $username,
            'avg_score' => $this->getAverageScore($talk),
            'votes' => $this->getScoreCount($talk),
        ]);
    }

    private function getScoreCount($talk)
    {
        if (empty($talk['scores'])) {
            return null;
        }

        return count($talk['scores']);
    }

    private function getAverageScore($talk)
    {
        if (empty($talk['scores'])) {
            return null;
        }

        return array_sum($talk['scores']) / count($talk['scores']);
    }
}
