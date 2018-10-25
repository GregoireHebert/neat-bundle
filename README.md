# NeatBundle

[![Version](https://img.shields.io/badge/version-1.0-blue.svg)](https://img.shields.io/badge/version-1.0-blue.svg)
&nbsp;
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%205.6-lightgrey.svg)](https://php.net/)
&nbsp;

The NeatBundle is an implementation of an genetic algorithm, based on the neuro evolution by augmenting topologies.

learn more about [Evolving Neural Networks through Augmenting Topologies](http://nn.cs.utexas.edu/downloads/papers/stanley.ec02.pdf)

## Installation

```shell
$ composer install gheb/neat-bundle
```

## Inputs & Outputs

In such a system, values of inputs are computed to tend toward one or more outputs.
To do so, and keep it isolated, `gheb/neat-bundle` comes with `gheb/io-bundle`.

First define your inputs:

```php
<?php

namespace Acme\DemoBundle\IO\Inputs;

use Gheb\IOBundle\Inputs\AbstractInput;

class MyInput extends AbstractInput
{
    public function getName()
    {
		// the name is here to allow you calling it from the console
		// php bin/console gheb:io:input MyInput
        return 'MyInput';
    }

    public function getValue()
    {
        // return the input value
    }
}
```

And register it as a service with the tag `gheb.io.input`:

```yaml
parameters:
        acme.io.input.myinput.class : Acme\DemoBundle\IO\Inputs\MyInput
services:
        acme.io.input.myinput:
        class : '%acme.io.input.myinput.class%'
            tags:
              - { name: gheb.io.input }
```

Same way for the outputs:

First define your inputs:

```php
<?php

namespace Acme\DemoBundle\IO\Outputs;

use Gheb\IOBundle\Outputs\AbstractOutput;

class MyOutput extends AbstractInput
{
    public function getName()
    {
		// the name is here to allow you calling it from the console
		// php bin/console gheb:io:output MyOutput
        return 'MyOutput';
    }

    public function apply()
    {
        // does something for your app
    }
}
```

And register it as a service with the tag `gheb.io.output`:

```yaml
parameters:
        acme.io.input.myoutput.class : Acme\DemoBundle\IO\Inputs\MyOutput
services:
        acme.io.input.myoutput:
        class : '%acme.io.input.myoutput.class%'
            tags:
              - { name: gheb.io.output}
```

## Hooks

Maybe hooks is not the right term here, because in my minds, you don't always need to use hooks.
In our case, hooks may be essential.

when you run the neat command (php bin/console gheb:neat:run) to evaluate over and over the network created, there is a two things you **MUST** define :

the criteria to define when the evaluation of the current genome must stop and go to the next. It's done with the `nextGenomeCriteria` hook.

the fitness is something really depending on what you are trying to achieve, so you have to send it to the NeatBundle. It's done with the `getFitness` hook.

That's it.

### Create a hook

```php
<?php

namespace Acme\DemoBundle\Neat;

use Gheb\NeatBundle\Hook;

class myHook implements Hook
{
    public function __invoke()
    {
        // logic part
    }
}
```

and plug it to the corresponding tag :


```yaml
services:
  acme.neat.hook.nextgenomecriteria:
    class: '%tamagotchi.neat.hook.nextgenomecriteria.class%'
    arguments: [ "@doctrine.orm.entity_manager" ]
    tags:
      - { name: gheb.neat.hook.nextGenomeCriteria }
```

The different tags / hooks are :

* **`gheb.neat.hook.onBeforeInit`** _multiple hooks can be defined, they are executed once when you run the command._
* **`gheb.neat.hook.onBeforeNewRun`** _multiple hooks can be defined, they are executed just before a new run. That mean after the GA did switch to the next genome/specie/generation._
* **`gheb.neat.hook.onAfterEvaluation`** _multiple hooks can be defined, they are executed just after the current genome network is evaluated for given inputs._
* **`gheb.neat.hook.stopEvaluation`** _only one hook can be defined, if multiple, they just overwrite the previous ones, it must return a boolean. In some cases, you want just to execute your evaluation for N times, sometimes based on a result to get. If you don't set any, it will run almost forever._
* **`gheb.neat.hook.getFitness`** _only one hook can and **must** be defined, if multiple, they just overwrite the previous ones, it must return an integer. The GA will keep and mutate the genomes having the higher fitness._
* **`gheb.neat.hook.nextGenomeCriteria`** _only one hook can and **must** be defined, if multiple, they just overwrite the previous ones, it must return a boolean. This criteria is the condition that indicate the end of the current genome evaluation, and then just switch to the next genome/specie/generation._

Just to get a more precise idea of what's going on, when you run the command `gheb:neat:run`, here is a pseudo-code:

```
hook.onBeforeInit()

getOrCreatePoolofSpecies()

while hook.stopEvaluation()

	if hook.nextGenomeCriteria

		fitness = hook.getFitness()
		genome.setFitness(fitness)

		if fitness > pool.getMaxFitness()
        	pool.setMaxFitness(fitness);
		endif

		nextGenome()
		hook.onBeforeNewRun()
		newRun()
	else
		evaluateCurrentInputs() and applyOutputs()
	endif

	hook.onAfterEvaluation()
endwhile

```
I clearly see other places to put new hooks like just after the while loop with a onBeforeEnding hook.
But I do not have the use at the moment. I will add them later, or please feel free open a pull request.

## TODO

* Add UnitTests
* prioritize outputs applications ??
