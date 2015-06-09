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
        $defaultTemplate = realpath(__DIR__ . "/../../templates/emails/accepted_2015.twig");
        $defaultTarget = realpath(__DIR__ . "/../../target");

        $this
            ->setName('emails')
            ->setDescription('Generates emails for accepted talks')
            ->addOption(
                'target',
                null,
                InputOption::VALUE_REQUIRED,
                'Where do you want to generate the emails?',
                $defaultTarget
            )
            ->addOption(
                'template',
                null,
                InputOption::VALUE_REQUIRED,
                "Which email template to use",
                $defaultTemplate
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->output->writeln("Fetching talks...");

        $template = $input->getOption('template');
        $target = $input->getOption('target');

        if (!is_dir($target)) {
            throw new \Exception("Not a valid target directory: $target");
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

            $this->generateEmail($talk, $speaker, $template, $target);
        }
        $output->writeln("");
        $output->writeln("<info>Done.</info>");
    }

    private function generateEmail($talk, $speaker, $template, $target)
    {
        $filename = sprintf("%s - %s.eml", $speaker['name'], $talk['title']);
        $filename = strtr($filename, "\\/?*", "____");
        $target = rtrim($target, '\\/') . '/' . $filename;

        $this->output->writeln("Generating: <comment>$target</comment>");

        // Extract the first name for adddressing people
        $speaker['first_name'] = explode(" ", $speaker['name'])[0];

        $data = compact("talk", "speaker");

        $template = $this->getTemplate($template);
        $email = $template->render($data);

        file_put_contents($target, $email);
    }
}
