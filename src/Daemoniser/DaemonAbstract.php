<?php
namespace Daemoniser;

use DateTime;
use Exception;

/**
 * Class Daemoniser
 * @package Daemoniser
 */
abstract class DaemonAbstract
{
    /**
     * @var DaemonConfig|null
     */
    protected $config = null;

    /**
     * @var bool
     */
    protected $setup = false;

    /**
     * Provides controls for the daemon.
     *
     * @param DaemonConfig $config
     * @param array $argv
     * @throws DaemonException
     */
    final public function execute(DaemonConfig $config, array $argv)
    {
        $this->config = $config;

        if (!empty($config->getWhitelistedUsers())) {
            $user = exec('whoami');
            if ($config->isUserWhitelisted($user) !== true) {
                $this->echoLine(
                    sprintf(
                        'Error: User "%s" is not whitelisted to run this command. Allowed: %s',
                        $user,
                        implode(', ', $config->getWhitelistedUsers())
                    )
                );
                exit(1);
            }
        }

        if ($config->getErrorLogFilePath() === $config->getInfoLogFilePath()) {
            $this->echoLine('Error: Error log file and info log file paths cannot be the same.');
            exit(1);
        }

        if (!function_exists('pcntl_fork')) {
            $this->echoLine('Error: the function `pcntl_fork` is required for this library.');
            exit(1);
        }
        if (!isset($argv[1])) {
            $argv[1] = 'help';
            $this->echoLine('Error: Invalid command provided.');
        }
        $rawAdditionalCommands = $this->getAdditionalCommands();
        if (!is_array($rawAdditionalCommands)) {
            $this->echoLine('Error: The return of getAdditionalCommands must be an array of DaemonCommand instances.');
            exit(1);
        }
        $methodCommands = [];
        $keyedCommands = [];
        $longestCustom = 0;
        foreach ($rawAdditionalCommands as $command) {
            if (!$command instanceof DaemonCommand) {
                $this->echoLine('Error: The return of getAdditionalCommands must be an array of DaemonCommand instances.');
                exit(1);
            }
            if (!method_exists($this, $command->method)) {
                $this->echoLine(
                    sprintf(
                        'Error: The method "%s" does not exist on your object. Referenced by DaemonCommand "%s"',
                        $command->method,
                        $command->command
                    )
                );
                exit(1);
            }
            if ($command->isValid()) {
                if (strlen($command->command) > $longestCustom) {
                    $longestCustom = strlen($command->command);
                }
                $methodCommands[$command->command] = $command->method;
                $keyedCommands[$command->command] = $command->description;
            }
        }
        if (array_key_exists($argv[1], $methodCommands)) {
            $this->{$methodCommands[$argv[1]]}();
            exit;
        }
        switch ($argv[1]) {
            case 'start':
                $this->start();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'soft-stop':
                $this->softStop();
                exit(0);
            case 'restart':
                $this->restart();
                break;
            case 'rm-logs':
                if ($this->isRunning()) {
                    $this->echoLine('Error: Daemon is running. Cannot delete logs while it is running.');
                    exit(1);
                }
                $allLogs = false;
                if (isset($argv[2])) {
                    if ($argv[2] === '--all') {
                        $allLogs = true;
                    } else {
                        $this->echoLine('Error: Not deleting logs. Invalid second command. Expected --all');
                        exit(1);
                    }
                }
                $this->deleteLogs($allLogs);
                break;
            case 'pid':
                if ($this->isRunning() === true) {
                    $this->echoLine($this->getPid());
                }
                break;
            case 'status':
                if ($this->isRunning()) {
                    $this->echoLine('Running');
                } else {
                    $this->echoLine('Stopped');
                }
                break;
            default:
                $this->banner();
                if (isset($argv[1]) && $argv[1] === 'help') {
                    $this->echoLine('Help:');
                } else {
                    $this->echoLine('Invalid command provided.');
                }
                $this->echoLine('');
                $this->echoLine('Usage: php ' . $argv[0] . ' {command}');
                $this->echoLine('');
                $this->echoLine('    Standard commands:');
                $this->echoLine(str_pad('start', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Start the process');
                $this->echoLine(str_pad('soft-stop', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Stop the process via a stop file.');
                $this->echoLine(str_pad('stop', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Stop the process immediately');
                $this->echoLine(str_pad('restart', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Restart (stop and start) the process');
                $this->echoLine(str_pad('status', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Get the status of the process');
                $this->echoLine(str_pad('pid', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Get the pid of the process');
                $this->echoLine(str_pad('rm-logs', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Delete historic logs associated with this daemon.');
                $this->echoLine(str_pad('rm-logs all', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - Delete ALL associated with this daemon.');
                $this->echoLine(str_pad('help', $longestCustom+14, ' ', STR_PAD_LEFT) . ' - See the help information (this)');
                $this->echoLine('');
                if (!empty($keyedCommands)) {
                    $this->echoLine('    Custom commands:');
                    foreach ($keyedCommands as $k => $v) {
                        $this->echoLine(
                            str_pad($k, $longestCustom+14, ' ', STR_PAD_LEFT) . ' - ' . $v
                        );
                    }
                    $this->echoLine('');
                }
                if (isset($argv[1]) && $argv[1] !== 'help') {
                    exit(1);
                }
                exit(0);
                break;
        }
    }

    /**
     * @return DaemonCommand[]
     */
    protected function getAdditionalCommands()
    {
        return [];
    }

    /**
     * @return string
     */
    final public function getCalledClass()
    {
        return get_called_class();
    }

    /**
     * Fork the process.
     *
     * @throws DaemonException
     */
    final private function demonise()
    {
        $processIdentificationNumber = pcntl_fork();
        if ($processIdentificationNumber == -1) {
            throw new DaemonException('Error: Not fork process!');
        } else if ($processIdentificationNumber) {
            exit(0);
        }
        posix_setsid();
        chdir('/');
        $processIdentificationNumber = pcntl_fork();
        if ($processIdentificationNumber === -1) {
            throw new DaemonException('Error: Not double fork process!');
        } elseif ($processIdentificationNumber > 0) {
            $processIdentificationNumberFile = fopen($this->config->getPidFilePath(), 'wb');
            fwrite($processIdentificationNumberFile, $processIdentificationNumber);
            fclose($processIdentificationNumberFile);
            chmod($this->config->getPidFilePath(), 0777);
            exit(0);
        }
        posix_setsid();
        chdir('/');
        ini_set('error_log', $this->config->getErrorLogFilePath());
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        /** @noinspection PhpUnusedLocalVariableInspection */
        $STDIN = fopen('/dev/null', 'r');
        if (!$this->setup) {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $STDOUT = fopen($this->config->getInfoLogFilePath(), 'ab');
            /** @noinspection PhpUnusedLocalVariableInspection */
            $STDERR = fopen($this->config->getErrorLogFilePath(), 'ab');
            $this->setup = true;
        }
        $this->checkLogFileSize();

        $this->runWhilePidPresent();
    }

    /**
     * Check the log file size and if necessary, rotate them.
     */
    protected function checkLogFileSize()
    {
        $infoLogSize = -1;
        $errorLogSize = -1;
        if (file_exists($this->config->getInfoLogFilePath())) {
            $infoLogSize = filesize($this->config->getInfoLogFilePath());
        }
        if (file_exists($this->config->getErrorLogFilePath())) {
            $errorLogSize = filesize($this->config->getErrorLogFilePath());
        }
        if ($infoLogSize > $this->config->getMaxLogFileSize()) {
            if (is_resource(STDOUT)) {
                if (false === @fclose(STDOUT)) {
                    $this->logError(sprintf('Unable to close STDOUT for pid: %s', $this->getPid()));
                }
            }
            $this->rotateLogFile($this->config->getInfoLogFilePath());
            /** @noinspection PhpUnusedLocalVariableInspection */
            $STDOUT = fopen($this->config->getInfoLogFilePath(), 'ab');
        }
        if ($errorLogSize > $this->config->getMaxLogFileSize()) {
            if (is_resource(STDERR)) {
                if (false === @fclose(STDERR)) {
                    $this->logError(sprintf('Unable to close STDERR for pid: %s', $this->getPid()));
                }
            }
            $this->rotateLogFile($this->config->getErrorLogFilePath());
            /** @noinspection PhpUnusedLocalVariableInspection */
            $STDERR = fopen($this->config->getErrorLogFilePath(), 'ab');
        }
    }

    /**
     * @param string $logFileLocation
     * @return bool
     */
    protected function rotateLogFile($logFileLocation)
    {
        if (!file_exists($logFileLocation)) {
            return true;
        }
        try {
            $dt = new DateTime();
            /** @noinspection SpellCheckingInspection */
            $now = $dt->format('Y-m-d');
        } catch (Exception $e) {
            $now = time();
        }
        $name = $logFileLocation;
        $newName = substr($name, 0, -4) . '-' . $now . '.log';
        if (file_exists($newName)) {
            $i = 1;
            while (file_exists($newName)) {
                $newName = substr($name, 0, -4) . '-' . $now . '-' . $i . '.log';
                $i++;
            }
        }
        copy($name, $newName);
        chmod($newName, 0777);
        $f = @fopen($name, "r+");
        if ($f !== false) {
            ftruncate($f, 0);
            fclose($f);
            chmod($name, 0777);
        }
        return true;
    }

    /**
     * Should the daemon be stopping?
     *
     * @return bool
     */
    private function shouldStop()
    {
        if (file_exists($this->config->getSoftStopFilePath())) {
            $rawPid = file_get_contents($this->config->getSoftStopFilePath());
            if (false !== $rawPid && is_numeric($rawPid)) {
                if (intval($rawPid) === $this->getPid()) {
                    return true;
                }
            }
            unlink($this->config->getSoftStopFilePath());
        }
        return false;
    }

    /**
     * Runs a loop of the `run()` function.
     */
    private function runWhilePidPresent()
    {
        $sleep = $this->config->getSleepBetweenRuns();
        if (!is_numeric($sleep) || !is_int($sleep)) {
            $sleep = 0;
        }
        while ($this->getPid() !== 0) {
            if ($this->shouldStop() === true) {
                unlink($this->config->getSoftStopFilePath());
                $this->stop(true);
                exit(0);
            }
            $this->run();
            $this->checkLogFileSize();
            if ($sleep > 0) {
                if ($this->shouldStop() === true) {
                    unlink($this->config->getSoftStopFilePath());
                    $this->stop(true);
                    exit(0);
                }
                sleep($sleep);
            }
        }
    }

    /**
     * Can this daemon be immediately halted? This kills the pid.
     *
     * @return bool
     */
    protected function canImmediatelyStop()
    {
        return false;
    }

    /**
     * Get the process identification number (PID) of the running process
     *
     * @return int
     */
    private function getPid()
    {
        if (file_exists($this->config->getPidFilePath())) {
            $rawPid = file_get_contents($this->config->getPidFilePath());
            if (false === $rawPid || !is_numeric($rawPid)) {
                return 0;
            }
            $processIdentificationNumber = (int)$rawPid;
            if (posix_kill($processIdentificationNumber, SIG_DFL)) {
                return $processIdentificationNumber;
            } else {
                unlink($this->config->getPidFilePath());
                return 0;
            }
        }

        return 0;
    }

    /**
     * Start the daemon
     * @throws DaemonException
     */
    private function start()
    {
        if ($this->isRunning() === false) {
            $this->echoLine('Starting');
            $this->demonise();
            return;
        }

        $this->echoLine('Notice: Already started');
    }

    /**
     * Stop the daemon
     * @param bool $immediate
     * @return bool
     */
    private function stop($immediate=false)
    {
        if ($this->isRunning()) {
            if ($immediate !== true && $this->canImmediatelyStop() !== true) {
                $this->softStop();
                $this->echoLine('Error: Can not be stopped immediately. Scheduling via soft-stop. If you are restarting, you will need to call `status` and then `start` when the process has successfully ended.');
                exit(1);
            }
            if (posix_kill($this->getPid(), SIGTERM)) {
                unlink($this->config->getPidFilePath());
                $this->echoLine('Stopped');
                return true;
            }
            $this->echoLine('Error: Stopping failed');
            return false;
        }

        $this->echoLine('Notice: Already stopped');
        return false;
    }

    /**
     * Gracefully stop the process by writing a `.stop` file.
     *
     * @return bool
     */
    private function softStop()
    {
        if ($this->isRunning()) {
            $write = @file_put_contents($this->config->getSoftStopFilePath(), $this->getPid());
            if (false === $write) {
                $this->echoLine(
                    sprintf(
                        'Error: Unable to write stop file at location "%s"',
                        $this->config->getSoftStopFilePath()
                    )
                );
            }
            chmod($this->config->getSoftStopFilePath(), 0777);
            return false;
        }

        $this->echoLine('Notice: Already stopped');
        return true;
    }

    /**
     * Restart the daemon
     *
     * @throws DaemonException
     * @see DaemonAbstract::stop()
     * @see DaemonAbstract::start()
     */
    private function restart()
    {
        $this->stop();
        $this->start();
    }

    /**
     * Is the daemon running?
     *
     * @return bool
     */
    private function isRunning()
    {
        return $this->getPid() > 0;
    }

    /**
     * @param string $line
     */
    final protected function echoLine($line)
    {
        echo $line . PHP_EOL;
    }

    /**
     * Log a message to the info log file
     *
     * @param string $line
     */
    final protected function logInfo($line)
    {
        try {
            $dtPiece = (new DateTime())->format('[d-M-Y H:i:s T]');
        } catch (Exception $e) {
            $dtPiece = '[INFO]';
        }

        echo sprintf('%s %s', $dtPiece, $line) . PHP_EOL;
    }

    /**
     * Log a message to the error log file
     *
     * @param string $line
     */
    final protected function logError($line)
    {
        error_log($line);
    }

    /**
     * Delete files from a directory, which match `$fileName`.
     * @param string $logDir
     * @param string $fileName
     * @param bool $includeLatest
     * @return int
     */
    private function deleteMatchingFromLogDirectory($logDir, $fileName, $includeLatest)
    {
        $dashed = substr($fileName, 0, -4) . '-';
        $files = scandir($logDir);
        if ($files === false) {
            $this->echoLine(sprintf('Error: scandir failed on directory %s', $logDir));
            exit(1);
        }
        $total = 0;
        foreach ($files as $file) {
            if (in_array($file, ['..', '.']) || substr($file, -4) !== '.log') {
                continue;
            }
            if ($file === $fileName && $includeLatest === true) {
                unlink($logDir . '/' . $file);
                $total++;
                continue;
            }
            if (substr($file, 0, strlen($dashed)) === $dashed && substr($file, -4) === substr($fileName, -4)) {
                unlink($logDir . '/' . $file);
                $total++;
                continue;
            }
        }
        return $total;
    }

    /**
     * Delete all logs from a specific daemon.
     *
     * @param bool $includeLatest
     */
    private function deleteLogs($includeLatest=false)
    {
        $errorLogDir = dirname($this->config->getErrorLogFilePath());
        $infoLogDir = dirname($this->config->getErrorLogFilePath());
        if ($errorLogDir !== false) {
            $errorsDeleted = $this->deleteMatchingFromLogDirectory(
                $errorLogDir,
                basename($this->config->getErrorLogFilePath()),
                $includeLatest
            );
            $this->echoLine(sprintf('Error Log Delete: Removed %s file(s)', $errorsDeleted));
        }
        if ($infoLogDir !== false) {
            $infoDeleted = $this->deleteMatchingFromLogDirectory(
                $infoLogDir,
                basename($this->config->getInfoLogFilePath()),
                $includeLatest
            );
            $this->echoLine(sprintf(' Info Log Delete: Removed %s file(s)', $infoDeleted));
        }
    }

    /**
     * Output the banner of Daemoniser.
     */
    private function banner()
    {
        $this->echoLine('');
        $this->echoLine('     __ \                                  _)                ');
        $this->echoLine('     |   |  _` |  _ \ __ `__ \   _ \  __ \  |  __|  _ \  __| ');
        $this->echoLine('     |   | (   |  __/ |   |   | (   | |   | |\__ \  __/ |    ');
        $this->echoLine('    ____/ \__,_|\___|_|  _|  _|\___/ _|  _|_|____/\___|_|    ');
        $this->echoLine('');
    }

    /**
     * Execute your logic.
     *
     * @abstract
     */
    abstract protected function run();
}
