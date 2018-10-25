<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Gheb\IOBundle\Aggregator\Aggregator;

/**
 * Class Pool regroups every species
 */
class Pool
{
    public const CROSSOVER_CHANCE = 0.75;
    public const STALE_SPECIES    = 15;
    public const POPULATION       = 50;
    public const DELTA_DISJOINT   = 2.0;
    public const DELTA_WEIGHT     = 0.4;
    public const DELTA_THRESHOLD  = 1.0;

    /**
     * @var int
     */
    public $currentGenome = 0;

    /**
     * @var int
     */
    public $currentSpecies = 0;

    /**
     * @var EntityManager
     */
    public $em;

    /**
     * @var int
     */
    public $generation = 0;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $innovation = 0;

    /**
     * @var Aggregator
     */
    private $inputAggregator;

    /**
     * @var int
     */
    public $maxFitness = 0;

    /**
     * @var Mutation
     */
    public $mutation;

    /**
     * @var ArrayCollection
     */
    public $species;

    /**
     * Pool constructor.
     *
     * @param EntityManager $em
     * @param Aggregator    $outputsAggregator
     * @param Aggregator    $inputsAggregator
     * @param Mutation      $mutation
     */
    public function __construct(EntityManager $em, Aggregator $outputsAggregator, Aggregator $inputsAggregator, Mutation $mutation)
    {
        $this->em              = $em;
        $this->innovation      = $outputsAggregator->count();
        $this->inputAggregator = $inputsAggregator;
        $this->mutation        = $mutation;

        $this->species = new ArrayCollection();
    }

    /**
     * Add a specie to the pool
     *
     * @param Specie $specie
     */
    public function addSpecie(Specie $specie): void
    {
        $this->species->add($specie);
        $specie->setPool($this);
    }

    /**
     * Add a genome to a specie. If it does not belong to any existing specie according to it's weight and evolution number, create a new specie.
     *
     * @param Genome $child
     */
    public function addToSpecies(Genome $child): void
    {
        $foundSpecie = false;

        /** @var Specie $specie */
        foreach ($this->species as $specie) {
            if ($this->sameSpecies($child, $specie->genomes->offsetGet($specie->genomes->getKeys()[0]))) {
                $specie->addGenome($child);
                $foundSpecie = true;
                break;
            }
        }

        if (!$foundSpecie) {
            $childSpecie = new Specie();

            $childSpecie->addGenome($child);
            $this->addSpecie($childSpecie);
        }
    }

    /**
     * For a specie, has a chance X over 0.75 to crossover 2 random genomes and return it,
     * or to create a new genome based on a random existing one
     *
     * @param Specie $specie
     *
     * @throws ORMInvalidArgumentException
     *
     * @return Genome
     */
    public function breedChild(Specie $specie): Genome
    {
        if (lcg_value() < self::CROSSOVER_CHANCE) {
            $g1    = $specie->genomes->offsetGet($specie->genomes->getKeys()[random_int(1, $specie->genomes->count())-1]);
            $g2    = $specie->genomes->offsetGet($specie->genomes->getKeys()[random_int(1, $specie->genomes->count())-1]);
            $child = $this->mutation->crossOver($g1, $g2);
        } else {
            $g     = $specie->genomes->offsetGet($specie->genomes->getKeys()[random_int(1, $specie->genomes->count())-1]);
            $child = $this->mutation->cloneEntity($g);
        }

        $this->mutation->mutate($child, $this);

        return $child;
    }

    /**
     * Create a Genome and set it's maxNeuron to the amount of inputs +1 and then applies a first mutation
     *
     * @use Mutation::mutate
     *
     * @return Genome
     */
    public function createBasicGenome(): Genome
    {
        $genome = new Genome();

        $genome->setMaxNeuron($this->inputAggregator->count());
        $this->mutation->mutate($genome, $this);

        return $genome;
    }

    /**
     * Remove the lower fitness half genomes of each specie or keep only the highest fitness genome of each specie.
     *
     * @param bool $cutToOne
     *
     */
    public function cullSpecies($cutToOne = false): void
    {
        $removedGenomes = [];

        /** @var Specie $specie */
        foreach ($this->species as $specie) {
            $iterator = $specie->getGenomes()->getIterator();

            // order from lower to higher
            $iterator->uasort(
                function (Genome $first, Genome $second) {
                    return $first->getFitness() < $second->getFitness() ? -1 : 1;
                }
            );

            /**
             * @var int $position
             * @var Genome $genome
             */
            $remaining  = $cutToOne ? $specie->getGenomes()->count()-1 : ceil($specie->getGenomes()->count() / 2);
            foreach ($iterator as $genome) {
                if (--$remaining>0) {
                    $specie->removeGenome($genome);
                    $removedGenomes[] = $genome;
                    continue;
                }

                break;
            }
        }

        $this->em->flush();

        foreach ($removedGenomes as $removedGenome) {
            $this->em->detach($removedGenome);
        }
        unset($removedGenomes);
    }

    /**
     * Calculate how far two genomes are different based on genes innovation number.
     * Each time a genome gene innovation is not found in the second genome genes innovation push the genome away from each other.
     *
     * @param Genome $g1
     * @param Genome $g2
     *
     * @return float
     */
    public function disjoint(Genome $g1, Genome $g2): float
    {
        $disjointGenes = 0;

        $innovation1 = [];
        /** @var Gene $gene */
        foreach ($g1->getGenes() as $gene) {
            $innovation1[] = $gene->getInnovation();
        }

        $innovation2 = [];
        /** @var Gene $gene */
        foreach ($g2->getGenes() as $gene) {
            $innovation2[] = $gene->getInnovation();
        }

        foreach ($g1->getGenes() as $gene) {
            if (!\in_array($gene->getInnovation(), $innovation2, false)) {
                $disjointGenes++;
            }
        }

        foreach ($g2->getGenes() as $gene) {
            if (!\in_array($gene->getInnovation(), $innovation1, false)) {
                $disjointGenes++;
            }
        }

        $max = max($g1->getGenes()->count(), $g2->getGenes()->count());

        return $disjointGenes / $max;
    }

    /**
     * @return int
     */
    public function getCurrentGenome(): int
    {
        return $this->currentGenome;
    }

    /**
     * @return int
     */
    public function getCurrentSpecies(): int
    {
        return $this->currentSpecies;
    }

    /**
     * @return EntityManager
     */
    public function getEm(): EntityManager
    {
        return $this->em;
    }

    /**
     * @return int
     */
    public function getGeneration(): int
    {
        return $this->generation;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getInnovation(): int
    {
        return $this->innovation;
    }

    /**
     * @return Aggregator
     */
    public function getInputAggregator(): Aggregator
    {
        return $this->inputAggregator;
    }

    /**
     * @return int
     */
    public function getMaxFitness(): int
    {
        return $this->maxFitness;
    }

    /**
     * @return Mutation
     */
    public function getMutation(): Mutation
    {
        return $this->mutation;
    }

    /**
     * @return ArrayCollection
     */
    public function getSpecies(): Collection
    {
        return $this->species;
    }

    /**
     * @return Genome|null
     */
    public function getBestGenome():? Genome
    {
        /** @var Specie $specie */
        $specie = $this->species->filter(function(Specie $specie){
            return $specie->getTopFitness() === $this->getMaxFitness();
        })->first();

        return $specie->getBestGenome();
    }

    /**
     * Create a all new generation
     *
     * @throws ORMInvalidArgumentException
     * @throws OptimisticLockException
     */
    public function newGeneration(): void
    {
        // Remove the lower fitness half genomes of each specie
        $this->cullSpecies();

        // give a rank based on it's fitness
        $this->rankGlobally();

        // Remove all species not having enough fitness for the pool previous max fitness
        $this->removeStaleSpecies();

        // give a rank based on it's fitness
        $this->rankGlobally();

        /** @var Specie $specie */
        foreach ($this->species as $specie) {
            $specie->calculateAverageFitness();
        }

        // Remove all species having a fitness lower than the average
        $this->removeWeakSpecies();

        $sum      = $this->totalAverageFitness();
        $children = new ArrayCollection();

        // for each specie, if it average fitness is higher than the global population,
        // it has a chance to create a new child
        foreach ($this->species as $specie) {
            $breed = floor($specie->getAverageFitness() / $sum * self::POPULATION) - 1;

            for ($i = 0; $i < $breed; $i++) {
                $children->add($this->breedChild($specie));
            }
        }

        // keep only the highest fitness genome of each specie
        $this->cullSpecies(true);

        // re-assign for re-indexation
        $this->species = new ArrayCollection($this->species->toArray());

        // Since the creation of new child is based on top fitness species,
        // it does not contains as much population as the maximum defined.
        // Therefor we create a new child from a random specie until the max population is reached
        while ($this->species->count() > 0 && ($children->count() + $this->species->count() > 0) && ($children->count() + $this->species->count()) < self::POPULATION) {
            $offsetRandom = random_int(0, $this->species->count()-1);
            $specie = $this->species->offsetGet($this->species->getKeys()[$offsetRandom]);
            $children->add($this->breedChild($specie));
        }

        /** @var Genome $child */
        // we re-dispatch the new children through all the existing species (or new thanks to mutations)
        foreach ($children as $child) {
            $this->addToSpecies($child);
        }

        $this->generation++;
    }

    /**
     * Up innovation number of 1 and returns it
     *
     * @return int
     */
    public function newInnovation(): int
    {
        return ++$this->innovation;
    }

    /**
     * Tries to get to the next genome. If we passed the number of genome available, we try a new specie.
     * If we passed the number of species available, create a new generation.
     *
     * @throws ORMInvalidArgumentException
     * @throws OptimisticLockException
     */
    public function nextGenome(): void
    {
        $this->currentGenome++;

        if ($this->currentGenome > $this->species->offsetGet($this->species->getKeys()[$this->currentSpecies])->getGenomes()->count()-1) {
            $this->currentGenome = 0;
            $this->currentSpecies++;
            if ($this->currentSpecies > $this->species->count()-1) {
                $this->newGeneration();
                $this->currentSpecies = 0;
            }
        }
    }

    /**
     * Higher is better
     */
    public function rankGlobally(): void
    {
        $global = new ArrayCollection();

        /** @var Specie $specie */
        foreach ($this->species as $specie) {
            $genomes = $specie->getGenomes();
            foreach ($genomes as $genome) {
                $global->add($genome);
            }
        }

        $iterator = $global->getIterator();
        // from lower to higher, because higher rank is better
        $iterator->uasort(
            function (Genome $first, Genome $second) {
                return $first->getFitness() < $second->getFitness() ? -1 : 1;
            }
        );

        /** @var Genome $genome */
        foreach ($iterator as $rank => $genome) {
            $genome->setGlobalRank($rank+1);
        }
    }

    /**
     * @param Specie $specie
     */
    public function removeSpecie(Specie $specie): void
    {
        $specie->setPool(null);
        $this->species->removeElement($specie);
    }

    /**
     * Remove all species not having enough fitness for the pool previous max fitness
     *
     * @throws ORMInvalidArgumentException
     * @throws OptimisticLockException
     */
    public function removeStaleSpecies(): void
    {
        $removedSpecies = [];

        /**
         * @var int
         * @var Specie $specie
         */
        foreach ($this->species as $key => $specie) {
            $iterator = $specie->getGenomes()->getIterator();

            // from higher to lower
            $iterator->uasort(
                function (Genome $first, Genome $second) {
                    return $first->getFitness() > $second->getFitness() ? -1 : 1;
                }
            );

            $sorted = new ArrayCollection($iterator->getArrayCopy());

            // if the highest fitness is higher than specie fitness, replace it
            if ($sorted->offsetGet($sorted->getKeys()[0])->getFitness() > $specie->getTopFitness()) {
                $specie->setTopFitness($sorted->offsetGet($sorted->getKeys()[0])->getFitness());
                $specie->setStaleness(0);
            } else {
                $specie->staleness++;
            }

            // if the staleness is above the max granted
            // or if the top fitness of the species is under the pool max fitness, then discard it.
            if ($specie->getStaleness() >= self::STALE_SPECIES ||
                $specie->getTopFitness() < $this->getMaxFitness()
            ) {
                $this->removeSpecie($specie);
                $removedSpecies[] = $specie;
            }
        }

        $this->species = $this->species->filter(
            function($specie) {
                return $specie instanceof Specie;
            }
        );

        $this->em->flush();

        foreach ($removedSpecies as $removedSpecy) {
            $this->em->detach($removedSpecy);
        }
        unset($removedSpecies);
    }

    /**
     * Remove all species having a fitness lower than the average
     *
     * @throws ORMInvalidArgumentException
     * @throws OptimisticLockException
     */
    public function removeWeakSpecies(): void
    {
        $removedSpecies = [];
        $sum = $this->totalAverageFitness();

        /** @var Specie $specie */
        foreach ($this->species as $specie) {
            $breed = floor($specie->getAverageFitness() / $sum * self::POPULATION);
            if ($breed < 1) {
                $this->removeSpecie($specie);
                $removedSpecies[] = $specie;
            }
        }

        $this->species = $this->species->filter(
            function($specie) {
                return $specie instanceof Specie;
            }
        );

        $this->em->flush();

        foreach ($removedSpecies as $removedSpecy) {
            $this->em->detach($removedSpecy);
        }
        unset($removedSpecies);
    }

    /**
     * Return if two genome seems to be part of a same specie or not based on it's desjoint and weight.
     *
     * @param $genome1
     * @param $genome2
     *
     * @return bool
     */
    public function sameSpecies($genome1, $genome2): bool
    {
        $dd = self::DELTA_DISJOINT * $this->disjoint($genome1, $genome2);
        $dw = self::DELTA_WEIGHT * $this->weight($genome1, $genome2);

        $add = $dd + $dw;

        return is_nan($add) ? false : ($add < self::DELTA_THRESHOLD);
    }

    /**
     * @param int $currentGenome
     */
    public function setCurrentGenome($currentGenome): void
    {
        $this->currentGenome = $currentGenome;
    }

    /**
     * @param int $currentSpecies
     */
    public function setCurrentSpecies($currentSpecies): void
    {
        $this->currentSpecies = $currentSpecies;
    }

    /**
     * @param EntityManager $em
     */
    public function setEm($em): void
    {
        $this->em = $em;
    }

    /**
     * @param int $generation
     */
    public function setGeneration($generation): void
    {
        $this->generation = $generation;
    }

    /**
     * @param int $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @param int $innovation
     */
    public function setInnovation($innovation): void
    {
        $this->innovation = $innovation;
    }

    /**
     * @param Aggregator $inputAggregator
     */
    public function setInputAggregator($inputAggregator): void
    {
        $this->inputAggregator = $inputAggregator;
    }

    /**
     * @param int $maxFitness
     */
    public function setMaxFitness($maxFitness): void
    {
        $this->maxFitness = $maxFitness;
    }

    /**
     * @param Mutation $mutation
     */
    public function setMutation($mutation): void
    {
        $this->mutation = $mutation;
    }

    /**
     * @param ArrayCollection $species
     */
    public function setSpecies($species): void
    {
        $this->species = $species;
    }

    /**
     * Return the sum of species average fitness
     *
     * @return int
     */
    public function totalAverageFitness(): int
    {
        $total = 0;
        /** @var Specie $specie */
        foreach ($this->species as $specie) {
            $total += $specie->getAverageFitness();
        }

        return $total;
    }

    /**
     * Return the weight difference between two genomes
     *
     * @param Genome $g1
     * @param Genome $g2
     *
     * @return float
     */
    public function weight(Genome $g1, Genome $g2): float
    {
        $innovation = [];

        /** @var Gene $gene */
        /** @var Gene $gene2 */
        foreach ($g2->getGenes() as $gene) {
            $innovation[$gene->getInnovation()] = $gene->getWeight();
        }

        $sum        = 0;
        $coincident = 0;

        foreach ($g1->getGenes() as $gene) {
            if (isset($innovation[$gene->getInnovation()])) {
                $sum += abs($gene->getWeight() - $innovation[$gene->getInnovation()]);
                $coincident++;
            }
        }

        // on php7 a division by zero (forced) returns INF Or before that, it returned false.
        // if INF is always > to any number, false is not.
        return (0 === $coincident) ? (($sum < 0) ? -INF : (($sum === 0) ? NAN : INF)) : $sum/$coincident;
    }
}
