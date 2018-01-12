<?php

namespace Gheb\NeatBundle\Neat;

class Gene
{
    /**
     * @var bool
     */
    public $enabled = true;

    /**
     * @var Genome
     */
    public $genome;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $innovation = 0;

    /**
     * @var int
     */
    public $into = 0;

    /**
     * @var int
     */
    public $out = 0;

    /**
     * @var float
     */
    public $weight = 0.0;

    public function __clone()
    {
        if ($this->id) {
            $this->setId(null);
        }
    }

    /**
     * @return Genome
     */
    public function getGenome(): Genome
    {
        return $this->genome;
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
     * @return int
     */
    public function getInto(): int
    {
        return $this->into;
    }

    /**
     * @return int
     */
    public function getOut(): int
    {
        return $this->out;
    }

    /**
     * @return float
     */
    public function getWeight(): float
    {
        return $this->weight;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @param Genome $genome
     */
    public function setGenome(Genome $genome): void
    {
        $this->genome = $genome;
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
    public function setInnovation(int $innovation): void
    {
        $this->innovation = $innovation;
    }

    /**
     * @param int $into
     */
    public function setInto(int $into): void
    {
        $this->into = $into;
    }

    /**
     * @param int $out
     */
    public function setOut(int $out): void
    {
        $this->out = $out;
    }

    /**
     * @param float $weight
     */
    public function setWeight(float $weight): void
    {
        $this->weight = $weight;
    }
}
