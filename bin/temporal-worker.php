#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Infrastructure\Temporal\HelloActivity;
use App\Infrastructure\Temporal\HelloWorkflow;
use Temporal\Worker\WorkerFactory as TemporalWorkerFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$taskQueue = getenv('TEMPORAL_TASK_QUEUE') ?: 'default';

$factory = TemporalWorkerFactory::create();
$worker = $factory->newWorker($taskQueue);
$worker->registerWorkflowTypes(HelloWorkflow::class);
$worker->registerActivityImplementations(new HelloActivity());

$factory->run();
