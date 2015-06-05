<?php

namespace Wrep\Daemonizable\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EndlessContainerAwareCommand extends EndlessCommand implements ContainerAwareInterface
{
	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * @return ContainerInterface
	 */
	protected function getContainer()
	{
		if (null === $this->container) {
			$this->container = $this->getApplication()->getKernel()->getContainer();
		}

		return $this->container;
	}

	/**
	 * @see ContainerAwareInterface::setContainer()
	 */
	public function setContainer(ContainerInterface $container = null)
	{
		$this->container = $container;
	}

	/**
	 * Called after each iteration
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function finishIteration(InputInterface $input, OutputInterface $output)
	{
		parent::finishIteration($input, $output);

		// Clear the entity manager if used
		if ($this->getContainer()->has('doctrine'))
		{
			$doctrine = $this->getContainer()->get('doctrine');
			if ($doctrine) {
				$doctrine->getManager()->clear();
			}
		}
	}
}
