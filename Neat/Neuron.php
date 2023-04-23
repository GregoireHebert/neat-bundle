<?php

namespace Gheb\NeatBundle\Neat;

class Neuron
{
    public int $id;

    /** @var array<Gene>  */
    public array $incoming = [];

    public int $position;

    public float $value = 0.0;

    public string $activationFunction;

    /**
     * @param Gene $gene
     */
    public function addIncoming(Gene $gene): void
    {
        $this->incoming[] = $gene;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIncoming(): array
    {
        return $this->incoming;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setIncoming(array $incoming): void
    {
        $this->incoming = $incoming;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function getActivationFunction(): string
    {
        return $this->activationFunction;
    }

    public function setActivationFunction(string $activationFunction): void
    {
        $this->activationFunction = $activationFunction;
    }
}
