<?php
namespace Gheb\NeatBundle;

use Gheb\NeatBundle\DependencyInjection\Compiler\HookCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NeatBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new HookCompilerPass());
    }
}
