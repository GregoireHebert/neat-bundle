<?php

namespace Gheb\NeatBundle\Neat;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Neuron
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var ArrayCollection
     */
    public $incoming;

    /**
     * @var int
     */
    public $position;

    /**
     * @var float
     */
    public $value = 0.0;

    /**
     * @var string
     */
    public $activationFunction;

    public function __construct()
    {
        $this->incoming = new ArrayCollection();
    }

    /**
     * @param Gene $gene
     */
    public function addIncoming(Gene $gene): void
    {
        if (!$this->incoming->contains($gene)) {
            $this->incoming->add($gene);
        }
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return ArrayCollection
     */
    public function getIncoming(): Collection
    {
        return $this->incoming;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * @param int $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @param ArrayCollection $incoming
     */
    public function setIncoming($incoming): void
    {
        $this->incoming = $incoming;
    }

    /**
     * @param int $position
     */
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @param float $value
     */
    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getActivationFunction(): string
    {
        return $this->activationFunction;
    }

    /**
     * @param string $activationFunction
     */
    public function setActivationFunction(string $activationFunction): void
    {
        $this->activationFunction = $activationFunction;
    }
}
