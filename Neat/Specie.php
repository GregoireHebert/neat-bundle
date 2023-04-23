<?php

namespace Gheb\NeatBundle\Neat;

class Specie
{
    public int $averageFitness = 0;

    /**
     * @var array<Genome>
     */
    public array $genomes = [];

    public int $id;

    public ?Pool $pool;

    public int $staleness = 0;

    public int $topFitness = 0;

    public function addGenome(Genome $genome): void
    {
        $this->genomes[] = $genome;
        $genome->setSpecie($this);
    }

    /**
     * Calculate the average fitness based on genome global rank
     */
    public function calculateAverageFitness(): void
    {
        $total = 0;
        foreach ($this->genomes as $genome) {
            $total += $genome->getGlobalRank();
        }

        $this->setAverageFitness($total / count($this->genomes));
    }

    public function getAverageFitness(): int
    {
        return $this->averageFitness;
    }

    public function getGenomes(): array
    {
        return $this->genomes;
    }

    public function getBestGenome(): ?Genome
    {
        /** var Genome $genome */
       foreach ($this->getGenomes() as $genome) {
           if ($genome->getFitness() === $this->getTopFitness()) {
               return $genome;
           }
       }

       return null;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPool(): Pool
    {
        return $this->pool;
    }

    public function getStaleness(): int
    {
        return $this->staleness;
    }

    public function getTopFitness(): int
    {
        return $this->topFitness;
    }

    public function removeGenome(Genome $genomeToRemove): void
    {
        $genomeToRemove->setSpecie(null);

        foreach ($this->genomes as $key => $genome) {
            if ($genome === $genomeToRemove) {
                unset($this->genomes[$key]);
                return;
            }
        }
    }

    public function setAverageFitness(int $averageFitness): void
    {
        $this->averageFitness = $averageFitness;
    }

    /**
     * @param array<Genome> $genomes
     */
    public function setGenomes(array $genomes): void
    {
        $this->genomes = $genomes;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setPool(?Pool $pool): void
    {
        $this->pool = $pool;
    }

    public function setStaleness(int $staleness): void
    {
        $this->staleness = $staleness;
    }

    public function setTopFitness(int $topFitness): void
    {
        $this->topFitness = $topFitness;
    }
}
