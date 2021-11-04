# Daemonizable Commands for Symfony [![Build Status](https://travis-ci.org/mac-cain13/daemonizable-command.svg?branch=master)](https://travis-ci.org/mac-cain13/daemonizable-command)

**A small bundle to create endless running commands with Symfony.**

These endless running commands are very easy to daemonize with something like Upstart or systemd.

## Why do I need this?
Because you want to create long running PHP/Symfony processes! For example to send mails with large attachment, process (delayed) payments or generate large PDF reports. They query the database or read from a message queue and do their job. This bundle makes it very easy to create such processes as Symfony commands.

## How to install?
Use composer to include it into your Symfony project:

`composer require wrep/daemonizable-command`

### What version to use?
Symfony did make some breaking changes, so you should make sure to use a compatible bundle version:
* Version 3.0.* for Symfony 4 and 5 and higher
* Version 2.0.* for Symfony 3
* Version 1.3.* for Symfony 2.8+

## How to use?
Just create a Symfony command that extends from `EndlessCommand` and off you go. Here is a minimal example:

```php
namespace Acme\DemoBundle\Command;

use Wrep\Daemonizable\Command\EndlessCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class MinimalDemoCommand extends EndlessCommand
{
	// This is just a normal Command::configure() method
	protected function configure()
	{
		$this->setName('acme:minimaldemo')
		     ->setDescription('An EndlessCommand implementation example');
	}

	// Execute will be called in a endless loop
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// Tell the user what we're going to do.
		// This will be a NullOutput if the user doesn't want any output at all,
		//  so you don't have to do any checks, just always write to the output.
		$output->write('Updating timestamp... ');

		// Do some work
		file_put_contents( '/tmp/acme-timestamp.txt', time() );

		// Tell the user we're done
		$output->writeln('done');
	}
}
```

Run it with `php app/console acme:minimaldemo`.

An [example with all the bells and whistles](examples/ExampleCommand.php) is also available and gives a good overview of best practices and how to do some basic things.

## How to daemonize?
Alright, now we have an endless running command *in the foreground*. Usefull for debugging, useless in production! So how do we make this thing a real daemon?

You should use [systemd](http://www.freedesktop.org/wiki/Software/systemd) to daemonize the command. They provide very robust daemonization, start your daemon on a reboot and also monitor the process so it will try to restart it in the case of a crash.

If you can't use Upstart or systemd, you can use `.lock` file with [LockHandler](http://symfony.com/doc/current/components/filesystem/lock_handler.html) with [crontab](https://wikipedia.org/wiki/Cron) wich start script every minute.

An [example Upstart script](https://github.com/mac-cain13/daemonizable-command/blob/master/examples/example-systemd.service) is available, place your script in `/etc/init/` and start the daemon with `start example-daemon`. The name of the `.conf`-file will be the name of the daemon. A systemd example is not yet available, but it shouldn't be that hard to [figure out](http://patrakov.blogspot.nl/2011/01/writing-systemd-service-files.html).

## Command line switches
A few switches are available by default to make life somewhat easier:

* Use `-q` to suppress all output
* Use `--run-once` to only run the command once, usefull for debugging
* Use `--detect-leaks` to print a memory usage report after each run, read more in the next section

## Memory usage and leaks
Memory usage is very important for long running processes. Symfony is not the smallest framework around and if you leak some memory in your execute method your daemon will crash! The `EndlessCommand` classes have been checked for memory leaks, but you should also check your own code.

### How to prevent leaks?
Always start your command with the `-e prod --no-debug` flags. This disables all debugging features of Symfony that will eat up more and more memory.

Make sure you cleanup in the `execute`-method, make sure you're not appending data to an array every iteration or leave sockets/file handles open for example.

In case you are using the fingers-crossed handler in Monolog, this will also be a source of memory leaks. The idea of this handler is to keep all below-threshold log entries in memory and only flush those in case of an above-threshold entry. You can still use the fingers-crossed handler as long as you manually flush it at the end of the `execute`-method:

```
foreach ($this->getContainer()->get('logger')->getHandlers() as $handler)
{
    if ($handler instanceof FingersCrossedHandler) {
        $handler->clear();
    }
}
```

### Detecting memory leaks
Run your command with the `--detect-leaks` flag. Remember that debug mode will eat memory so you'll need to run with `-e prod --no-debug --detect-leaks` for accurate reports.

After each iteration a memory report like this is printed on your console:
```
== MEMORY USAGE ==
Peak: 30038.86 KByte stable (0.000 %)
Cur.: 29856.46 KByte stable (0.000 %)
```

The first 3 iterations may be unstable in terms of memory usage, but after that it should be stable. *Even a slight increase of memory usage will crash your daemon over time!*

If you see an increase/stable/decrease loop you're probably save. It could be the garabage collector not cleaning up, you can fix this by using unset on variables to cleanup the memory yourself.

### Busting some myths
Calling `gc_collect_cycles()` will not help to resolve leaks. PHP will cleanup memory right in time all by itself, calling this method may slow down leaking memory, but will not solve it. Also it makes spotting leaks harder, so just don't use it.

If you run Symfony in production and non-debug mode it will not leak memory and you do not have to disable any SQL loggers. The only leak I runned into is the one in the MonologBundle mentioned above.

### Working with Doctrine
For reasons EndlessContainerAwareCommand clears after each Iteration Doctrine's EntityManager. Be aware of that.
You can override finishIteration() to avoid this behaviour but you have to handle the EM on your own then. 
