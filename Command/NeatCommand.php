<?php

namespace Gheb\NeatBundle\Command;

use Doctrine\ORM\EntityManager;
use Gheb\IOBundle\Aggregator;
use Gheb\IOBundle\Inputs\InputsAggregator;
use Gheb\NeatBundle\Entity\Genome;
use Gheb\NeatBundle\Entity\Mutation;
use Gheb\NeatBundle\Entity\Specie;
use Gheb\NeatBundle\Hook;
use Gheb\NeatBundle\Manager\Manager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\output\OutputInterface;

/**
 * Class NeatCommand
 *
 * @author  Grégoire Hébert <gregoire@opo.fr>
 */
class NeatCommand extends ContainerAwareCommand
{
    /**
     * @var Hook[]
     */
    private $afterEvaluationHooks = [];

    /**
     * @var Hook[]
     */
    private $beforeInitHooks = [];

    /**
     * @var Hook[]
     */
    private $beforeNewRunHooks = [];

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Hook
     */
    private $getFitnessHook;

    /**
     * @var InputsAggregator
     */
    private $inputsAggregator;

    /**
     * @var Mutation
     */
    private $mutation;

    /**
     * @var Hook
     */
    private $nextGenomeCriteriaHook;

    /**
     * @var Aggregator
     */
    private $outputsAggregator;

    /**
     * @var Hook
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

    public function addAfterEvaluationHooks(Hook $hook)
    {
        $this->afterEvaluationHooks[] = $hook;
    }

    public function addBeforeInitHooks(Hook $hook)
    {
        $this->beforeInitHooks[] = $hook;
    }

    public function addBeforeNewRunHooks(Hook $hook)
    {
        $this->beforeNewRunHooks[] = $hook;
    }

    public function addGetFitnessHook(Hook $hook)
    {
        $this->getFitnessHook = $hook;
    }

    public function addNextGenomeCriteriaHook(Hook $hook)
    {
        $this->nextGenomeCriteriaHook = $hook;
    }

    public function addStopEvaluationHook(Hook $hook)
    {
        $this->stopEvaluationHook = $hook;
    }

    /**
     * configure the command
     */
    protected function configure()
    {
        $this
            ->setName('gheb:neat:run')
            ->setDescription('execute neat');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // before init hooks
        foreach ($this->beforeInitHooks as $beforeInitHook) {
            $beforeInitHook->hook();
        }

        $manager = new Manager($this->em, $this->inputsAggregator, $this->outputsAggregator, $this->mutation);
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        while ($this->stopEvaluationHook instanceof Hook ? $this->stopEvaluationHook->hook() : true) {
            $pool = $manager->getPool();

            /** @var Specie $specie */
            /* @var Genome $genome */
            $specie = $pool->getSpecies()->offsetGet($pool->getCurrentSpecies());
            $genome = $specie->getGenomes()->offsetGet($pool->getCurrentGenome());

            if ($this->nextGenomeCriteriaHook->hook()) {
                $fitness = $this->getFitnessHook->hook();
                $genome->setFitness($fitness);

                if ($fitness > $pool->getMaxFitness()) {
                    $pool->setMaxFitness($fitness);
                }

                $pool->setCurrentSpecies(0);
                $pool->setCurrentGenome(0);

                while ($manager->fitnessAlreadyMeasured()) {
                    $pool->nextGenome();
                }

                $this->em->flush();

                // before new run hooks
                foreach ($this->beforeNewRunHooks as $beforeNewRunHook) {
                    $beforeNewRunHook->hook();
                }

                $manager->initializeRun();
            } else {
                $manager->evaluateCurrent();
            }

            // after evaluation hooks
            foreach ($this->afterEvaluationHooks as $afterEvaluationHook) {
                $afterEvaluationHook->hook();
            }
        }
    }
}
