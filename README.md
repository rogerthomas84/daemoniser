```
 __ \                                  _)
 |   |  _` |  _ \ __ `__ \   _ \  __ \  |  __|  _ \  __|
 |   | (   |  __/ |   |   | (   | |   | |\__ \  __/ |
____/ \__,_|\___|_|  _|  _|\___/ _|  _|_|____/\___|_|
```

What is it
----------

Daemoniser is a PHP library to take the stress off of creating PHP based daemons. If you're questioning why then this
library isn't for you. Generally PHP shouldn't be used for creating daemons.

Use it with Composer
--------------------

`$ composer require rogerthomas84/daemoniser`

How to use
-------

For help writing your first daemon, see the example in `example/example.php`.

A daemon requires an instance of `DaemonConfig` to be passed in via the `execute` method.

DaemonConfig constructor:
-------------------------------

1) `DaemonAbstract $damon` / The instance of your daemon (which extends `DaemonAbstract`)
    * Required
2) `$errorLogFilePath` / A full path to either a new or existing log file to use for logging errors.
    * Optional, defaults to `tmp/log/error_My_Class_Name.log` (assuming fully qualified class name is `\My\Class\Name`)
3) `$infoLogFilePath` / A full path to either a new or existing log file to use for logging echo'd content.
    * Optional, defaults to `tmp/log/info_My_Class_Name.log` (assuming fully qualified class name is `\My\Class\Name`)
4) `$pidFilePath` / A full path to either a new or existing file to use for storing the pid of the dameon'd instance.
    * Optional, defaults to `tmp/pid/My_Class_Name.pid` (assuming fully qualified class name is `\My\Class\Name`)
5) `$softStopFilePath` / The path to the soft stop file.
    * Optional, defaults to `tmp/stops/stop_My_Class_Name.log` (assuming fully qualified class name is `\My\Class\Name`)
6) `$sleepDuration` / How long to sleep after each call to your `run()` method.
    * Optional, defaults to `5` seconds
7) `$maxLogFileSize` / How large in bytes should your log file be allowed to get before
    * Optional, defaults to `100000000` bytes, (100 MB)
8) `$iniSettings` / A key => value array of settings to use with `ini_set`. For example, `['memory_limit' => '1024MB']`
    * Optional, defaults to empty array

Usage
-----

Daemoniser has several helpful commands.

1) `php my-daemon.php status` - Get the status of the daemon
2) `php my-daemon.php start` - Start the daemon
3) `php my-daemon.php soft-stop` - Stop the daemon via a stop file gracefully.
4) `php my-daemon.php stop` - Stop the daemon immediately (this is not ideal)
5) `php my-daemon.php restart` - Restart the daemon (this is not ideal)
6) `php my-daemon.php pid` - Get the PID for the daemon
7) `php my-daemon.php rm-logs` - Delete historic logs for this daemon.
8) `php my-daemon.php rm-logs all` - Delete **ALL** logs for this daemon.
9) `php my-daemon.php help` - Show the help content

You shouldn't ever use `stop` really. Doing so will result in the process being immediately killed. But this is less
of a problem if you aren't running something that could result in data loss in the case of an immediate halt being
called.

If you 'must' have an immediate stop, you'll have to implement the `canImmediatelyStop()` method in your daemons
and return `true`.

Extending the commands:
-----------------------

You can easily extend the commands to perform different actions outside of the `run()` loop. To do this, simply
implement the `protected function getAdditionalCommands()` method.

You'd need to return an array of objects. Each object being an instance of `DaemonCommand`. There's a helpful
`::build()` method available on the `DaemonCommand` which you can use to easily build new functionality.


Should I even use this?
-----------------------

If you're asking yourself this question, then the answer is no. No you shouldn't. This library fills very specific
requirements.

Full example file:
------------------

```php
<?php
chdir(dirname(__FILE__));
error_reporting(E_ALL | E_STRICT);

require_once '../vendor/autoload.php';

use Daemoniser\DaemonAbstract;
use Daemoniser\DaemonCommand;
use Daemoniser\DaemonConfig;
use Daemoniser\DaemonException;

/**
 * Class ExampleOneDaemon
 */
class ExampleOneDaemon extends DaemonAbstract
{
    public function run()
    {
        $this->logInfo((new DateTime())->format('Y-m-d H:i:s'));
        $this->logError('This logs an error This logs an error This logs an error This logs an error This logs an error This logs an error ');
    }

    /**
     * @return string
     */
    public function randomAnimal()
    {
        $animals = ['Cat', 'Dog', 'Giraffe'];
        $this->echoLine($animals[array_rand($animals)]);
    }

    /**
     * @return DaemonCommand[]
     * @throws DaemonException
     */
    protected function getAdditionalCommands()
    {
        return [
            DaemonCommand::build('animal', 'Get a random animal.', 'randomAnimal')
        ];
    }
    
    /**
     * @return bool
     */
    protected function canImmediatelyStop()
    {
        return true; // Can we just kill the process?
    }
}

try {
    $daemon = new ExampleOneDaemon();
    $config = new DaemonConfig(
        $daemon,
        null,
        null,
        null,
        5,
        DaemonConfig::ONE_HUNDRED_MB,
        []
    );
    $config->setMaxLogFileSize(1000);
    // You can set the following here, or use the construct of the `DaemonConfig` to add them:
    // $config->setPidFilePath('/path/to/pid/file.pid');
    // $config->setInfoLogFilePath('/path/to/info.log');
    // $config->setErrorLogFilePath('/path/to/info.log');
    // $config->setSleepBetweenRuns(10); // Sleep for 10 seconds between calls to `run()`
    // $config->setMaxLogFileSize(2000000); // Set the max log file size to 2,000,000 bytes before being rotated
    // $config->setIniSettings(
    //     [
    //         'memory_limit' => '512MB',
    //         'display_startup_errors' => 1,
    //         'display_errors' => 1,
    //     ]
    // );

    $daemon->execute(
        $config,
        $argv
    );
} catch (DaemonException $e) {
    echo '  Exception executing command:' . PHP_EOL;
    echo sprintf('    Message: %s', $e->getMessage()) . PHP_EOL;
    echo sprintf('       File: %s', $e->getFile()) . PHP_EOL;
    echo sprintf('       Line: %s', $e->getLine()) . PHP_EOL;
    exit(1);
}

```