<?php

namespace Gheb\NeatBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Gheb\IOBundle\Aggregator\Aggregator;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\HookInterface;
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
     * @var HookInterface[]
     */
    private $afterEvaluationHooks = [];

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Aggregator
     */
    private $inputsAggregator;

    /**
     * @var Mutation
     */
    private $mutation;

    /**
     * @var Aggregator
     */
    private $outputsAggregator;

    /**
     * @var HookInterface
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

    public function addAfterEvaluationHooks(HookInterface $hook)
    {
        $this->afterEvaluationHooks[] = $hook;
    }

    public function addStopEvaluationHook(HookInterface $hook)
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
     *
     * @throws ORMInvalidArgumentException
     * @throws OptimisticLockException
     * @throws \Exception
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $manager = new Manager($this->em, $this->inputsAggregator, $this->outputsAggregator, $this->mutation, $this->pusher);
        $this->em->getConnection()->getConfiguration()->setSQLLogger();

        if ($this->stopEvaluationHook instanceof HookInterface ? ($this->stopEvaluationHook)() : true) {
            $manager->evaluateBest();

            // after evaluation hooks
            foreach ($this->afterEvaluationHooks as $afterEvaluationHook) {
                $afterEvaluationHook();
            }
        }
    }
}
