<?php

namespace Wrep\Daemonizable\Command;

use Wrep\Daemonizable\Exception\ShutdownEndlessCommandException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class EndlessCommand extends Command
{
	const DEFAULT_TIMEOUT = 5;

	private $code;
	private $timeout;
	private $returnCode;
	private $shutdownRequested;

	private $lastUsage;
	private $lastPeakUsage;

	/**
	 * @see Symfony\Component\Console\Command\Command::__construct()
	 */
	public function __construct($name = null)
	{
		// Construct our context
		$this->shutdownRequested = false;
		$this->setTimeout(self::DEFAULT_TIMEOUT);
		$this->setReturnCode(0);

		$this->lastUsage = 0;
		$this->lastPeakUsage = 0;

		// Construct parent context (also calls configure)
		parent::__construct($name);

		// Set our runloop as the executable code
		parent::setCode(array($this, 'runloop'));
	}

	/**
	 * @see Symfony\Component\Console\Command\Command::run()
	 */
	public function run(InputInterface $input, OutputInterface $output)
	{
		// Force the creation of the synopsis before the merge with the app definition
		$this->getSynopsis();

		// Merge our options
		$this->addOption('run-once', null, InputOption::VALUE_NONE, 'Run the command just once, do not go into an endless loop');
		$this->addOption('detect-leaks', null, InputOption::VALUE_NONE, 'Output information about memory usage');

		// Add the signal handler
		if ( function_exists('pcntl_signal') )
		{
			// Enable ticks for fast signal processing
			declare(ticks = 1);

			pcntl_signal(SIGTERM, array($this, 'handleSignal') );
			pcntl_signal(SIGINT, array($this, 'handleSignal') );
		}

		// And now run the command
		return parent::run($input, $output);
	}

	/**
	 * Handle proces signals.
	 *
	 * @param int The signalcode to handle
	 */
	public function handleSignal($signal)
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
	 * @param InputInterface  $input  An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance
	 *
	 * @return integer The command exit code
	 *
	 * @throws \Exception
	 */
	protected function runloop(InputInterface $input, OutputInterface $output)
	{
		try
		{
			do
			{
				// Do a run
				$this->execute($input, $output);

				// Finish this iteration
				$this->finishIteration($input, $output);

				// Request shutdown if we only should run once
				if ( (bool)$input->getOption('run-once') ) {
					$this->shutdown();
				}

				// Print memory report if requested
				if ( (bool)$input->getOption('detect-leaks') )
				{
					// Gather memory info
					$peak = $this->getMemoryInfo(true);
					$curr = $this->getMemoryInfo(false);

					// Print report
					$output->writeln('== MEMORY USAGE ==');
					$output->writeln(sprintf('Peak: %.02f KByte <%s>%s (%.03f %%)</%s>', $peak['amount'] / 1024, $peak['statusType'], $peak['statusDescription'], $peak['diffPercentage'], $peak['statusType']));
					$output->writeln(sprintf('Cur.: %.02f KByte <%s>%s (%.03f %%)</%s>', $curr['amount'] / 1024, $curr['statusType'], $curr['statusDescription'], $curr['diffPercentage'], $curr['statusType']));
					$output->writeln('');

					// Unset variables to prevent instable memory usage
					unset($peak);
					unset($curr);
				}

				// Sleep some time, note that sleep will be interupted by a signal
				if (!$this->shutdownRequested) {
					usleep($this->timeout);
				}
			}
			while (!$this->shutdownRequested);
		}
		catch (ShutdownEndlessCommandException $ignore)
		{}

		// Prepare for shutdown
		$this->finalize($input, $output);

		return $this->returnCode;
	}

	/**
	 * Called after each iteration
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function finishIteration(InputInterface $input, OutputInterface $output)
	{}

	/**
	 * Get information about the current memory usage
	 *
	 * @param bool True for peak usage, false for current usage
	 *
	 * @return array
	 */
	private function getMemoryInfo($peak = false)
	{
		$lastUsage = ($peak) ? $this->lastPeakUsage : $this->lastUsage;
		$info['amount'] = ($peak) ? memory_get_peak_usage() : memory_get_usage();
		$info['diff'] = $info['amount'] - $lastUsage;
		$info['diffPercentage'] = ($lastUsage == 0) ? 0 : $info['diff'] / ($lastUsage / 100);
		$info['statusDescription'] = 'stable';
		$info['statusType'] = 'info';

		if ($info['diff'] > 0)
		{
			$info['statusDescription'] = 'increasing';
			$info['statusType'] = 'error';
		}
		else if ($info['diff'] < 0)
		{
			$info['statusDescription'] = 'decreasing';
			$info['statusType'] = 'comment';
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
	 * @see Symfony\Component\Console\Command\Command::setCode()
	 */
	public function setCode($code)
	{
		// Exact copy of our parent
		// Makes sure we can access to call it every iteration
		if (!is_callable($code)) {
			throw new \InvalidArgumentException('Invalid callable provided to Command::setCode.');
		}

		$this->code = $code;

		return $this;
	}

	/**
	 * Execution logic.
	 *
	 * This method will be called on every iteration. Try to keep it fast, process
	 * only one unit every iteration. If one unit is to inefficient (due networking for
	 * example), process small batches and call the throwExceptionOnShutdown whenever you can.
	 * This prevents unexpected kills of the process and makes shutdown fast.
	 *
	 * @param InputInterface  $input  An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance
	 *
	 * @return null|integer null or 0 if everything went fine, or an error code
	 *
	 * @throws \LogicException When this abstract method is not implemented
	 * @see    setCode()
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		parent::execute($input, $output);
	}

	/**
	 * Set the timeout of this command.
	 *
	 * @param int Timeout between two iterations in seconds
	 *
	 * @return Command The current instance
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setTimeout($timeout)
	{
		if (!is_numeric($timeout) || $timeout < 0) {
			throw new \InvalidArgumentException('Invalid timeout provided to Command::setTimeout.');
		}

		$this->timeout = 1000000 * $timeout;

		return $this;
	}

	/**
	 * Get the timeout of this command.
	 *
	 * @return int Timeout between two iterations in seconds
	 */
	public function getTimeout()
	{
		return ($this->timeout / 1000000);
	}

	/**
	 * Set the return code of this command.
	 *
	 * @param int 0 if everything went fine, or an error code
	 *
	 * @return Command The current instance
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setReturnCode($returnCode)
	{
		if ($returnCode < 0) {
			throw new \InvalidArgumentException('Invalid returnCode provided to Command::setReturnCode.');
		}

		$this->returnCode = (int)$returnCode;

		return $this;
	}

	/**
	 * Get the return code of this command.
	 *
	 * @return int 0 if everything went fine, or an error code
	 */
	public function getReturnCode()
	{
		return $this->returnCode;
	}

	/**
	 * Instruct the command to end the endless loop gracefully.
	 *
	 * This will finish the current iteration and give the command a chance
	 * to cleanup.
	 *
	 * @return Command The current instance
	 */
	public function shutdown()
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
	 * @return Command The current instance
	 *
	 * @throws ShutdownEndlessCommandException
	 */
	protected function throwExceptionOnShutdown()
	{
		// Make sure all signals are handled
		if (function_exists('pcntl_signal_dispatch')) {
			pcntl_signal_dispatch();
		}

		if ($this->shutdownRequested) {
			throw new ShutdownEndlessCommandException('Volunteered to break out of the EndlessCommand runloop because a shutdown is requested.');
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
	 * @param InputInterface  $input  An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance, will be a NullOutput if the verbose is not set
	 */
	protected function finalize(InputInterface $input, OutputInterface $output)
	{}
}
