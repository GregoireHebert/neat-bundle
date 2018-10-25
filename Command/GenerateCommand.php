<?php

namespace Gheb\NeatBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Gheb\IOBundle\Aggregator\Aggregator;
use Gheb\NeatBundle\Neat\Genome;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\Neat\Specie;
use Gheb\NeatBundle\HookInterface;
use Gheb\NeatBundle\Manager\Manager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\output\OutputInterface;

/**
 * Class NeatCommand
 *
 * @author  Grégoire Hébert <gregoire@opo.fr>
 */
class GenerateCommand extends ContainerAwareCommand
{
    /**
     * @var HookInterface[]
     */
    private $afterEvaluationHooks = [];

    /**
     * @var HookInterface[]
     */
    private $beforeInitHooks = [];

    /**
     * @var HookInterface[]
     */
    private $beforeNewRunHooks = [];

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var HookInterface
     */
    private $getFitnessHook;

    /**
     * @var Aggregator
     */
    private $inputsAggregator;

    /**
     * @var Mutation
     */
    private $mutation;

    /**
     * @var HookInterface
     */
    private $nextGenomeCriteriaHook;

    /**
     * @var Aggregator
     */
    private $outputsAggregator;

    /**
     * @var HookInterface
     */
    private $stopEvaluationHook;

    /**
     * NeatCommand constructor.
     *
     * @param Aggregator    $inputsAggregator
     * @param Aggregator    $outputsAggregator
     * @param EntityManager $em
     * @param Mutation      $mutation
     */
    public function __construct(Aggregator $inputsAggregator, Aggregator $outputsAggregator, EntityManager $em, Mutation $mutation)
    {
        $this->inputsAggregator  = $inputsAggregator;
        $this->outputsAggregator = $outputsAggregator;
        $this->em                = $em;
        $this->mutation          = $mutation;

        parent::__construct();
    }

    /**
     * @param HookInterface $hook
     */
    public function addAfterEvaluationHooks(HookInterface $hook)
    {
        $this->afterEvaluationHooks[] = $hook;
    }

    /**
     * @param HookInterface $hook
     */
    public function addBeforeInitHooks(HookInterface $hook)
    {
        $this->beforeInitHooks[] = $hook;
    }

    /**
     * @param HookInterface $hook
     */
    public function addBeforeNewRunHooks(HookInterface $hook)
    {
        $this->beforeNewRunHooks[] = $hook;
    }

    /**
     * @param HookInterface $hook
     */
    public function addGetFitnessHook(HookInterface $hook)
    {
        $this->getFitnessHook = $hook;
    }

    /**
     * @param HookInterface $hook
     */
    public function addNextGenomeCriteriaHook(HookInterface $hook)
    {
        $this->nextGenomeCriteriaHook = $hook;
    }

    /**
     * @param HookInterface $hook
     */
    public function addStopEvaluationHook(HookInterface $hook)
    {
        $this->stopEvaluationHook = $hook;
    }

    /**
     * configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('gheb:neat:generate')
            ->setDescription('execute neat to generate the networks');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     * @throws ORMInvalidArgumentException
     * @throws OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        // before init hooks
        foreach ($this->beforeInitHooks as $beforeInitHook) {
            $beforeInitHook();
        }

        $manager = new Manager($this->em, $this->inputsAggregator, $this->outputsAggregator, $this->mutation);
        $pool = $manager->getPool();

        while ($this->stopEvaluationHook instanceof HookInterface ? ($this->stopEvaluationHook)() : true) {

            /** @var Specie $specie */
            /* @var Genome $genome */
            $species = $pool->getSpecies();
            $specie = $species->offsetGet($species->getKeys()[$pool->getCurrentSpecies()]);
            $genome = $specie->getGenomes()->offsetGet($specie->getGenomes()->getKeys()[$pool->getCurrentGenome()]);

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
