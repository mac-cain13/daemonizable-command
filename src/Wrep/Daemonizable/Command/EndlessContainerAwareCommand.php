<?php

namespace Wrep\Daemonizable\Command;

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
}