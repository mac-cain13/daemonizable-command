<?php

namespace Wrep\Notificato\Tests;

use \Wrep\Daemonizable\Command\EndlessCommand;

class EndlessCommandTest extends \PHPUnit_Framework_TestCase
{
	private $endlessCommand;

	public function setUp()
	{
		$this->endlessCommand = $this->getMockForAbstractClass('\Wrep\Daemonizable\Command\EndlessCommand', array('phpunit:endlesscommand:test'));
	}

	/**
	 * @dataProvider legalTimeouts
	 */
	public function testTimeout($timeout)
	{
		$this->assertEquals(EndlessCommand::DEFAULT_TIMEOUT, $this->endlessCommand->getTimeout(), 'Default timeout not used');

		$this->endlessCommand->setTimeout($timeout);
		$this->assertEquals($timeout, $this->endlessCommand->getTimeout(), 'Timeout change did not persist');
	}

	public function legalTimeouts()
	{
		return array(
			array(0.5),
			array(0),
			array(1),
			array('1'),
			);
	}

	/**
	 * @dataProvider illegalTimeouts
	 */
	public function testIllegalTimeout($timeout)
	{
		$this->setExpectedException('InvalidArgumentException', 'Invalid timeout provided to Command::setTimeout.');
		$this->endlessCommand->setTimeout($timeout);
	}

	public function illegalTimeouts()
	{
		return array(
			array(-0.5),
			array(-1),
			array('-1'),
			array('just a random string'),
			);
	}

	public function testReturnCode()
	{
		$this->assertEquals(0, $this->endlessCommand->getReturnCode(), 'Inital return code not zero');

		$this->endlessCommand->setReturnCode(9);
		$this->assertEquals(9, $this->endlessCommand->getReturnCode(), 'Return code change did not persist');

		$this->endlessCommand->setReturnCode(0);
		$this->assertEquals(0, $this->endlessCommand->getReturnCode(), 'Return code back to zero did not persist');
	}
}
