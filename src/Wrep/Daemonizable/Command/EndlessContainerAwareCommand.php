<?php

declare(strict_types=1);

namespace Wrep\Daemonizable\Command;

use Doctrine\Bundle\DoctrineBundle\Orm\ManagerRegistryAwareEntityManagerProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class EndlessContainerAwareCommand extends EndlessCommand
{
    /**
     * @var ManagerRegistryAwareEntityManagerProvider|null
     */
    private $doctrine;

    public function __construct(
        string $name = null,
        ManagerRegistryAwareEntityManagerProvider|null $doctrine = null,
    ) {
        parent::__construct($name);
    }

    /**
     * Called after each iteration
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function finishIteration(InputInterface $input, OutputInterface $output): void
    {
        parent::finishIteration($input, $output);

        // Clear the entity manager if used
        if ($this->doctrine) {
            $this->doctrine->getDefaultManager()->clear();
        }
    }
}
