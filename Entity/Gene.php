<?php

namespace Gheb\NeatBundle\Entity;

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
    public function getGenome()
    {
        return $this->genome;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getInnovation()
    {
        return $this->innovation;
    }

    /**
     * @return int
     */
    public function getInto()
    {
        return $this->into;
    }

    /**
     * @return int
     */
    public function getOut()
    {
        return $this->out;
    }

    /**
     * @return float
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * @param Genome $genome
     */
    public function setGenome($genome)
    {
        $this->genome = $genome;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param int $innovation
     */
    public function setInnovation($innovation)
    {
        $this->innovation = $innovation;
    }

    /**
     * @param int $into
     */
    public function setInto($into)
    {
        $this->into = $into;
    }

    /**
     * @param int $out
     */
    public function setOut($out)
    {
        $this->out = $out;
    }

    /**
     * @param float $weight
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
    }
}
