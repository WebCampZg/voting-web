<?php

namespace WebCampZg\VotingWeb\Console;

use WebCampZg\VotingWeb\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\User\User;

/**
 * Generates emails to send to for accepted talks.
 */
class EmailsCommand extends Command
{
    private $app;
    private $output;
    private $target;
    private $template;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    public function getTemplate($template)
    {
        if (!isset($this->template)) {
            $this->template = $this->app['twig']->loadTemplate($template);
        }

        return $this->template;
    }

    protected function configure()
    {
        $this
            ->setName('emails')
            ->setDescription('Generates emails for accepted talks')
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Where do you want to generate the emails?'
            )
            ->addOption(
                'template',
                't',
                InputOption::VALUE_REQUIRED,
                "Which email template to use",
                "emails/accepted_2014.twig"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->output->writeln("Fetching talks...");

        $template = $input->getOption('template');
        $this->target = $input->getArgument('target');
        if (!is_dir($this->target)) {
            throw new \Exception("Not a valid target directory: $this->target");
        }

        $talks = $this->app['db']->talks->find([
            'status' => 'accepted'
        ]);
        $talks = iterator_to_array($talks);

        $this->output->writeln("Fetching speakers...");
        $speakers = $this->app['db']->speakers->find();
        $speakers = iterator_to_array($speakers);

        $output->writeln("");

        foreach ($talks as $talk) {
            $speakerID = (string) $talk['speaker_id'];
            if (!isset($speakers[$speakerID])) {
                throw new \Exception("Cannot find speaker $speakerID");
            }

            $speaker = $speakers[$speakerID];

            $this->generateEmail($talk, $speaker, $template);
        }
        $output->writeln("");
        $output->writeln("<info>Done.</info>");
    }

    private function generateEmail($talk, $speaker, $template)
    {
        $filename = sprintf("%s - %s.eml", $speaker['name'], $talk['title']);
        $target = $this->target . $filename;

        $this->output->writeln("Generating: <comment>$target</comment>");

        // Extract the first name for adddressing people
        $speaker['first_name'] = explode(" ", $speaker['name'])[0];

        $data = compact("talk", "speaker");

        $template = $this->getTemplate($template);
        $email = $template->render($data);


        file_put_contents($target, $email);
    }
}
