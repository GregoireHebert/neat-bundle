<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Gheb\IOBundle\Inputs\AbstractInput;
use Gheb\IOBundle\Aggregator\Aggregator ;

class Network
{
    public const MAX_NODES = 1000000;

    /**
     * Receive inputs and evaluate them in function of their values
     *
     * @param Genome            $genome
     * @param AbstractInput[]   $inputs
     * @param Aggregator  $outputsAggregator
     * @param Aggregator   $inputsAggregator
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function evaluate(Genome $genome, $inputs, Aggregator  $outputsAggregator, Aggregator  $inputsAggregator): array
    {
        if ($inputsAggregator->count() !== \count($inputs)) {
            throw new \InvalidArgumentException('Incorrect number of neural network inputs');
        }

        $inputCount = $inputsAggregator->count();
        for ($i = 0; $i < $inputCount; $i++) {
            $genome->getNeuron($i)->setValue($inputs[$i]->getValue());
        }

        /** @var Neuron $neuron */
        foreach ($genome->getNetwork() as $neuron) {
            $sum = 0;
            /** @var Gene $incoming */
            foreach ($neuron->getIncoming() as $incoming) {
                /** @var Neuron $other */
                $other = $genome->getNeuron($incoming->getInto());
                $sum += $incoming->getWeight() * $other->getValue();
            }

            if ($neuron->getIncoming()->count() > 0) {
                $neuron->setValue(self::sigmoid($sum));
            }
        }

        $triggeredOutputs = [];
        $outputCount = $outputsAggregator->count();
        for ($j = 0; $j < $outputCount; $j++) {
            if ($genome->getNeuron(self::MAX_NODES + $j)->getValue() > 0) {
                $triggeredOutputs[] = $outputsAggregator->aggregate->offsetGet($j);
            }
        }

        return $triggeredOutputs;
    }

    /**
     * Structure a network of neurons based on genes in and out
     *
     * @param Genome        $genome
     * @param Aggregator    $outputsAggregator
     * @param Aggregator    $inputsAggregator
     * @param EntityManager $em
     *
     * @throws OptimisticLockException
     */
    public static function generateNetwork(Genome $genome, Aggregator  $outputsAggregator, Aggregator  $inputsAggregator, EntityManager $em)
    {
        $inputCount = $inputsAggregator->count();
        for ($i = 0; $i < $inputCount; $i++) {
            $neuron = new Neuron();
            $neuron->setPosition($i);
            $genome->addNeuron($neuron);
        }

        $outputCount = $outputsAggregator->count();
        for ($j = 0; $j < $outputCount; $j++) {
            $neuron = new Neuron();
            $neuron->setPosition(self::MAX_NODES + $j);
            $genome->addNeuron($neuron);
        }

        // from lower to higher
        $iterator = $genome->getGenes()->getIterator();
        $iterator->uasort(
            function (Gene $first, Gene $second) {
                return $first->getOut() < $second->getOut() ? -1 : 1;
            }
        );

        $em->flush();

        $iteratorCount = $iterator->count();
        for ($i = 0; $i < $iteratorCount; $i++) {
            /** @var Gene $gene */
            $gene = $iterator->offsetGet($i);

            if ($gene->isEnabled()) {
                if (!$genome->getNeuron($gene->getOut()) instanceof Neuron) {
                    $neuron = new Neuron();
                    $neuron->setPosition($gene->getOut());
                    $genome->addNeuron($neuron);
                }

                /** @var Neuron $neuron */
                $neuron = $genome->getNeuron($gene->getOut());
                $neuron->getIncoming()->add($gene);

                if (!$genome->getNeuron($gene->getInto()) instanceof Neuron) {
                    $neuron = new Neuron();
                    $neuron->setPosition($gene->getInto());
                    $genome->addNeuron($neuron);
                }

                $em->flush();
            }
        }
    }

    /**
     * return sigmoidal result
     *
     * @param $x
     *
     * @return float
     */
    public static function sigmoid($x): float
    {
        return 2/(1+exp(-4.9*$x))-1;
    }
}
