<?php

namespace Gheb\NeatBundle\Command;

use Doctrine\ORM\EntityManager;
use Gheb\IOBundle\Aggregator;
use Gheb\IOBundle\Inputs\InputsAggregator;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\Hook;
use Gheb\NeatBundle\Manager\Manager;
use Gos\Bundle\WebSocketBundle\DataCollector\PusherDecorator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\output\OutputInterface;

/**
 * Class NeatCommand
 *
 * @author  Grégoire Hébert <gregoire@opo.fr>
 */
class EvaluateCommand extends Command
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
     * @var PusherDecorator
     */
    private $pusher;

    /**
     * NeatCommand constructor.
     *
     * @param Aggregator    $inputsAggregator
     * @param Aggregator    $outputsAggregator
     * @param EntityManager $em
     * @param Mutation      $mutation
     * @param PusherDecorator $pusher
     */
    public function __construct(Aggregator $inputsAggregator, Aggregator $outputsAggregator, EntityManager $em, Mutation $mutation, $pusher)
    {
        $this->inputsAggregator  = $inputsAggregator;
        $this->outputsAggregator = $outputsAggregator;
        $this->em                = $em;
        $this->mutation          = $mutation;
        $this->pusher            = $pusher;

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
        $manager = new Manager($this->em, $this->inputsAggregator, $this->outputsAggregator, $this->mutation, $this->pusher);
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        if ($this->stopEvaluationHook instanceof Hook ? $this->stopEvaluationHook() : true) {
            $manager->evaluateBest();

            // after evaluation hooks
            foreach ($this->afterEvaluationHooks as $afterEvaluationHook) {
                $afterEvaluationHook();
            }
        }
    }
}
