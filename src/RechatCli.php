<?php

namespace Rechat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/../vendor/autoload.php';

class RechatCli extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'rechat';

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Process rechat job.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp(
                'This command allows you to handle rechat job...'.PHP_EOL.'example usage:'.PHP_EOL.'!rechat honechk1 tallbl4 --last 5'
            )
            ->addArgument('channel', InputArgument::REQUIRED, 'Twitch channel name e.g. honechk1')
            ->addArgument(
                'users',
                InputArgument::IS_ARRAY,
                'Twitch user name to search there messages in twitch channel e.g. tallbl4'
            )
            ->addOption(
                'message',
                'm',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Part of message to filter results.'
            )
            ->addOption(
                'last',
                'l',
                InputArgument::OPTIONAL,
                'How many recent entries to use. Without this option messages will be searched in last N vods returned by twitch api.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$output->writeln('execution started');
        $output->writeln(
            Main::searchUserInChannel(
                $input->getArgument('channel'),
                $input->getOption('last'),
                $input->getArgument('users'),
                $input->getOption('message')
            )
        );

        return 0;
    }
}