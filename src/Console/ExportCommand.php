<?php

namespace WebCampZg\VotingWeb\Console;

use WebCampZg\VotingWeb\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\User\User;

class ExportCommand extends Command
{
    private $app;
    private $output;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('export')
            ->setDescription('Exports accepted talks & speakers data to JSON')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $talks = $this->app['db']->talks;
        $speakers = $this->app['db']->speakers;

        $query = ['status' => 'accepted'];

        $data = [];
        foreach($talks->find($query) as $talk) {
            $speaker = $speakers->findOne(['_id' => $talk['speaker_id']]);
            if ($speaker === null) {
                throw new \Exception("Speaker not found for talk {$talk['title']}. Broken relatinship?");
            }

            $talk['speaker'] = $speaker;
            unset($talk['speaker_id']);

            $data[] = $talk;
        }

        $output->write(json_encode($data, JSON_PRETTY_PRINT));
    }
}
