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
        $this->logError('This logs an error');
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
     * Can this daemon be immediately halted? This kills the pid.
     *
     * @return bool
     */
    protected function canImmediatelyStop()
    {
        return true;
    }
}

try {
    $daemon = new ExampleOneDaemon();
    $config = new DaemonConfig(
        $daemon,
        null,
        null,
        null,
        null,
        5,
        DaemonConfig::ONE_HUNDRED_MB
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
    echo sprintf('    %s', $e->getMessage()) . PHP_EOL;
    echo sprintf('    File: %s', $e->getFile()) . PHP_EOL;
    echo sprintf('    Line: %s', $e->getLine()) . PHP_EOL;
    exit(1);
}
