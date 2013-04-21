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

	public function testTimeout()
	{
		$this->assertEquals(EndlessCommand::DEFAULT_TIMEOUT, $this->endlessCommand->getTimeout(), 'Default timeout not used');
		$this->endlessCommand->setTimeout(0.5);
		$this->assertEquals(0.5, $this->endlessCommand->getTimeout(), '1/2 second timeout not saved');
	}
}
