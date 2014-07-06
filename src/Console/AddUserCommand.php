<?php

namespace WebCampZg\VotingWeb\Console;

use WebCampZg\VotingWeb\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\User\User;

class AddUserCommand extends Command
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
            ->setName('adduser')
            ->setDescription('Creates a new user')
            ->addOption(
               'username',
               'u',
               InputOption::VALUE_REQUIRED,
               'The username'
            )
            ->addOption(
               'password',
               'p',
               InputOption::VALUE_REQUIRED,
               'The password'
            )
            ->addOption(
               'admin',
               'a',
               InputOption::VALUE_NONE,
               'Create an administrator'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $username = $input->getOption('username', $defaultUsername);
        $password = $input->getOption('password');
        $isAdmin = $input->getOption('admin');

        $dialog = $this->getHelperSet()->get('dialog');
        $defaultUsername = get_current_user();

        // Prompt for username
        if (empty($username)) {
            $username = $dialog->ask(
                $output,
                "Username [<info>$defaultUsername</info>]: ",
                $defaultUsername
            );
        }

        // Prompt for password
        while (empty($password)) {
            $one = $dialog->askHiddenResponse($output, "Enter password: ");
            $two = $dialog->askHiddenResponse($output, "Retype password: ");

            if ($one != $two) {
                $this->output->writeln("<error>Password mismatch. Try again.</error>");
                continue;
            }

            if (empty($one)) {
                $this->output->writeln("<error>Password cannot be empty.</error>");
                continue;
            }

            $password = $one;
        }

        $output->writeln("");

        $this->createUser($username, $password, $isAdmin);
    }

    private function createUser($username, $password, $isAdmin)
    {
        // Check if user already exists
        $users = $this->app['db']->users;
        $count = $users->find(['username' => $username])->count();
        if ($count > 0) {
            throw new \Exception("User \"$username\"already exists.");
        }

        $roles = $isAdmin ? ['ROLE_ADMIN'] : ['ROLE_VOTER'];

        // Encode the password
        $user = new User($username, $password, $roles);
        $encPass = $this->app->encodePassword($user, $password);

        // Save to database
        $users->save([
            'username' => $username,
            'password' => $encPass,
            'roles' => $roles,
        ]);

        $this->output->writeln("User <info>$username</info> created successfully.");
    }
}
