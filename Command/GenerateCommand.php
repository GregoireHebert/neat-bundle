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

#[AsCommand(name: 'gheb:neat:generate', description: 'execute neat to generate the networks')]
class GenerateCommand extends Command
{
    /**
     * @var HookInterface[]
     */
    private array $afterEvaluationHooks = [];

    /**
     * @var HookInterface[]
     */
    private array $beforeInitHooks = [];

    /**
     * @var HookInterface[]
     */
    private array $beforeNewRunHooks = [];

    private ?HookInterface $getFitnessHook;

    private ?HookInterface $nextGenomeCriteriaHook;

    private ?HookInterface $stopEvaluationHook;

    public function __construct(private Aggregator $inputsAggregator, private Aggregator $outputsAggregator, private Mutation $mutation)
    {
        parent::__construct();
    }

    public function addAfterEvaluationHooks(HookInterface $hook): void
    {
        $this->afterEvaluationHooks[] = $hook;
    }

    public function addBeforeInitHooks(HookInterface $hook): void
    {
        $this->beforeInitHooks[] = $hook;
    }

    public function addBeforeNewRunHooks(HookInterface $hook): void
    {
        $this->beforeNewRunHooks[] = $hook;
    }

    public function addGetFitnessHook(HookInterface $hook): void
    {
        $this->getFitnessHook = $hook;
    }

    public function addNextGenomeCriteriaHook(HookInterface $hook): void
    {
        $this->nextGenomeCriteriaHook = $hook;
    }

    public function addStopEvaluationHook(HookInterface $hook): void
    {
        $this->stopEvaluationHook = $hook;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // before init hooks
        foreach ($this->beforeInitHooks as $beforeInitHook) {
            $beforeInitHook();
        }

        $manager = new Manager($this->inputsAggregator, $this->outputsAggregator, $this->mutation);
        $pool = $manager->getPool();

        while (!$this->stopEvaluationHook instanceof HookInterface || ($this->stopEvaluationHook)()) {
            $species = $pool->getSpecies();
            $specie = $species[$pool->getCurrentSpecies()];
            $genome = $specie->getGenomes()[$pool->getCurrentGenome()];

            if (($this->nextGenomeCriteriaHook)()) {
                $fitness = ($this->getFitnessHook)();
                $genome->setFitness($fitness);

                if ($specie->getTopFitness() < $fitness) {
                    $specie->setTopFitness($fitness);
                }

                if ($pool->getMaxFitness() < $fitness) {
                    $pool->setMaxFitness($fitness);
                }

                $pool->setCurrentSpecies(0);
                $pool->setCurrentGenome(0);

                while ($manager->fitnessAlreadyMeasured()) {
                    $pool->nextGenome();
                }

                // before new run hooks
                foreach ($this->beforeNewRunHooks as $beforeNewRunHook) {
                    $beforeNewRunHook();
                }

                $manager->initializeRun();
            } else {
                $manager->evaluateCurrent();
            }

            // after evaluation hooks
            foreach ($this->afterEvaluationHooks as $afterEvaluationHook) {
                $afterEvaluationHook();
            }
        }
    }
}
