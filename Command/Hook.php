<?php

namespace Gheb\NeatBundle\Command;

/**
 * Class Hook
 * @author  Grégoire Hébert <gregoire@opo.fr>
 * @package Gheb\NeatBundle\Command
 */
abstract class Hook
{
    /**
     * Function called by the NeatCommand
     * @return mixed
     */
    public function hook(){}
}