<?php

namespace Gheb\NeatBundle\Command;

use Doctrine\ORM\EntityManager;
use Gheb\IOBundle\Aggregator;
use Gheb\IOBundle\Inputs\InputsAggregator;
use Gheb\NeatBundle\Neat\Genome;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\Neat\Specie;
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
            ->setName('gheb:neat:evaluate')
            ->setDescription('execute neat to evaluate best genome network');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // before init hooks
        foreach ($this->beforeInitHooks as $beforeInitHook) {
            $beforeInitHook();
        }

        $manager = new Manager($this->em, $this->inputsAggregator, $this->outputsAggregator, $this->mutation);
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        while ($this->stopEvaluationHook instanceof Hook ? $this->stopEvaluationHook() : true) {
            $manager->evaluateBest();

            // after evaluation hooks
            foreach ($this->afterEvaluationHooks as $afterEvaluationHook) {
                $afterEvaluationHook();
            }
        }
    }
}
