<?php

namespace Gheb\NeatBundle\Command;

use Gheb\IOBundle\Aggregator\Aggregator;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\HookInterface;
use Gheb\NeatBundle\Manager\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\output\OutputInterface;

#[AsCommand(name: 'gheb:neat:evaluate', description: 'execute neat to evaluate best genome network')]
class EvaluateCommand extends Command
{
    /**
     * @var array<HookInterface>
     */
    private array $afterEvaluationHooks = [];

    private ?HookInterface $stopEvaluationHook;

    public function __construct(private Aggregator $inputsAggregator, private Aggregator $outputsAggregator, private Mutation $mutation)
    {
        parent::__construct();
    }

    public function addAfterEvaluationHooks(HookInterface $hook)
    {
        $this->afterEvaluationHooks[] = $hook;
    }

    public function addStopEvaluationHook(HookInterface $hook)
    {
        $this->stopEvaluationHook = $hook;
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $manager = new Manager($this->inputsAggregator, $this->outputsAggregator, $this->mutation);

        if (!$this->stopEvaluationHook instanceof HookInterface || ($this->stopEvaluationHook)()) {
            $manager->evaluateBest();

            // after evaluation hooks
            foreach ($this->afterEvaluationHooks as $afterEvaluationHook) {
                $afterEvaluationHook();
            }
        }
    }
}
