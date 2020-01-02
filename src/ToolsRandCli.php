<?php

namespace Rechat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/../vendor/autoload.php';

class ToolsRandCli extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'tools:rand';

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Generates pseudo-random number')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp(
                'This command allows you to get a random number from a given range...'.PHP_EOL.'example usage:'.PHP_EOL.'!tools:rand --min 5 --max 10'
            )
            ->addOption('min', null, InputOption::VALUE_REQUIRED, 'The lower bound of the range to generate')
            ->addOption('max', null, InputOption::VALUE_REQUIRED, 'The upper bound of the range to generate');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write(rand((int)$input->getOption('min'), (int)$input->getOption('max')));

        return 0;
    }
}