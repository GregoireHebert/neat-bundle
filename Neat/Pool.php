<?php

namespace Gheb\NeatBundle\Neat;

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
    public int $currentGenome = 0;
    public int $currentSpecies = 0;
    public int $generation = 0;
    public int $id;
    public int $innovation = 0;
    private Aggregator $inputAggregator;
    public int $maxFitness = 0;
    /**
     * @var array<Specie>
     */
    public array $species = [];

    public function __construct(public Mutation $mutation, public Aggregator $inputsAggregator, Aggregator $outputsAggregator)
    {
        $this->innovation      = $outputsAggregator->count();
        $this->inputAggregator = $inputsAggregator;
    }

    public function addSpecie(Specie $specie): void
    {
        $this->species[] = $specie;
        $specie->setPool($this);
    }

    /**
     * Add a genome to a specie.
     * If it does not belong to any existing specie according to its weight and evolution number, create a new specie.
     */
    public function addToSpecies(Genome $child): void
    {
        foreach ($this->species as $specie) {
            if ($this->sameSpecies($child, $specie->genomes[0])) {
                $specie->addGenome($child);
                return;
            }
        }

        $childSpecie = new Specie();

        $childSpecie->addGenome($child);
        $this->addSpecie($childSpecie);
    }

    /**
     * For a specie, has a chance X over 0.75 to crossover 2 random genomes and return it,
     * or to create a new genome based on a random existing one
     */
    public function breedChild(Specie $specie): Genome
    {
        if (lcg_value() < self::CROSSOVER_CHANCE) {
            $g1    = $specie->genomes[array_rand($specie->genomes)];
            $g2    = $specie->genomes[array_rand($specie->genomes)];
            // could be twice the same, not ideal
            $child = $this->mutation->crossOver($g1, $g2);
        } else {
            $g     = $specie->genomes[array_rand($specie->genomes)];
            $child = clone $g;
        }

        $this->mutation->mutate($child, $this);

        return $child;
    }

    /**
     * Create a Genome and set it's maxNeuron to the amount of inputs +1 and then applies a first mutation
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
     */
    public function cullSpecies(bool $cutToOne = false): void
    {
        foreach ($this->species as $specie) {
            $iterator = $specie->getGenomes();

            // order from lower to higher
            uasort($iterator, fn (Genome $first, Genome $second) => $first->getFitness() <=> $second->getFitness());

            $remaining = $cutToOne ? count($iterator)-1 : ceil(count($iterator) / 2);
            foreach ($iterator as $genome) {
                if (--$remaining>0) {
                    $specie->removeGenome($genome);
                    continue;
                }

                break;
            }
        }
    }

    /**
     * Calculate how far two genomes are different based on genes innovation number.
     * Each time a genome gene innovation is not found in the second genome genes innovation push the genome away from each other.
     */
    public function disjoint(Genome $g1, Genome $g2): float
    {
        $disjointGenes = 0;

        $gOneGenes = $g1->getGenes();
        $gTwoGenes = $g2->getGenes();

        $innovation1 = array_map(static fn (Gene $gene) => $gene->getInnovation(), $gOneGenes);
        $innovation2 = array_map(static fn (Gene $gene) => $gene->getInnovation(), $gTwoGenes);

        foreach ($gOneGenes as $gene) {
            if (!\in_array($gene->getInnovation(), $innovation2, true)) {
                $disjointGenes++;
            }
        }

        foreach ($gTwoGenes as $gene) {
            if (!\in_array($gene->getInnovation(), $innovation1, true)) {
                $disjointGenes++;
            }
        }

        return $disjointGenes / max(count($gOneGenes), count($gTwoGenes));
    }

    public function getCurrentGenome(): int
    {
        return $this->currentGenome;
    }

    public function getCurrentSpecies(): int
    {
        return $this->currentSpecies;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getInnovation(): int
    {
        return $this->innovation;
    }

    public function getInputAggregator(): Aggregator
    {
        return $this->inputAggregator;
    }

    public function getMaxFitness(): int
    {
        return $this->maxFitness;
    }

    public function getMutation(): Mutation
    {
        return $this->mutation;
    }

    public function getSpecies(): array
    {
        return $this->species;
    }

    public function getBestGenome(): Genome
    {
        foreach($this->species as $specie) {
            if ($specie->getTopFitness() === $this->getMaxFitness()) {
                return $specie->getBestGenome();
            }
        }

        throw new \RuntimeException('No genome reaches the max fitness');
    }

    /**
     * Create a all new generation
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
        /** @var array<Genome> $children */
        $children = [];

        // for each specie, if its average fitness is higher than the global population,
        // it has a chance to create a new child
        foreach ($this->species as $specie) {
            $breed = floor($specie->getAverageFitness() / $sum * self::POPULATION) - 1;

            for ($i = 0; $i < $breed; $i++) {
                $children[] = $this->breedChild($specie);
            }
        }

        // keep only the highest fitness genome of each specie
        $this->cullSpecies(true);

        // Since the creation of new child is based on top fitness species,
        // it does not contain as much population as the maximum defined.
        // Therefor we create a new child from a random specie until the max population is reached
        $nbSpecies = count($this->species);

        while ((count($children) + $nbSpecies) < self::POPULATION && $nbSpecies > 0) {
            $children[] = $this->breedChild($this->species[array_rand($this->species)]);
        }

        // we re-dispatch the new children through all the existing species (or new thanks to mutations)
        foreach ($children as $child) {
            $this->addToSpecies($child);
        }

        $this->generation++;
    }

    /**
     * Up innovation number of 1 and returns it
     */
    public function newInnovation(): int
    {
        return ++$this->innovation;
    }

    /**
     * Tries to get to the next genome. If we passed the number of genome available, we try a new specie.
     * If we passed the number of species available, create a new generation.
     */
    public function nextGenome(): void
    {
        $this->currentGenome++;

        if ($this->currentGenome > count($this->species[$this->currentSpecies]->getGenomes())-1) {
            $this->currentGenome = 0;
            $this->currentSpecies++;
            if ($this->currentSpecies > count($this->species)-1) {
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
        $global = [];

        foreach ($this->species as $specie) {
            $genomes = $specie->getGenomes();
            foreach ($genomes as $genome) {
                $global[] = $genome;
            }
        }

        // from lower to higher, because higher rank is better
        uasort($global, static fn (Genome $first, Genome $second) => $first->getFitness() <=> $second->getFitness());

        /** @var Genome $genome */
        foreach ($global as $rank => $genome) {
            $genome->setGlobalRank($rank+1);
        }
    }

    public function removeSpecie(Specie $specieToRemove): void
    {
        $specieToRemove->setPool(null);

        foreach ($this->species as $key => $specie) {
            if ($specie === $specieToRemove) {
                unset($this->species[$key]);
                return;
            }
        }
    }

    /**
     * Remove all species not having enough fitness for the pool previous max fitness
     */
    public function removeStaleSpecies(): void
    {
        foreach ($this->species as $specie) {
            $sorted = (array) $specie->getGenomes();

            // from higher to lower
            uasort($sorted, static fn (Genome $first, Genome $second) => $second->getFitness() <=> $first->getFitness());

            // if the highest fitness is higher than specie fitness, replace it
            if ($sorted[0]->getFitness() > $specie->getTopFitness()) {
                $specie->setTopFitness($sorted[0]->getFitness());
                $specie->setStaleness(0);
            } else {
                $specie->staleness++;
            }

            // if the staleness is above the max granted
            // or if the top fitness of the species is under the pool max fitness, then discard it.
            if ($specie->getStaleness() >= self::STALE_SPECIES || $specie->getTopFitness() < $this->getMaxFitness()) {
                $this->removeSpecie($specie);
            }
        }
    }

    /**
     * Remove all species having a fitness lower than the average
     */
    public function removeWeakSpecies(): void
    {
        $sum = $this->totalAverageFitness();

        foreach ($this->species as $specie) {
            if (floor($specie->getAverageFitness() / $sum * self::POPULATION) < 1) {
                $this->removeSpecie($specie);
            }
        }
    }

    /**
     * Return if two genome seems to be part of a same specie or not based on it's disjoint and weight.
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

        return !is_nan($add) && $add < self::DELTA_THRESHOLD;
    }

    public function setCurrentGenome(int $currentGenome): void
    {
        $this->currentGenome = $currentGenome;
    }

    public function setCurrentSpecies(int$currentSpecies): void
    {
        $this->currentSpecies = $currentSpecies;
    }

    public function setGeneration(int $generation): void
    {
        $this->generation = $generation;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setInnovation(int $innovation): void
    {
        $this->innovation = $innovation;
    }

    public function setInputAggregator(Aggregator $inputAggregator): void
    {
        $this->inputAggregator = $inputAggregator;
    }

    public function setMaxFitness(int $maxFitness): void
    {
        $this->maxFitness = $maxFitness;
    }

    public function setMutation(Mutation $mutation): void
    {
        $this->mutation = $mutation;
    }

    public function setSpecies(array $species): void
    {
        $this->species = $species;
    }

    /**
     * Return the sum of species average fitness
     */
    public function totalAverageFitness(): int
    {
        $total = 0;

        foreach ($this->species as $specie) {
            $total += $specie->getAverageFitness();
        }

        return $total;
    }

    /**
     * Return the weight difference between two genomes
     */
    public function weight(Genome $g1, Genome $g2): float
    {
        $innovation = [];

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

        // handle division by 0
        return (0 === $coincident) ? (($sum < 0) ? -INF : (($sum === 0) ? NAN : INF)) : $sum/$coincident;
    }
}
