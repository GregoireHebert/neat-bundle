<?php

namespace Gheb\NeatBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class HookCompilerPass
 * @author  Grégoire Hébert <gregoire@opo.fr>
 * @package Gheb\NeatBundle\DependencyInjection\Compiler
 */
class HookCompilerPass implements CompilerPassInterface
{

    /**
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        $neatCommand = $container->getDefinition('gheb.neat.command');

        $beforeInitHooks         = array_keys($container->findTaggedServiceIds('gheb.neat.hook.onBeforeInit'));
        $beforeNewRunHooks       = array_keys($container->findTaggedServiceIds('gheb.neat.hook.onBeforeNewRun'));
        $afterEvaluationHooks    = array_keys($container->findTaggedServiceIds('gheb.neat.hook.onAfterEvaluation'));
        $stopEvaluationHooks     = array_keys($container->findTaggedServiceIds('gheb.neat.hook.stopEvaluation'));
        $getFitnessHooks         = array_keys($container->findTaggedServiceIds('gheb.neat.hook.getFitness'));
        $nextGenomeCriteriaHooks = array_keys($container->findTaggedServiceIds('gheb.neat.hook.nextGenomeCriteria'));

        if (empty($nextGenomeCriteriaHooks)) {
            throw new \Exception('You must define a Hook for the next genome criteria');
        }

        if (empty($getFitnessHooks)) {
            throw new \Exception('You must define a Hook returning the current evaluation fitness');
        }

        foreach ($beforeInitHooks as $beforeInitHook) {
            $hook = new Reference($beforeInitHook);
            $neatCommand->addMethodCall('addBeforeInitHooks', array($hook));
        }

        foreach ($beforeNewRunHooks as $beforeNewRunHook) {
            $hook = new Reference($beforeNewRunHook);
            $neatCommand->addMethodCall('addBeforeNewRunHooks', array($hook));
        }

        foreach ($afterEvaluationHooks as $afterEvaluationHook) {
            $hook = new Reference($afterEvaluationHook);
            $neatCommand->addMethodCall('addAfterEvaluationHooks', array($hook));
        }

        foreach ($stopEvaluationHooks as $stopEvaluationHook) {
            $hook = new Reference($stopEvaluationHook);
            $neatCommand->addMethodCall('addStopEvaluationHook', array($hook));
        }

        foreach ($getFitnessHooks as $getFitnessHook) {
            $hook = new Reference($getFitnessHook);
            $neatCommand->addMethodCall('addGetFitnessHook', array($hook));
        }

        foreach ($nextGenomeCriteriaHooks as $nextGenomeCriteriaHook) {
            $hook = new Reference($nextGenomeCriteriaHook);
            $neatCommand->addMethodCall('addNextGenomeCriteriaHook', array($hook));
        }
    }
}