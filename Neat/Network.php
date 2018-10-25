<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Gheb\IOBundle\Inputs\AbstractInput;
use Gheb\IOBundle\Aggregator\Aggregator ;

class Network
{
    public const MAX_NODES = 1000000;
    public const ACTIVATION_FUNCTIONS = ['sigmoid', 'reLU', 'sin', 'cos', 'tanh', 'gaussian'];

    public function getRandomActivationFunction()
    {
        return array_rand(self::ACTIVATION_FUNCTIONS);
    }

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
            $genome->getNeuron($i)->setValue(self::normalize($inputs[$i]->getValue()));
        }

        /** @var Neuron $neuron */
        foreach ($genome->getNetwork() as $neuron) {
            $sum = 0;
            /** @var Gene $incoming */
            foreach ($neuron->getIncoming() as $incoming) {
                /** @var Neuron $into */
                $into = $genome->getNeuron($incoming->getInto());
                $sum += $incoming->getWeight() * $into->getValue();
            }

            if ($neuron->getIncoming()->count() > 0) {
                $neuron->setValue(self::{$neuron->getActivationFunction()}($sum));
            }
        }

        $triggeredOutputs = [];
        $outputCount = $outputsAggregator->count();
        for ($j = 0; $j < $outputCount; $j++) {
            if (self::sigmoid($genome->getNeuron(self::MAX_NODES + $j)->getValue()) > 0.5) {
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
     * @throws \Doctrine\ORM\ORMException
     */
    public static function generateNetwork(Genome $genome, Aggregator  $outputsAggregator, Aggregator  $inputsAggregator, EntityManager $em)
    {
        $inputCount = $inputsAggregator->count();
        for ($i = 0; $i < $inputCount; $i++) {
            $neuron = new Neuron();
            $neuron->setActivationFunction(self::getRandomActivationFunction());
            $neuron->setPosition($i);
            $genome->addNeuron($neuron);
        }

        $outputCount = $outputsAggregator->count();
        for ($j = 0; $j < $outputCount; $j++) {
            $neuron = new Neuron();
            $neuron->setActivationFunction(self::getRandomActivationFunction());
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

        $iteratorCount = $iterator->count();
        for ($i = 0; $i < $iteratorCount; $i++) {
            /** @var Gene $gene */
            $gene = $iterator->offsetGet($i);

            if ($gene->isEnabled()) {
                if (!$genome->getNeuron($gene->getOut()) instanceof Neuron) {
                    $neuron = new Neuron();
                    $neuron->setActivationFunction(self::getRandomActivationFunction());
                    $neuron->setPosition($gene->getOut());
                    $genome->addNeuron($neuron);
                }

                /** @var Neuron $neuron */
                $neuron = $genome->getNeuron($gene->getOut());
                $neuron->addIncoming($gene);

                if (!$genome->getNeuron($gene->getInto()) instanceof Neuron) {
                    $neuron = new Neuron();
                    $neuron->setActivationFunction(self::getRandomActivationFunction());
                    $neuron->setPosition($gene->getInto());
                    $genome->addNeuron($neuron);
                }

            }
        }

        $em->flush();
    }

    /**
     * Sigmoid function, return a value between 0 and 1.
     *
     * @param int $x
     *
     * @return float
     */
    public static function sigmoid($x): float
    {
        return  1 / (1 + exp(-$x));
    }

    /**
     * reLU function returns 0 if $x is < 0, otherwise returns x.
     *
     * @param $x
     *
     * @return float|int
     */
    public static function reLU($x)
    {
        return max(0, $x);
    }

    public static function sin($x): float
    {
        return \sin($x);
    }

    public static function cos($x): float
    {
        return \cos($x);
    }

    public static function tanh($x): float
    {
        return \tanh($x);
    }

    public static function gaussian($x): float
    {
        return \exp(-pow($x, 2));
    }

    public static function normalize($x): float
    {
        return $x/10;
    }
}
