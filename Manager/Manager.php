<?php

namespace Gheb\NeatBundle\Manager;

use Gheb\IOBundle\Aggregator\Aggregator ;
use Gheb\IOBundle\Outputs\AbstractOutput;
use Gheb\NeatBundle\Neat\Mutation;
use Gheb\NeatBundle\Neat\Pool;
use Gheb\NeatBundle\Neat\Specie;
use Gheb\NeatBundle\Neat\Network;

class Manager
{
    /**
     * @var Pool
     */
    private $pool;

    public function __construct(private Aggregator $inputsAggregator, private Aggregator $outputsAggregator, private Mutation $mutation)
    {
        $this->pool = null; // TODO Load pool

        if (!$this->pool instanceof Pool) {
            $this->initializePool();
        } else {
            $this->pool->setInputAggregator($inputsAggregator);
            $this->pool->setMutation($mutation);
        }
    }

    public function initializePool(): void
    {
        $this->pool = new Pool($this->mutation, $this->outputsAggregator, $this->inputsAggregator);

        for ($i = 0; $i < Pool::POPULATION; $i++) {
            $this->pool->addToSpecies($this->pool->createBasicGenome());
        }

        $this->initializeRun();
    }

    public function initializeRun(): void
    {
        $species = $this->pool->getSpecies();
        $specie = $species[$this->pool->getCurrentSpecies()];
        $genome = $specie->getGenomes()[$this->pool->getCurrentGenome()];

        Network::generateNetwork($genome, $this->outputsAggregator, $this->inputsAggregator);

        $this->evaluateCurrent();
    }

    public function evaluateCurrent(): void
    {
        $species = $this->pool->getSpecies();
        $specie = $species[$this->pool->getCurrentSpecies()];
        if (!$specie instanceof Specie) {
            return ;
        }

        $genome = $specie->getGenomes()[$this->pool->getCurrentGenome()];

        $inputs  = (array) $this->inputsAggregator->aggregates;
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

        $inputs  = (array) $this->inputsAggregator->aggregates;
        $outputs = Network::evaluate($genome, $inputs, $this->outputsAggregator, $this->inputsAggregator);

        // TODO DUMP/LOG Output

        $this->applyOutputs($outputs);
    }

    /**
     * Return if either a genome fitness has been measured or not
     *
     * @return bool
     */
    public function fitnessAlreadyMeasured(): bool
    {
        $species = $this->pool->getSpecies();
        $specie = $species[$this->pool->getCurrentSpecies()];
        $genome = $specie->getGenomes()[$this->pool->getCurrentGenome()];

        return $genome->getFitness() !== 0;
    }

    public function getInputsAggregator(): Aggregator
    {
        return $this->inputsAggregator;
    }

    public function setInputsAggregator($inputsAggregator): void
    {
        $this->inputsAggregator = $inputsAggregator;
    }

    public function getMutation(): Mutation
    {
        return $this->mutation;
    }

    public function setMutation(Mutation $mutation): void
    {
        $this->mutation = $mutation;
    }

    public function getOutputsAggregator(): Aggregator
    {
        return $this->outputsAggregator;
    }

    public function setOutputsAggregator(Aggregator $outputsAggregator): void
    {
        $this->outputsAggregator = $outputsAggregator;
    }

    public function getPool(): Pool
    {
        return $this->pool;
    }

    public function setPool(Pool $pool): void
    {
        $this->pool = $pool;
    }
}
