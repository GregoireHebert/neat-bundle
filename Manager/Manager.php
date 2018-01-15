<?php

namespace Gheb\NeatBundle\Manager;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Gheb\IOBundle\Aggregator\Aggregator ;
use Gheb\IOBundle\Outputs\AbstractOutput;
use Gheb\NeatBundle\Neat\Genome;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\Neat\Pool;
use Gheb\NeatBundle\Neat\Specie;
use Gheb\NeatBundle\Neat\Network;
use Gos\Bundle\WebSocketBundle\DataCollector\PusherDecorator;

class Manager
{
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
     * @var Pool
     */
    private $pool;
    /**
     * @var PusherDecorator
     */
    private $pusher;

    /**
     * Manager constructor.
     *
     * @param EntityManager $em
     * @param Aggregator    $inputsAggregator
     * @param Aggregator    $outputsAggregator
     * @param Mutation      $mutation
     * @param PusherDecorator   $pusher
     *
     * @throws OptimisticLockException
     * @throws ORMInvalidArgumentException
     * @throws \Exception
     */
    public function __construct(EntityManager $em, Aggregator $inputsAggregator, Aggregator $outputsAggregator, Mutation $mutation, $pusher = null)
    {
        $this->em                = $em;
        $this->inputsAggregator  = $inputsAggregator;
        $this->outputsAggregator = $outputsAggregator;
        $this->mutation          = $mutation;
        $this->pusher            = $pusher;

        $repo       = $this->em->getRepository('NeatBundle:Pool');
        $this->pool = $repo->findOneBy([]);

        if (!$this->pool instanceof Pool) {
            $this->initializePool();
        } else {
            $this->pool->setEm($em);
            $this->pool->setInputAggregator($inputsAggregator);
            $this->pool->setMutation($mutation);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMInvalidArgumentException
     * @throws \Exception
     */
    public function initializePool(): void
    {

        $pool = new Pool($this->em, $this->outputsAggregator, $this->inputsAggregator, $this->mutation);
        $this->em->persist($pool);
        $this->em->flush();

        $repo       = $this->em->getRepository('NeatBundle:Pool');
        $this->pool = $repo->findOneBy([]);


        for ($i = 0; $i < Pool::POPULATION; $i++) {
            $this->pool->addToSpecies($this->pool->createBasicGenome());
        }

        $this->em->flush();

        $this->initializeRun();
    }

    /**
     * @throws OptimisticLockException
     * @throws \Exception
     */
    public function initializeRun(): void
    {
        /** @var Specie $specie */
        /* @var Genome $genome */
        $species = $this->pool->getSpecies();
        $specie = $species->offsetGet($species->getKeys()[$this->pool->getCurrentSpecies()]);
        $genome = $specie->getGenomes()->offsetGet($specie->getGenomes()->getKeys()[$this->pool->getCurrentGenome()]);

        Network::generateNetwork($genome, $this->outputsAggregator, $this->inputsAggregator, $this->em);

        $this->evaluateCurrent();
    }

    /**
     * @throws \Exception
     */
    public function evaluateCurrent(): void
    {
        /** @var Specie $specie */
        /* @var Genome $genome */
        $species = $this->pool->getSpecies();
        $specie = $species->offsetGet($species->getKeys()[$this->pool->getCurrentSpecies()]);
        if (!$specie instanceof Specie) {
            return ;
        }

        $genome = $specie->getGenomes()->offsetGet($specie->getGenomes()->getKeys()[$this->pool->getCurrentGenome()]);
        if (!$specie instanceof Specie) {
            return ;
        }

        $inputs  = $this->inputsAggregator->aggregate->toArray();
        $outputs = Network::evaluate($genome, $inputs, $this->outputsAggregator, $this->inputsAggregator);

        $this->applyOutputs($outputs);
    }

    /**
     * @param $outputs
     */
    public function applyOutputs(array $outputs): void
    {
        /** @var AbstractOutput $output */
        foreach ($outputs as $output) {
            try {
                $output->apply();
            } catch (\Exception $e) {
                var_dump($e->getMessage());

                return;
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function evaluateBest(): void
    {
        $pool = $this->pool;
        $genome = $pool->getBestGenome();

        $inputs  = $this->inputsAggregator->aggregate->toArray();
        $outputs = Network::evaluate($genome, $inputs, $this->outputsAggregator, $this->inputsAggregator);

        foreach ($outputs as $output) {
            /** @var AbstractOutput $output */
            $this->pusher->push($output->getName(), 'output_application');
        }

        $this->applyOutputs($outputs);
    }

    /**
     * Return either a genome fitness has been measured or not
     *
     * @return bool
     */
    public function fitnessAlreadyMeasured(): bool
    {
        /** @var Specie $specie */
        $species = $this->pool->getSpecies();
        $specie = $species->offsetGet($species->getKeys()[$this->pool->getCurrentSpecies()]);
        if (!$specie instanceof Specie) {
            return false;
        }

        /** @var Genome $genome */
        $genome = $specie->getGenomes()->offsetGet($specie->getGenomes()->getKeys()[$this->pool->getCurrentGenome()]);
        if (!$specie instanceof Specie) {
            return false;
        }

        return $genome->getFitness() !== 0;
    }

    /**
     * @return EntityManager
     */
    public function getEm(): EntityManager
    {
        return $this->em;
    }

    /**
     * @param EntityManager $em
     */
    public function setEm($em): void
    {
        $this->em = $em;
    }

    /**
     * @return Aggregator
     */
    public function getInputsAggregator(): Aggregator
    {
        return $this->inputsAggregator;
    }

    /**
     * @param Aggregator  $inputsAggregator
     */
    public function setInputsAggregator($inputsAggregator): void
    {
        $this->inputsAggregator = $inputsAggregator;
    }

    /**
     * @return Mutation
     */
    public function getMutation(): Mutation
    {
        return $this->mutation;
    }

    /**
     * @param Mutation $mutation
     */
    public function setMutation($mutation): void
    {
        $this->mutation = $mutation;
    }

    /**
     * @return Aggregator
     */
    public function getOutputsAggregator(): Aggregator
    {
        return $this->outputsAggregator;
    }

    /**
     * @param Aggregator  $outputsAggregator
     */
    public function setOutputsAggregator($outputsAggregator): void
    {
        $this->outputsAggregator = $outputsAggregator;
    }

    /**
     * @return Pool
     */
    public function getPool(): Pool
    {
        return $this->pool;
    }

    /**
     * @param Pool $pool
     */
    public function setPool($pool): void
    {
        $this->pool = $pool;
    }
}
