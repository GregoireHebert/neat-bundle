<?php

namespace Gheb\NeatBundle\Manager;

use Doctrine\ORM\EntityManager;
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

    public function initializePool()
    {
        $this->pool = new Pool($this->em, $this->outputsAggregator, $this->inputsAggregator, $this->mutation);

        for ($i = 0; $i < Pool::POPULATION; $i++) {
            $this->pool->addToSpecies($this->pool->createBasicGenome());
        }

        $this->em->persist($this->pool);

        $this->initializeRun();
    }

    public function initializeRun()
    {
        /** @var Specie $specie */
        /* @var Genome $genome */
        $specie = $this->pool->getSpecies()->offsetGet($this->pool->getCurrentSpecies());
        $genome = $specie->getGenomes()->offsetGet($this->pool->getCurrentGenome());

        Network::generateNetwork($genome, $this->outputsAggregator, $this->inputsAggregator);

        $this->evaluateCurrent();
    }

    public function evaluateCurrent()
    {
        /** @var Specie $specie */
        /* @var Genome $genome */
        $specie = $this->pool->getSpecies()->offsetGet($this->pool->getCurrentSpecies());
        $genome = $specie->getGenomes()->offsetGet($this->pool->getCurrentGenome());

        $inputs  = $this->inputsAggregator->aggregate->toArray();
        $outputs = Network::evaluate($genome, $inputs, $this->outputsAggregator, $this->inputsAggregator);

        $this->applyOutputs($outputs);
    }

    public function applyOutputs($outputs)
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

    public function evaluateBest()
    {
        $genome = $this->pool->getBestGenome();

        $inputs  = $this->inputsAggregator->aggregate->toArray();
        $outputs = Network::evaluate($genome, $inputs, $this->outputsAggregator, $this->inputsAggregator);

        foreach ($outputs as $output) {
            /** @var AbstractOutput $output */
            $this->pusher->push(['outputName' => $output->getName()], 'output_application', ['username' => 'user1']);
        }

        $this->applyOutputs($outputs);
    }

    /**
     * Return either a genome fitness has been measured or not
     *
     * @return bool
     */
    public function fitnessAlreadyMeasured()
    {
        /** @var Specie $specie */
        $specie = $this->pool->getSpecies()->offsetGet($this->pool->getCurrentSpecies());

        /** @var Genome $genome */
        $genome = $specie->getGenomes()->offsetGet($this->pool->getCurrentGenome());

        return $genome->getFitness() != 0;
    }

    /**
     * @return EntityManager
     */
    public function getEm()
    {
        return $this->em;
    }

    /**
     * @param EntityManager $em
     */
    public function setEm($em)
    {
        $this->em = $em;
    }

    /**
     * @return Aggregator
     */
    public function getInputsAggregator()
    {
        return $this->inputsAggregator;
    }

    /**
     * @param Aggregator  $inputsAggregator
     */
    public function setInputsAggregator($inputsAggregator)
    {
        $this->inputsAggregator = $inputsAggregator;
    }

    /**
     * @return Mutation
     */
    public function getMutation()
    {
        return $this->mutation;
    }

    /**
     * @param Mutation $mutation
     */
    public function setMutation($mutation)
    {
        $this->mutation = $mutation;
    }

    /**
     * @return Aggregator
     */
    public function getOutputsAggregator()
    {
        return $this->outputsAggregator;
    }

    /**
     * @param Aggregator  $outputsAggregator
     */
    public function setOutputsAggregator($outputsAggregator)
    {
        $this->outputsAggregator = $outputsAggregator;
    }

    /**
     * @return Pool
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @param Pool $pool
     */
    public function setPool($pool)
    {
        $this->pool = $pool;
    }
}
