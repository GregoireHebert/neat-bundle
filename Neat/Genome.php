<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\Common\Collections\ArrayCollection;

class Genome
{
    /**
     * @var int
     */
    public $fitness = 0;

    /**
     * @var ArrayCollection
     */
    public $genes;

    /**
     * @var int
     */
    public $globalRank = 0;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $maxNeuron = 0;

    /**
     * @var array
     */
    public $mutationRates = [];

    /**
     * @var ArrayCollection
     */
    public $network;

    /**
     * @var Specie
     */
    public $specie;

    public function __construct()
    {
        $this->genes   = new ArrayCollection();
        $this->network = new ArrayCollection();

        $this->mutationRates['connections'] = 0.25;
        $this->mutationRates['link']        = 2.0;
        $this->mutationRates['bias']        = 0.40;
        $this->mutationRates['node']        = 0.50;
        $this->mutationRates['enable']      = 0.2;
        $this->mutationRates['disable']     = 0.4;
        $this->mutationRates['step']        = 0.1;
    }

    public function __clone()
    {
        if ($this->id) {
            $this->setId(null);
            $this->setFitness(0);
            $this->setNetwork(new ArrayCollection());
            $this->setMaxNeuron(0);
            $this->setGlobalRank(0);

            $genesClone = new ArrayCollection();
            foreach ($this->getGenes() as $gene) {
                /** @var Gene $geneClone */
                $geneClone = clone $gene;
                $geneClone->setGenome($this);
                $genesClone->add($geneClone);
            }
            $this->setGenes($genesClone);
        }
    }

    /**
     * @param Gene $gene
     */
    public function addGene(Gene $gene)
    {
        $this->genes->add($gene);
        $gene->setGenome($this);
    }

    /**
     * @param Neuron $neuron
     */
    public function addNeuron(Neuron $neuron)
    {
        $this->network->add($neuron);
    }

    /**
     * @return int
     */
    public function getFitness()
    {
        return $this->fitness;
    }

    /**
     * @return ArrayCollection
     */
    public function getGenes()
    {
        return $this->genes;
    }

    /**
     * @return int
     */
    public function getGlobalRank()
    {
        return $this->globalRank;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getMaxNeuron()
    {
        return $this->maxNeuron;
    }

    /**
     * @return array
     */
    public function getMutationRates()
    {
        return $this->mutationRates;
    }

    /**
     * @return ArrayCollection
     */
    public function getNetwork()
    {
        return $this->network;
    }

    /**
     * @param $position
     *
     * @return Neuron|False
     */
    public function getNeuron($position)
    {
        return $this->network->filter(function (Neuron $neuron) use ($position) {
            return $neuron->getPosition() == $position;
        })->first();
    }

    /**
     * @return Specie
     */
    public function getSpecie()
    {
        return $this->specie;
    }

    public function removeGene(Gene $gene)
    {
        $gene->setGenome(null);
        $this->genes->removeElement($gene);
    }

    /**
     * @param int $fitness
     */
    public function setFitness($fitness)
    {
        $this->fitness = $fitness;
    }

    /**
     * @param ArrayCollection $genes
     */
    public function setGenes($genes)
    {
        $this->genes = $genes;
    }

    /**
     * @param int $globalRank
     */
    public function setGlobalRank($globalRank)
    {
        $this->globalRank = $globalRank;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param int $maxNeuron
     */
    public function setMaxNeuron($maxNeuron)
    {
        $this->maxNeuron = $maxNeuron;
    }

    /**
     * @param array $mutationRates
     */
    public function setMutationRates($mutationRates)
    {
        $this->mutationRates = $mutationRates;
    }

    /**
     * @param mixed $network
     */
    public function setNetwork($network)
    {
        $this->network = $network;
    }

    /**
     * @param Specie $specie
     */
    public function setSpecie($specie)
    {
        $this->specie = $specie;
    }
}
