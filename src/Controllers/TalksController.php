<?php

namespace WebCampZg\VotingWeb\Controllers;

use MongoDB;
use MongoID;

use Silex\Application;

class TalksController
{
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_HIDDEN = 'hidden';

    private static $statuses = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_HIDDEN,
    ];

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
        $talk = $this->getTalk($id);
        if ($talk === null) {
            $app->abort(404, "Talk not found: $id");
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

        $talk = $this->getTalk($id);
        if ($talk === null) {
            return $app->json("Talk not found: $id", 404);
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
        $talk = $this->getTalk($id);
        if ($talk === null) {
            return $app->json("Talk not found: $id", 404);
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

    public function changeStatusJsonAction(Application $app, $id, $status)
    {
        if (!$app['security']->isGranted('ROLE_ADMIN')) {
            return $app->json("Only admins may do this.", 403);
        }

        if (!in_array($status, self::$statuses)) {
            return $app->json("Invalid status: $status", 400);
        }

        $talk = $this->getTalk($id);
        if ($talk === null) {
            return $app->json("Talk not found: $id", 404);
        }

        if ($talk['status'] != $status) {
            $talk['status'] = $status;
            $this->db->talks->save($talk);
        }

        return $app->json([
            'talk_id' => $id,
            'status' => $status
        ]);
    }

    public function acceptedTalksJsonAction(Application $app)
    {
        $talks = array();
        $query = ['status' => 'accepted'];
        foreach ($this->db->talks->find($query) as $talk) {
            $speaker = $this->db->speakers->findOne([
                '_id' => $talk['speaker_id']
            ]);

            unset($speaker['email']);
            unset($speaker['experience']);

            $talk['speaker'] = $speaker;
            unset($talk['speaker_id']);
            unset($talk['scores']);

            $talks[] = $talk;
        }

        return $app->json($talks);
    }

    /**
     * Returns a talk by ID or null if not found.
     */
    private function getTalk($id)
    {
        $talkID = new MongoID($id);
        return $this->db->talks->findOne([
            "_id" => $talkID
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
