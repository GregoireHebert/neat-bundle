<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Class Species
 *
 * @author  Grégoire Hébert <gregoire@opo.fr>
 */
class Specie
{
    /**
     * @var int
     */
    public $averageFitness = 0;

    /**
     * @var ArrayCollection
     */
    public $genomes;

    /**
     * @var int
     */
    public $id;

    /**
     * @var Pool
     */
    public $pool;

    /**
     * @var int
     */
    public $staleness = 0;

    /**
     * @var int
     */
    public $topFitness = 0;

    public function __construct()
    {
        $this->genomes = new ArrayCollection();
    }

    public function addGenome(Genome $genome): void
    {
        $this->genomes->add($genome);
        $genome->setSpecie($this);
    }

    /**
     * Calculate the average fitness based on genome global rank
     */
    public function calculateAverageFitness(): void
    {
        $total = 0;
        /** @var Genome $genome */
        foreach ($this->genomes as $genome) {
            $total += $genome->getGlobalRank();
        }

        $this->setAverageFitness($total / $this->genomes->count());
    }

    /**
     * @return int
     */
    public function getAverageFitness(): int
    {
        return $this->averageFitness;
    }

    /**
     * @return ArrayCollection
     */
    public function getGenomes(): Collection
    {
        return $this->genomes;
    }

    /**
     * @return Genome|null
     */
    public function getBestGenome():? Genome
    {
        /** var Genome $genome */
        return $this->getGenomes()->filter(function(Genome $genome){
            return $genome->getFitness() === $this->getTopFitness();
        })->first();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Pool
     */
    public function getPool(): Pool
    {
        return $this->pool;
    }

    /**
     * @return int
     */
    public function getStaleness(): int
    {
        return $this->staleness;
    }

    /**
     * @return int
     */
    public function getTopFitness(): int
    {
        return $this->topFitness;
    }

    /**
     * @param Genome $genome
     */
    public function removeGenome(Genome $genome): void
    {
        $genome->setSpecie(null);
        $this->genomes->removeElement($genome);
    }

    /**
     * @param int $averageFitness
     */
    public function setAverageFitness($averageFitness): void
    {
        $this->averageFitness = $averageFitness;
    }

    /**
     * @param ArrayCollection $genomes
     */
    public function setGenomes($genomes): void
    {
        $this->genomes = $genomes;
    }

    /**
     * @param int $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @param Pool $pool
     */
    public function setPool($pool): void
    {
        $this->pool = $pool;
    }

    /**
     * @param int $staleness
     */
    public function setStaleness($staleness): void
    {
        $this->staleness = $staleness;
    }

    /**
     * @param int $topFitness
     */
    public function setTopFitness($topFitness): void
    {
        $this->topFitness = $topFitness;
    }
}
