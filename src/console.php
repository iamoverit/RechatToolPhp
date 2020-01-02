<?php

use Rechat\base\Application;

require_once __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
$dotenv->load();

$console = new Application();
$console->setAutoExit(false);

$console->add(new \Rechat\BeanstalkdClearCli());

$console->add(new \Rechat\RechatWorkerCli());
$console->add(new \Rechat\ToolsWorkerCli());

$console->add(new \Rechat\RechatCli());
$console->add(new \Rechat\ToolsRandCli());
$console->add(new \Rechat\ToolsMimicCli());
$console->run();