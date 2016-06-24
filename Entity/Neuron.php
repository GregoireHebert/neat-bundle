<?php

namespace Gheb\NeatBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;

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

    public function __construct()
    {
        $this->incoming = new ArrayCollection();
    }

    /**
     * @param $gene
     */
    public function addIncoming($gene)
    {
        $this->incoming->add($gene);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ArrayCollection
     */
    public function getIncoming()
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
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param ArrayCollection $incoming
     */
    public function setIncoming($incoming)
    {
        $this->incoming = $incoming;
    }

    /**
     * @param mixed $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     * @param float $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
}
