<?php

namespace Gheb\NeatBundle\Neat;

use Gheb\IOBundle\Aggregator\Aggregator ;

class Mutation
{
    private const PERTURB_CHANCE = 0.90;

    private ?Pool $pool = null;

    private Aggregator $inputsAggregator;

    private Aggregator $outputsAggregator;

    public function __construct(Aggregator  $inputsAggregator, Aggregator  $outputsAggregator)
    {
        $this->inputsAggregator  = $inputsAggregator;
        $this->outputsAggregator = $outputsAggregator;
    }

    /**
     * Create a new genome based on two genomes
     */
    public function crossOver(Genome $g1, Genome $g2): Genome
    {
        if ($g2->getFitness() > $g1->getFitness()) {
            return $this->crossOver($g2, $g1);
        }

        /** @var array<Gene> $newInnovation */
        $newInnovation = [];
        $child = new Genome();

        foreach ($g2->getGenes() as $gene) {
            $newInnovation[$gene->getInnovation()] = $gene;
        }

        // Add to the new genome each gene contained in the first genome.
        // If the second genome has also an enabled gene for the same innovation number,
        // the gene added is randomly chosen between the two.
        foreach ($g1->getGenes() as $gene) {
            $gene2 = $newInnovation[$gene->getInnovation()] ?? null;
            if (null !== $gene2 && lcg_value() < .5 && $gene2->isEnabled()) {
                $child->addGene(clone $gene2);
            } else {
                $child->addGene(clone $gene);
            }
        }

        $child->setMaxNeuron(max($g1->getMaxNeuron(), $g2->getMaxNeuron()));
        $child->setMutationRates($g1->getMutationRates());

        return $child;
    }

    /**
     * Reverse enable state of a random gene
     * @param bool $enabled // changes enabled ones to disabled when true, and changes disabled ones to enabled when false
     */
    public function enableDisableMutate(Genome $genome, bool $enabled = true): void
    {
        $candidates = array_filter($genome->getGenes(), static fn (Gene $gene) => $gene->isEnabled() !== $enabled);

        if (count($candidates) === 0) {
            return;
        }

        $gene = $candidates[array_rand($candidates)];
        $gene->setEnabled(!$gene->isEnabled());
    }

    /**
     * Return a random neuron. A neuron can be an input, an output or a hidden node
     */
    public function getRandomNeuron(array $genes, bool $ignoreInput = false): int
    {
        $neurons = [];
        $inputsCount = $this->inputsAggregator->count();

        if (!$ignoreInput) {
            for ($i = 0; $i < $inputsCount; $i++) {
                $neurons[$i] = $i;
            }
        }

        $outputCount = $this->outputsAggregator->count();
        for ($j = 0; $j < $outputCount; $j++) {
            $neurons[Network::MAX_NODES+$j] = Network::MAX_NODES+$j;
        }

        foreach ($genes as $gene) {
            if (!$ignoreInput || $gene->getInto() > $inputsCount) {
                $neurons[$gene->getInto()] = $gene->getInto();
            }

            if (!$ignoreInput || $gene->getOut() > $inputsCount) {
                $neurons[$gene->getOut()] = $gene->getOut();
            }
        }

        return $neurons[array_rand($neurons)];
    }

    /**
     * Has a chance to create a new gene in between two random in and out genes
     * or a chance to create a new link from a bias to the output
     */
    public function linkMutate(Genome $genome, bool $forceBias): void
    {
        $rn1 = $this->getRandomNeuron($genome->getGenes());
        $rn2 = $this->getRandomNeuron($genome->getGenes(), true);

        // if they are inputs only, nothing to do
        $inputPositionMax = $this->inputsAggregator->count();
        if ($inputPositionMax > $rn1 && $inputPositionMax > $rn2) {
            return;
        }

        $newLink = new Gene();
        $newLink->setInto($rn1);
        $newLink->setOut($rn2);

        if ($forceBias) {
            $newLink->setInto($inputPositionMax);
        }

        $thisNewLinkExistAlready = 0 < count(array_filter(
            $genome->getGenes(),
            static fn (Gene $gene) => $gene->getInto() === $newLink->getInto() && $gene->getOut() === $newLink->getOut()
        ));

        if ($thisNewLinkExistAlready) {
            // too bad, no variation this time
            return;
        }

        if (!$this->pool instanceof Pool) {
            throw new \LogicException('Expected Pool, none found');
        }

        $newLink->setInnovation($this->pool->newInnovation());
        $newLink->setWeight(lcg_value()*4-2);

        $genome->addGene($newLink);
    }

    private function pointMutate(Genome $genome): void
    {
        $step = $genome->mutationRates['step'];

        foreach ($genome->getGenes() as $gene) {
            if (lcg_value() < self::PERTURB_CHANCE) {
                /** @var Gene $gene */
                $gene->setWeight($gene->getWeight() + lcg_value() * $step * 2 - $step);
            } else {
                $gene->setWeight(lcg_value()*4-2);
            }
        }
    }

    /**
     * Applies a mutation upon a genome
     *
     * @param Genome $genome
     * @param ?Pool $pool   pool to innovate, when the genome hasn't been attached to it yet
     */
    public function mutate(Genome $genome, ?Pool $pool = null): void
    {
        $this->pool = $pool;
        $rates      = $genome->mutationRates;

        // has a chance to reduce the mutation rate or rise it up
        foreach ($rates as $mutation => $rate) {
            if (lcg_value() < .5) {
                $genome->mutationRates[$mutation] = 0.95*$rate;
            } else {
                $genome->mutationRates[$mutation] = 1.05263*$rate;
            }
        }

        if (lcg_value() < $genome->mutationRates['connections']) {
            $this->pointMutate($genome);
        }

        // has a chance to create a new link in between 2 input and output nodes
        $linkRate = $genome->mutationRates['link'];
        while (0 < $linkRate) {
            if (lcg_value() < $linkRate) {
                $this->linkMutate($genome, false);
            }
            --$linkRate;
        }

        // has a chance to create a new link in between a bias node and an output nodes
        $biasRate = $genome->mutationRates['bias'];
        while (0 < $biasRate) {
            if (lcg_value() < $biasRate) {
                $this->linkMutate($genome, true);
            }
            --$biasRate;
        }

        // has a chance to split a link in adding a new node in between
        $nodeRate = $genome->mutationRates['node'];
        while (0 < $nodeRate) {
            if (lcg_value() < $nodeRate) {
                $this->nodeMutate($genome);
            }
            --$nodeRate;
        }

        // has a chance to enable a disabled gene
        $enableRate = $genome->mutationRates['enable'];
        while (0 < $enableRate) {
            if (lcg_value() < $enableRate) {
                $this->enableDisableMutate($genome);
            }
            --$enableRate;
        }

        // has a chance to disable an enabled gene
        $disableRate = $genome->mutationRates['disable'];
        while (0 < $disableRate) {
            if (lcg_value() < $disableRate) {
                $this->enableDisableMutate($genome, false);
            }
            --$disableRate;
        }
    }

    /**
     * Adds a new node in between two existing nodes and disable the initial link in order to
     * get from A--C to A--B--C making the weight between A and B to 1.0
     * This new node is here to break the linearity in the network and expand the structure that will may evolve for speciation later.
     */
    public function nodeMutate(Genome $genome): void
    {
        if (0 === count($genome->getGenes())) {
            // weirdly nothing to mutate here
            trigger_error('no genes to mutate for genome', E_USER_NOTICE);
            return;
        }

        $gene = $genome->getGenes()[array_rand($genome->getGenes())];
        if ($gene->isEnabled() === false) {
            return;
        }

        $gene->setEnabled(false);

        $pool  = $this->pool ?? $gene->getGenome()->getSpecie()->getPool();
        $clone = clone $gene;

        $clone->setOut($genome->getMaxNeuron());
        $clone->setWeight(1.0);
        $clone->setInnovation($pool->newInnovation());
        $clone->setEnabled(true);

        $genome->addGene($clone);

        $clone2 = clone $gene;

        $clone2->setInto($genome->getMaxNeuron());
        $clone2->setInnovation($pool->newInnovation());
        $clone2->setEnabled(true);

        $genome->addGene($clone2);

        $genome->setMaxNeuron($genome->getMaxNeuron()+1);
    }
}
