<?php

declare(strict_types=1);

namespace Tests\Wrep\Daemonizable\Command;

use PHPUnit\Framework\TestCase;
use Wrep\Daemonizable\Command\EndlessCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;

class EndlessCommandRunloopTest extends TestCase
{
    public function testCommandEntersRunloop(): void
    {
        $command = new TestEndlessCommand();
        
        $application = new Application();
        $application->add($command);
        $application->setAutoExit(false);
        
        $input = new ArrayInput([
            'command' => 'test:endless',
            '--run-once' => true
        ]);
        $output = new BufferedOutput();
        
        $exitCode = $application->run($input, $output);
        
        $outputContent = $output->fetch();
        
        // Verify that the command entered the runloop by checking output
        $this->assertStringContainsString('Starting runloop', $outputContent);
        $this->assertStringContainsString('Execute called', $outputContent);
        $this->assertStringContainsString('Finalizing', $outputContent);
        $this->assertEquals(0, $exitCode);
    }
}

class TestEndlessCommand extends EndlessCommand
{
    protected function configure(): void
    {
        $this->setName('test:endless');
    }
    
    protected function starting(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('Starting runloop');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Execute called');
        return 0;
    }
    
    protected function finalize(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('Finalizing');
    }
}