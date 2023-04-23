<?php

namespace Gheb\NeatBundle\Neat;

class Genome
{
    private int $fitness = 0;

    /** @var array<Gene>  */
    private array $genes = [];

    private int $globalRank = 0;

    private ?int $id;

    private int $maxNeuron = 0;

    /** @var array<string, float>  */
    public array $mutationRates = [];

    /** @var array<Neuron>  */
    private array $network = [];

    private ?Specie $specie;

    public function __construct()
    {
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
            $this->setNetwork([]);
            $this->setMaxNeuron(0);
            $this->setGlobalRank(0);

            $genesClone = [];
            foreach ($this->getGenes() as $gene) {
                $geneClone = clone $gene;
                $geneClone->setGenome($this);

                $genesClone[] = $geneClone;
            }

            $this->setGenes($genesClone);
        }
    }

    public function addGene(Gene $gene): void
    {
        $this->genes[] = $gene;
        $gene->setGenome($this);
    }

    public function addNeuron(Neuron $neuron): void
    {
        $this->network[] = $neuron;
    }

    public function getFitness(): int
    {
        return $this->fitness;
    }

    /**
     * @return array<Gene>
     */
    public function getGenes(): array
    {
        return $this->genes;
    }

    public function getGlobalRank(): int
    {
        return $this->globalRank;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMaxNeuron(): int
    {
        return $this->maxNeuron;
    }

    public function getMutationRates(): array
    {
        return $this->mutationRates;
    }

    public function getNetwork(): array
    {
        return $this->network;
    }

    public function getNeuron(int $position): Neuron
    {
        foreach($this->network as $neuron) {
            if ($neuron->getPosition() === $position) {
                return $neuron;
            }
        }

        throw new \LogicException("no Neuron at position $position");
    }

    public function getSpecie(): Specie
    {
        return $this->specie;
    }

    public function removeGene(Gene $geneToRemove): void
    {
        $geneToRemove->setGenome(null);
        $this->genes = array_filter($this->genes, static fn($gene)  => $gene !== $geneToRemove);
    }

    public function setFitness(int $fitness): void
    {
        $this->fitness = $fitness;
    }

    public function setGenes(array $genes): void
    {
        $this->genes = $genes;
    }

    public function setGlobalRank(int $globalRank): void
    {
        $this->globalRank = $globalRank;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setMaxNeuron(int $maxNeuron): void
    {
        $this->maxNeuron = $maxNeuron;
    }

    public function setMutationRates(array $mutationRates): void
    {
        $this->mutationRates = $mutationRates;
    }

    public function setNetwork(array $network): void
    {
        $this->network = $network;
    }

    public function setSpecie(?Specie $specie): void
    {
        $this->specie = $specie;
    }
}
