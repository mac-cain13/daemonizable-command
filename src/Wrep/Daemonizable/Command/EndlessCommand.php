<?php

declare(strict_types=1);

namespace Wrep\Daemonizable\Command;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Throwable;
use Wrep\Daemonizable\Exception\ShutdownEndlessCommandException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function pcntl_async_signals;
use function pcntl_signal;

abstract class EndlessCommand extends Command
{
    public const DEFAULT_TIMEOUT = 5;

    private int $timeout;
    private int $returnCode;
    private bool $shutdownRequested;
    private int $lastUsage;
    private int $lastPeakUsage;

    /**
     * @see Command::__construct()
     */
    public function __construct(string $name = null)
    {
        // Construct our context
        $this->shutdownRequested = false;
        $this->setTimeout(static::DEFAULT_TIMEOUT);
        $this->returnCode = 0;
        $this->lastUsage = 0;
        $this->lastPeakUsage = 0;

        // Construct parent context (also calls configure)
        parent::__construct($name);

        // Merge our options
        $this->addOption('run-once', null, InputOption::VALUE_NONE,
            'Run the command just once, do not go into an endless loop');
        $this->addOption('detect-leaks', null, InputOption::VALUE_NONE, 'Output information about memory usage');

        // Set our runloop as the executable code
        parent::setCode([$this, 'runloop']);
    }

    /**
     * @see Command::run()
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        // Add the signal handler
        if (function_exists('pcntl_signal')) {
            // Enable async signals for fast signal processing
            try {
                pcntl_async_signals(true);
            } catch (Throwable $e) {
                declare(ticks=1);
            }

            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        // And now run the command
        return parent::run($input, $output);
    }

    /**
     * Handle process signals.
     *
     * @param int $signal The signal code to handle
     */
    public function handleSignal(int $signal): void
    {
        switch ($signal) {
            // Shutdown signals
            case SIGTERM:
            case SIGINT:
                $this->shutdown();
                break;
        }
    }

    /**
     * The big endless loop and management of signals/shutdown etc.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int The command exit code
     *
     * @throws Exception
     */
    protected function runloop(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->starting($input, $output);

            while (! $this->shutdownRequested) {
                // Start iteration
                $this->startIteration($input, $output);

                // Do a run
                $this->execute($input, $output);

                // Finish this iteration
                $this->finishIteration($input, $output);

                // Request shutdown if we only should run once
                if ($input->getOption('run-once')) {
                    $this->shutdown();
                }

                // Print memory report if requested
                if ($input->getOption('detect-leaks')) {
                    // Gather memory info
                    $peak = $this->getMemoryInfo(true);
                    $curr = $this->getMemoryInfo(false);

                    // Print report
                    $output->writeln('== MEMORY USAGE ==');
                    $output->writeln(sprintf('Peak: %.02f KByte <%s>%s (%.03f %%)</%s>', $peak['amount'] / 1024,
                        $peak['statusType'], $peak['statusDescription'], $peak['diffPercentage'], $peak['statusType']));
                    $output->writeln(sprintf('Cur.: %.02f KByte <%s>%s (%.03f %%)</%s>', $curr['amount'] / 1024,
                        $curr['statusType'], $curr['statusDescription'], $curr['diffPercentage'], $curr['statusType']));
                    $output->writeln('');

                    // Unset variables to prevent unstable memory usage
                    unset($peak, $curr);
                }

                // Sleep some time, note that sleep will be interrupted by a signal
                if (! $this->shutdownRequested) {
                    usleep($this->timeout);
                }
            }
        } catch (ShutdownEndlessCommandException $ignore) {
        }

        // Prepare for shutdown
        $this->finalize($input, $output);

        return $this->returnCode;
    }

    /**
     * Called before first execute
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function starting(InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * Called before each iteration
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function startIteration(InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * Called after each iteration
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function finishIteration(InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * Get information about the current memory usage
     *
     * @param bool $peak True for peak usage, false for current usage
     *
     * @return array
     */
    private function getMemoryInfo(bool $peak = false): array
    {
        $lastUsage = ($peak) ? $this->lastPeakUsage : $this->lastUsage;
        $info['amount'] = ($peak) ? memory_get_peak_usage() : memory_get_usage();
        $info['diff'] = $info['amount'] - $lastUsage;
        $info['diffPercentage'] = ($lastUsage == 0) ? 0 : $info['diff'] / ($lastUsage / 100);
        $info['statusDescription'] = 'stable';
        $info['statusType'] = 'info';

        if ($info['diff'] > 0) {
            $info['statusDescription'] = 'increasing';
            $info['statusType'] = 'error';
        } else {
            if ($info['diff'] < 0) {
                $info['statusDescription'] = 'decreasing';
                $info['statusType'] = 'comment';
            }
        }

        // Update last usage variables
        if ($peak) {
            $this->lastPeakUsage = $info['amount'];
        } else {
            $this->lastUsage = $info['amount'];
        }

        return $info;
    }

    /**
     * Execution logic.
     *
     * This method will be called on every iteration. Try to keep it fast, process
     * only one unit every iteration. If one unit is to inefficient (due networking for
     * example), process small batches and call the throwExceptionOnShutdown whenever you can.
     * This prevents unexpected kills of the process and makes shutdown fast.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int 0 if everything went fine, or an exit code
     *
     * @throws LogicException When this abstract method is not implemented
     * @see    setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return parent::execute($input, $output);
    }

    /**
     * Set the timeout of this command.
     *
     * @param float $timeout Timeout between two iterations in seconds
     *
     * @throws InvalidArgumentException
     */
    public function setTimeout(float $timeout): self
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Invalid timeout provided to Command::setTimeout.');
        }

        $this->timeout = (int) (1000000 * $timeout);

        return $this;
    }

    /**
     * Get the timeout of this command.
     *
     * @return float Timeout between two iterations in seconds
     */
    public function getTimeout(): float
    {
        return ($this->timeout / 1000000);
    }

    /**
     * Set the return code of this command.
     *
     * @param int $returnCode 0 if everything went fine, or an error code
     *
     * @throws InvalidArgumentException
     */
    public function setReturnCode(int $returnCode): self
    {
        if ($returnCode < 0) {
            throw new InvalidArgumentException('Invalid returnCode provided to Command::setReturnCode.');
        }

        $this->returnCode = $returnCode;

        return $this;
    }

    /**
     * Get the return code of this command.
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function getReturnCode(): int
    {
        return $this->returnCode;
    }

    /**
     * Instruct the command to end the endless loop gracefully.
     *
     * This will finish the current iteration and give the command a chance
     * to clean up.
     *
     */
    public function shutdown(): self
    {
        $this->shutdownRequested = true;

        return $this;
    }

    /**
     * Checks if a shutdown is requested and throws an exception if so.
     *
     * Can be used to (voluntary) exit the runloop during a run, use this if your
     * execution code takes quite long to finish on a point where you still can exit
     * without corrupting any data.
     *
     * @throws ShutdownEndlessCommandException
     */
    protected function throwExceptionOnShutdown(): self
    {
        // Make sure all signals are handled
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        if ($this->shutdownRequested) {
            throw new ShutdownEndlessCommandException('Volunteered to break out of the EndlessCommand::runloop because a shutdown is requested.');
        }

        return $this;
    }

    /**
     * Called on shutdown after the last iteration finished.
     *
     * Use this to do some cleanup, but keep it fast. If you take too long and we must
     * exit because of a signal changes are the process will be killed! It's the counterpart
     * of initialize().
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance, will be a NullOutput if the verbose is not set
     */
    protected function finalize(InputInterface $input, OutputInterface $output): void
    {
    }

    protected function isShutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }
}
