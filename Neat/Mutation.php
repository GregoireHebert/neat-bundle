<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMInvalidArgumentException;
use Gheb\IOBundle\Aggregator\Aggregator ;

class Mutation
{
    public const PERTURB_CHANCE = 0.90;

    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var Pool
     */
    private $pool;
    /**
     * @var Aggregator
     */
    private $inputsAggregator;
    /**
     * @var Aggregator
     */
    private $outputsAggregator;

    /**
     * Manager constructor.
     *
     * @param EntityManager     $em
     * @param Aggregator   $inputsAggregator
     * @param Aggregator  $outputsAggregator
     */
    public function __construct(EntityManager $em, Aggregator  $inputsAggregator, Aggregator  $outputsAggregator)
    {
        $this->em                = $em;
        $this->inputsAggregator  = $inputsAggregator;
        $this->outputsAggregator = $outputsAggregator;
    }

    /**
     * Clone an entity and persist it
     *
     * @param $entity
     *
     * @throws ORMInvalidArgumentException
     *
     * @return mixed
     */
    public function cloneEntity($entity)
    {
        $clone = clone $entity;
        $this->em->detach($clone);

        return $clone;
    }

    /**
     * Create a new genome based on two genomes
     *
     * @param Genome $g1
     * @param Genome $g2
     *
     * @throws ORMInvalidArgumentException
     *
     * @return Genome
     */
    public function crossOver(Genome $g1, Genome $g2): Genome
    {
        if ($g2->getFitness() > $g1->getFitness()) {
            $temp = $g1;
            $g1   = $g2;
            $g2   = $temp;
        }

        $child = new Genome();

        $newInnovation = [];
        /** @var Gene $gene */
        foreach ($g2->getGenes() as $gene) {
            $newInnovation[$gene->getInnovation()] = $gene;
        }

        // add to the genome each gene contained in the first genome.
        // If the second genome has also an enabled gene for the same innovation number,
        // the gene added is randomly chosen between the two.
        foreach ($g1->getGenes() as $gene) {
            /** @var Gene $gene2 */
            $gene2 = $newInnovation[$gene->getInnovation()] ?? null;
            if (null !== $gene2 && random_int(1, 2) === 1 && true === $gene2->isEnabled()) {
                $child->addGene($this->cloneEntity($gene2));
            } else {
                $child->addGene($this->cloneEntity($gene));
            }
        }

        $child->setMaxNeuron(max($g1->getMaxNeuron(), $g2->getMaxNeuron()));
        $child->setMutationRates($g1->getMutationRates());

        return $child;
    }

    /**
     * Reverse enable state of a random gene
     *
     * @param Genome $genome
     * @param bool   $enabled // changes enabled ones to disabled when true, and changes disabled ones to enabled when false
     */
    public function enableDisableMutate(Genome $genome, $enabled = true): void
    {
        if ($genome->getGenes()->count() === 0) {
            return;
        }

        $candidates = new ArrayCollection();

        /** @var Gene $gene */
        foreach ($genome->getGenes() as $gene) {
            if ($gene->isEnabled() !== $enabled) {
                $candidates->add($gene);
            }
        }

        if ($candidates->count() === 0) {
            return;
        }

        $gene = $candidates->get(random_int(1, $candidates->count())-1);
        $gene->setEnabled(!$gene->isEnabled());
    }

    /**
     * Return a random neuron. A neuron can be an input, an output or an hidden node
     *
     * @param      $genes
     * @param bool $nonInput
     *
     * @return mixed
     */
    public function getRandomNeuron($genes, $nonInput = false)
    {
        $neurons = [];
        if (!$nonInput) {
            $inputsCount = $this->inputsAggregator->count();
            for ($i = 0; $i < $inputsCount; $i++) {
                $neurons[$i] = $i;
            }
        }

        $outputCount = $this->outputsAggregator->count();
        for ($j = 0; $j < $outputCount; $j++) {
            $neurons[Network::MAX_NODES+$j] = Network::MAX_NODES+$j;
        }

        /** @var Gene $gene */
        foreach ($genes as $gene) {
            if (!$nonInput || $gene->getInto() > $this->inputsAggregator->count()) {
                $neurons[$gene->getInto()] = $gene->getInto();
            }

            if (!$nonInput || $gene->getOut() > $this->inputsAggregator->count()) {
                $neurons[$gene->getOut()] = $gene->getOut();
            }
        }

        $r = random_int(1, \count($neurons)) -1;
        $n = array_values($neurons);

        return $n[$r];
    }

    /**
     * Has a chance to create a new gene in between two random in and out genes
     * or a chance to create a new link from a bias to the output
     *
     * @param Genome $genome
     * @param        $forceBias
     */
    public function linkMutate(Genome $genome, $forceBias): void
    {
        $rn1 = $this->getRandomNeuron($genome->getGenes());
        $rn2 = $this->getRandomNeuron($genome->getGenes(), true);

        // both are inputs, nothing to do
        $count = $this->inputsAggregator->count();
        if ($count > $rn1 && $count > $rn2) {
            return ;
        }

        // set as rn1 is an input and rn2 a nonInput
        if ($count > $rn2) {
            $tmp = $rn1;
            $rn1 = $rn2;
            $rn2 = $tmp;
        }

        $newLink = new Gene();
        $newLink->setInto($rn1);
        $newLink->setOut($rn2);

        if ($forceBias) {
            $newLink->setInto($this->inputsAggregator->count());
        }

        $exists = $genome->getGenes()->filter(function (Gene $gene) use ($newLink) {
            return $gene->getInto() === $newLink->getInto() && $gene->getOut() === $newLink->getOut();
        });

        if ($exists->count() > 0) {
            return;
        }

        $pool = $this->pool instanceof Pool ? $this->pool : $genome->getSpecie()->getPool();
        $newLink->setInnovation($pool->newInnovation());
        $newLink->setWeight(lcg_value()*4-2);

        $genome->addGene($newLink);
    }

    /**
     * Applies a mutation upon a genome
     *
     * @param Genome $genome
     * @param Pool   $pool   pool to innovate, when the genome hasn't been attached to it yet
     */
    public function mutate(Genome $genome, $pool = null): void
    {
        $this->pool = $pool;
        $rates      = $genome->mutationRates;

        // has a chance to reduce the mutation rate or rise it up
        foreach ($rates as $mutation=>$rate) {
            if (random_int(1, 2) === 1) {
                $genome->mutationRates[$mutation] = 0.95*$rate;
            } else {
                $genome->mutationRates[$mutation] = 1.05263*$rate;
            }
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
     * Adds a new node in between two existing nodes and disable the initial node in order to
     * get from A--C to A--B--C making the weight between A and B to 1.0
     * This new node is here to break the linearity in the network and expand the structure that will may evolve for speciation later.
     *
     * @param Genome $genome
     */
    public function nodeMutate(Genome $genome): void
    {
        if (0 === $genome->getGenes()->count()) {
            return;
        }

        /** @var Gene $gene */
        $gene = $genome->getGenes()->get(random_int(1, $genome->getGenes()->count())-1);
        if ($gene->isEnabled() === false) {
            return;
        }

        $gene->setEnabled(false);

        $pool  = $this->pool instanceof Pool ? $this->pool : $gene->getGenome()->getSpecie()->getPool();
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
