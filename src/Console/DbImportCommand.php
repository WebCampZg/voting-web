<?php

namespace WebCampZg\VotingWeb\Console;

use WebCampZg\VotingWeb\Application;
use WebCampZg\VotingWeb\Controllers\TalksController;

use PDO;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\User\User;

class DbImportCommand extends Command
{
    private $app;
    private $db;
    private $output;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->db = $app['db'];

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('import')
            ->setDescription('Import data from a postgres database')
            ->addOption(
               'host',
               'H',
               InputOption::VALUE_REQUIRED,
               'Database host name'
            )
            ->addOption(
               'port',
               'P',
               InputOption::VALUE_REQUIRED,
               'Database port',
               5432
            )
            ->addOption(
               'username',
               'u',
               InputOption::VALUE_REQUIRED,
               'Database username'
            )
            ->addOption(
               'password',
               'p',
               InputOption::VALUE_REQUIRED,
               'Database password'
            )
            ->addArgument(
               'dbname',
               InputArgument::REQUIRED,
               'Name of database to import'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $username = $input->getOption('username');
        $password = $input->getOption('password');
        $dbname = $input->getArgument('dbname');

        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Sync speakers
        $speakers = $this->speakerGenerator($pdo);
        foreach ($speakers as $speaker) {
            $this->saveSpeaker($speaker);
        }

        // Sync talks
        $talks = $this->talkGenerator($pdo);
        foreach ($talks as $talk) {
            $this->saveTalk($talk);
        }
    }

    private function speakerGenerator($pdo)
    {
        $sql = <<<SQL
            SELECT
                u.first_name || ' ' || u.last_name AS name,
                u.twitter                          AS twitter,
                u.github                           AS github,
                a.about                            AS short_bio,
                a.biography                        AS long_bio,
                a.speaker_experience               AS experience,
                a.image                            AS image,
                u.email                            AS email,
                t.name                             AS shirt
            FROM
                cfp_applicant a
            JOIN
                people_user u ON a.user_id = u.id
            JOIN
                people_tshirtsize t ON u.tshirt_size_id = t.id
            ;
SQL;

        return $this->sqlGenerator($pdo, $sql);
    }

    private function talkGenerator($pdo)
    {
        $sql = <<<SQL
            SELECT
                pa.id AS row_id,
                pa.title,
                pa.about AS short_abstract,
                pa.abstract AS long_abstract,
                null AS submitted,
                sl.name as level,
                pa.duration,
                u.email
            FROM
                cfp_paperapplication pa
            JOIN
                cfp_applicant a ON a.id = pa.applicant_id
            JOIN
                people_user u ON u.id = a.user_id
            JOIN
                cfp_audienceskilllevel sl ON sl.id = pa.skill_level_id
            ORDER BY
                row_id
            ;
SQL;

        return $this->sqlGenerator($pdo, $sql);
    }

    private function sqlGenerator($pdo, $sql)
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        foreach ($stmt as $row) {
            yield $row;
        }
    }

    /** Save or update speaker data. */
    private function saveSpeaker($speaker)
    {
        // Make the image path complete
        $speaker['image'] = "https://2015.webcampzg.org/media/" . $speaker['image'];

        $speakers = $this->db->speakers;

        // Find if speaker exists by email
        $existing = $speakers->findOne([
            'email' => $speaker['email']
        ]);

        if ($existing === null) {
            $this->output->writeln("Adding speaker: " . $speaker['name']);
            $speakers->save($speaker);
        } else {
            list($speaker, $hasChanges) = $this->merge($existing, $speaker);
            if ($hasChanges) {
                $this->output->writeln("Updating speaker: " . $speaker['name']);
                $speakers->save($speaker);
            }
        }
    }

    /** Save or update talk data. */
    private function saveTalk($talk)
    {
        $talks = $this->db->talks;
        $speakers = $this->db->speakers;

        // Find the speaker by email
        $speaker = $speakers->findOne([
            'email' => $talk['email']
        ]);

        if ($speaker === null) {
            throw new \Exception("Cannot find speaker " . $talk['email']);
        }

        // Add speaker link to talk
        $talk['speaker_id'] = $speaker['_id'];

        // Convert the submission date
        if ($talk['submitted'] !== null) {
            $dt = new \DateTime($talk['submitted']);

            $talk['submitted'] = new \MongoDate(
                $dt->format('U'),
                $dt->format('u')
            );
        } else {
            // stupid hack for those with no date, so they can at least be sorted
            $talk['submitted'] = new \MongoDate($talk['row_id']*60);
        }

        // Find if talk exists by row ID
        $existing = $talks->findOne([
            'row_id' => $talk['row_id']
        ]);

        if ($existing === null) {
            // Initial settings for new talks
            $talk['scores'] = new \stdClass();
            $talk['status'] = TalksController::STATUS_PENDING;

            $this->output->writeln("Adding talk: " . $talk['title']);
            $talks->save($talk);
        } else {
            list($talk, $hasChanges) = $this->merge($existing, $talk);
            if ($hasChanges) {
                $this->output->writeln("Updating talk: " . $talk['title']);
                $talks->save($talk);
            }
        }
    }

    private function merge($existing, $new)
    {
        $hasChanges = false;

        // Overwrite old values with new values
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $existing) || $existing[$key] != $value) {
                $existing[$key] = $value;
                $hasChanges = true;
            }
        }

        return [$existing, $hasChanges];
    }
}
