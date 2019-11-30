<?php
namespace Daemoniser;

/**
 * Class DaemonConfig
 * @package Daemoniser
 */
class DaemonConfig
{
    const ONE_MB = 1000000;
    const FIVE_MB = 5000000;
    const TEN_MB = 10000000;
    const TWENTY_MB = 20000000;
    const FIFTY_MB = 50000000;
    const ONE_HUNDRED_MB = 100000000;
    const TWO_HUNDRED_FIFTY_MB = 250000000;
    const FIVE_HUNDRED_MB = 500000000;

    /**
     * @var int
     */
    protected $sleepDuration = 5;

    /**
     * @var int
     */
    protected $maxLogFileSize = self::ONE_HUNDRED_MB;

    /**
     * @var string
     */
    protected $errorLogFilePath = null;

    /**
     * @var string
     */
    protected $infoLogFilePath = null;

    /**
     * @var string
     */
    protected $pidFilePath = null;

    /**
     * @var DaemonAbstract
     */
    protected $daemon = null;

    protected $iniSettings = [];

    /**
     * Construct the config object, passing the instance of the Daemon.
     *
     * @param DaemonAbstract $daemon the instance of the daemon
     * @param string|null $errorLogFilePath (optional) defaults to `tmp/log/error_{class_name}.log`
     * @param string|null $infoLogFilePath (optional) defaults to `tmp/log/info_{class_name}.log`
     * @param string|null $pidFilePath (optional) defaults to `tmp/pid/{class_name}.pid`
     * @param int $sleepDuration (optional) default 5
     * @param int $maxLogFileSize (optional) defaults to 100MB ((1024*1024))
     * @param array $iniSettings (optional) the ini_set settings to send.
     * @throws DaemonException
     */
    public function __construct(DaemonAbstract $daemon, $errorLogFilePath=null, $infoLogFilePath=null, $pidFilePath=null, $sleepDuration=5, $maxLogFileSize=self::ONE_HUNDRED_MB, $iniSettings=[])
    {
        $this->daemon = $daemon;
        $this->setSleepBetweenRuns($sleepDuration);
        $this->setErrorLogFilePath($errorLogFilePath);
        $this->setInfoLogFilePath($infoLogFilePath);
        $this->setPidFilePath($pidFilePath);
        $this->setMaxLogFileSize($maxLogFileSize);
        $this->setIniSettings($iniSettings);
    }

    /**
     * Set ini settings. Uses `ini_set` to configure.
     *
     * @param array $iniSettings
     */
    public function setIniSettings(array $iniSettings)
    {
        foreach ($iniSettings as $k => $v) {
            ini_set($k, $v);
        }
    }

    /**
     * Set the sleep duration in seconds between the recurring calls to `run()`
     *
     * @param int $sleepDuration
     * @return $this
     */
    public function setSleepBetweenRuns($sleepDuration)
    {
        if (is_integer($sleepDuration) && $sleepDuration > 0) {
            $this->sleepDuration = $sleepDuration;
        }

        return $this;
    }

    /**
     * Get the number of seconds to sleep between the recurring calls to `run()`
     *
     * @return int
     */
    public function getSleepBetweenRuns()
    {
        if ($this->sleepDuration > 0) {
            return $this->sleepDuration;
        }

        return 5;
    }

    /**
     * Set the maximum log file size in bytes.
     *
     * @param int $bytes
     * @return $this
     */
    public function setMaxLogFileSize($bytes)
    {
        if (is_integer($bytes) && $bytes > 0) {
            $this->maxLogFileSize = $bytes;
        }

        return $this;
    }

    /**
     * Get the maximum log file size in bytes.
     *
     * @return int
     */
    public function getMaxLogFileSize()
    {
        if ($this->maxLogFileSize > 0) {
            return $this->maxLogFileSize;
        }
        return self::ONE_HUNDRED_MB;
    }

    /**
     * Set the path to the log file for info logs.
     *
     * @param string $infoLogFilePath
     * @return $this
     * @throws DaemonException
     */
    public function setInfoLogFilePath($infoLogFilePath)
    {
        if ($infoLogFilePath === null) {
            $infoLogFilePath = $this->getFileSystemResourceFile('log', 'info_', 'log');
        }
        $this->checkProvidedFilePath($infoLogFilePath, 'info log', 'log');
        $this->infoLogFilePath = $infoLogFilePath;

        return $this;
    }

    /**
     * Get the path to the log file for info logs.
     *
     * @return string
     */
    public function getInfoLogFilePath()
    {
        return $this->infoLogFilePath;
    }

    /**
     * Set the path to the log file for error logs.
     *
     * @param string $errorLogFilePath
     * @return $this
     * @throws DaemonException
     */
    public function setErrorLogFilePath($errorLogFilePath)
    {
        if ($errorLogFilePath === null) {
            $errorLogFilePath = $this->getFileSystemResourceFile('log', 'error_', 'log');
        }
        $this->checkProvidedFilePath($errorLogFilePath, 'error log', 'log');
        $this->errorLogFilePath = $errorLogFilePath;

        return $this;
    }

    /**
     * Get the path to the log file for error logs.
     *
     * @return string
     */
    public function getErrorLogFilePath()
    {
        return $this->errorLogFilePath;
    }

    /**
     * Set the path to the file to hold the pid for this daemon.
     *
     * @param string $pidFilePath
     * @return $this
     * @throws DaemonException
     */
    public function setPidFilePath($pidFilePath)
    {
        if ($pidFilePath === null) {
            $pidFilePath = $this->getFileSystemResourceFile('pid', '', 'pid');
        }
        $this->checkProvidedFilePath($pidFilePath, 'pid', 'pid');
        $this->pidFilePath = $pidFilePath;

        return $this;
    }

    /**
     * Get the path to the PID file storage.
     *
     * @return string
     */
    public function getPidFilePath()
    {
        return $this->pidFilePath;
    }

    /**
     * Check that a given file path is writable.
     *
     * @param $filePath
     * @param $typeName
     * @param $extension
     * @throws DaemonException
     */
    private function checkProvidedFilePath($filePath, $typeName, $extension)
    {
        if (!is_writable(dirname($filePath))) {
            throw new DaemonException(
                sprintf('Error: %s file path is not writable at location "%s"', $typeName, $filePath)
            );
        }
        if (substr($filePath, -4) !== '.' . $extension) {
            throw new DaemonException(
                sprintf('Error: %s file path must end in ".%s" - received "%s"', $typeName, $extension, $filePath)
            );
        }
        if (is_file($filePath)) {
            if (!is_writable($filePath)) {
                throw new DaemonException(
                    sprintf('Error: %s file path is not writable at location "%s"', $typeName, $filePath)
                );
            }
        }
    }

    /**
     * Get a path to the directory of `tmp` in this library.
     *
     * @param string $folderName
     * @param string $prependName
     * @param string $extension
     * @return string
     * @throws DaemonException
     */
    private function getFileSystemResourceFile($folderName, $prependName, $extension)
    {
        $folderLocation = realpath(dirname(__FILE__) . sprintf('/../../tmp/%s', $folderName));
        if (false === $folderLocation) {
            throw new DaemonException('Unable to resolve file system path.');
        }

        return sprintf(
            '%s/%s%s.%s',
            $folderLocation,
            $prependName,
            $this->getNormalisedDaemonClassName(),
            $extension
        );
    }

    /**
     * Get a normalised class name that's suitable for use as a file name. For example, `\My\Class\Here` would be
     * transformed into `My_Class_Here`
     *
     * @return string
     */
    public function getNormalisedDaemonClassName()
    {
        $clzName = 'log';
        if ($this->daemon !== null) {
            $clzName = $this->daemon->getCalledClass();
        }
        $clzName = ltrim($clzName, '\\');

        return str_replace('\\', '_', $clzName);
    }
}
