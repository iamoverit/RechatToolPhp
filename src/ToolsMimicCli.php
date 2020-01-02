<?php

namespace Rechat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__.'/../vendor/autoload.php';

class ToolsMimicCli extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'tools:mimic';

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Mimics you')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp(
                'This command just mimics you..'.PHP_EOL.'example usage:'.PHP_EOL.'!tools:mimic funny message to mimic'
            )
            ->addArgument('message', InputArgument::REQUIRED, 'What message to mimic');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write($input->getArgument('message'));

        return 0;
    }
}