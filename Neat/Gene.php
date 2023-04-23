<?php

namespace Gheb\NeatBundle\Neat;

class Gene
{
    private bool $enabled = true;

    private ?Genome $genome;

    private ?int $id;

    private int $innovation = 0;

    private int $into = 0;

    private int $out = 0;

    private float $weight = 0.0;

    public function __clone()
    {
        if ($this->id) {
            $this->id = null;
        }
    }

    public function getGenome(): Genome
    {
        return $this->genome;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getInnovation(): int
    {
        return $this->innovation;
    }

    public function getInto(): int
    {
        return $this->into;
    }

    public function getOut(): int
    {
        return $this->out;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setGenome(?Genome $genome): void
    {
        $this->genome = $genome;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setInnovation(int $innovation): void
    {
        $this->innovation = $innovation;
    }

    public function setInto(int $into): void
    {
        $this->into = $into;
    }

    public function setOut(int $out): void
    {
        $this->out = $out;
    }

    public function setWeight(float $weight): void
    {
        $this->weight = $weight;
    }
}
