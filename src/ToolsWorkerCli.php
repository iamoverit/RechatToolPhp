<?php

namespace Rechat;

use Rechat\base\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Pheanstalk\Pheanstalk;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ToolsWorkerCli extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'tools:worker';

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Starts tools jobs worker.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pheanstalk = Pheanstalk::create(getenv('BEANSTALKD_HOST'), getenv('BEANSTALKD_PORT'));;
        $output->writeln('rechat worker started...');
        while (true) {
            $job = $pheanstalk
                ->watch('toolsJobs')
                ->ignore('default')
                ->reserveWithTimeout(5);
            if (isset($job)) {
                //$this->ensureDatabase($db);
                try {
                    //$task = $taskFactory->createFromJson($job->getData());
                    //$commandBus->handle($task);
                    $jobData = \GuzzleHttp\json_decode($job->getData());
                    $console = new Application();

                    $userCommmands = [
                        (new \Rechat\ToolsRandCli())->addOption('--highlight', '-hl', InputOption::VALUE_OPTIONAL, 'Response highlight type', 'false'),
                        (new \Rechat\ToolsMimicCli())->addOption('--highlight', '-hl', InputOption::VALUE_OPTIONAL, 'Response highlight type', null),
                        (new \Rechat\RechatCli())->addOption('--highlight', '-hl', InputOption::VALUE_OPTIONAL, 'Response highlight type', 'md'),
                    ];

                    foreach ($userCommmands as $userCommmand) {
                        $console->add($userCommmand);
                    }
                    $console->setAutoExit(false);
                    $input = new StringInput(substr($jobData->msg->content, 1));
                    $bufferedOutput = new BufferedOutput(
                        OutputInterface::VERBOSITY_NORMAL,
                        false // true for decorated
                    );
                    $console->run($input, $bufferedOutput);
                    $highlightType = $this->getResponseType($input);
                    $pheanstalk->useTube('discordJobs')->put(
                        \GuzzleHttp\json_encode(
                            ['raw_response' => $bufferedOutput->fetch().' ']
                            + ['msg' => $jobData->msg]
                            + [$highlightType ? ['type' => $highlightType] : []]
                        )
                    );
                    $output->writeln($job->getData());
                    $output->writeln("Deleting job: {$job->getId()}");
                    $pheanstalk->delete($job);
                } catch (\Throwable $t) {
                    $output->writeln(PHP_EOL."{$t->getMessage()}");
                    $output->writeln("{$t->getTraceAsString()}");

                    $output->writeln("Burying job: {$job->getId()}");
                    $pheanstalk->bury($job);
                }
            }
        }

        return 0;
    }

    /**
     * @param string $command
     * @return string
     */
    protected function getResponseType(string $command): ?string
    {
        $inputDefinition = new InputDefinition([new InputOption('--highlight', '-hl', InputOption::VALUE_OPTIONAL, 'Response highlight type', null)]);
        $stringInput = new \Rechat\lib\StringInput($command);
        $stringInput->bind($inputDefinition);

        return $stringInput->getOption('highlight');
    }
}