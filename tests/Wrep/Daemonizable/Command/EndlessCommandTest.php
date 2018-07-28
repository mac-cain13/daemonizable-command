<?php

namespace Tests\Wrep\Daemonizable\Command;

use PHPUnit\Framework\TestCase;
use Wrep\Daemonizable\Command\EndlessCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EndlessCommandTest extends TestCase
{
    private $endlessCommand;

    public function setUp()
    {
        $this->endlessCommand = $this->getMockForAbstractClass(EndlessCommand::class, ['phpunit:endlesscommand:test']);
    }

    /**
     * @dataProvider legalTimeouts
     */
    public function testTimeout($timeout)
    {
        $this->assertEquals(EndlessCommand::DEFAULT_TIMEOUT, $this->endlessCommand->getTimeout(),
            'Default timeout not used');

        $this->endlessCommand->setTimeout($timeout);
        $this->assertEquals($timeout, $this->endlessCommand->getTimeout(), 'Timeout change did not persist');
    }

    public function legalTimeouts()
    {
        return [
            [0.5],
            [0],
            [1],
            ['1'],
        ];
    }

    /**
     * @dataProvider illegalTimeouts
     */
    public function testIllegalTimeout($timeout)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timeout provided to Command::setTimeout.');

        $this->endlessCommand->setTimeout($timeout);
    }

    public function illegalTimeouts()
    {
        return [
            [-0.5],
            [-1],
            ['-1'],
            ['just a random string'],
        ];
    }

    public function testReturnCode()
    {
        $this->assertEquals(0, $this->endlessCommand->getReturnCode(), 'Inital return code not zero');

        $this->endlessCommand->setReturnCode(9);
        $this->assertEquals(9, $this->endlessCommand->getReturnCode(), 'Return code change did not persist');

        $this->endlessCommand->setReturnCode(0);
        $this->assertEquals(0, $this->endlessCommand->getReturnCode(), 'Return code back to zero did not persist');
    }

    /**
     * Execute a command, that will receive a sigterm and needs to call
     * the handleSignal Method outside the EndlessCommand Class.
     */
    public function testInterruptSigtermFromDifferentContext()
    {
        $cmd = new EndlessSelfTerminatingCommand();

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $this->assertSame(0, $cmd->run($input, $output), 'Default exit code is not 0');
    }
}

/**
 * Class EndlessSelfTerminatingCommand
 * @package Tests\Wrep\Daemonizable\Command
 * @internal for testing purposes only
 */
class EndlessSelfTerminatingCommand extends EndlessCommand
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        posix_kill(posix_getpid(), SIGTERM);
    }
}
