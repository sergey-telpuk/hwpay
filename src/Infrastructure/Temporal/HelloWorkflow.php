<?php

declare(strict_types=1);

namespace App\Infrastructure\Temporal;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class HelloWorkflow
{
    #[WorkflowMethod(name: 'HelloWorkflow')]
    public function run(string $name): \Generator
    {
        $activity = Workflow::newActivityStub(
            HelloActivityInterface::class,
            ActivityOptions::new()->withStartToCloseTimeout(\DateInterval::createFromDateString('30 seconds'))
        );

        return yield $activity->greet($name);
    }
}
