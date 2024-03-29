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
    protected $softStopFilePath = null;

    /**
     * @var string
     */
    protected $pidFilePath = null;

    /**
     * @var DaemonAbstract
     */
    protected $daemon = null;

    /**
     * @var string|null
     */
    protected $defaultWritableFolder = null;

    /**
     * @var array
     */
    protected $whitelistedUsers = [];

    /**
     * @var array
     */
    protected $iniSettings = [];

    /**
     * Construct the config object, passing the instance of the Daemon.
     *
     * @param DaemonAbstract $daemon the instance of the daemon
     * @param string|null $errorLogFilePath (optional) defaults to `tmp/log/error_{class_name}.log`
     * @param string|null $infoLogFilePath (optional) defaults to `tmp/log/info_{class_name}.log`
     * @param string|null $pidFilePath (optional) defaults to `tmp/pid/{class_name}.pid`
     * @param string|null $softStopFilePath (optional) defaults to `tmp/stops/stop_{class_name}.pid`
     * @param int $sleepDuration (optional) default 5
     * @param int $maxLogFileSize (optional) defaults to 100MB ((1024*1024))
     * @param array $iniSettings (optional) the ini_set settings to send.
     * @param array $whitelistedUsers (optional) an array of system user names to whitelist executing the daemon,
     *                                           for example ['www-data', 'root']
     * @throws DaemonException
     */
    public function __construct(DaemonAbstract $daemon, $errorLogFilePath=null, $infoLogFilePath=null, $pidFilePath=null, $softStopFilePath=null, $sleepDuration=5, $maxLogFileSize=self::ONE_HUNDRED_MB, $iniSettings=[], $whitelistedUsers=[])
    {
        $this->daemon = $daemon;
        $this->setSleepBetweenRuns($sleepDuration);
        $this->setErrorLogFilePath($errorLogFilePath);
        $this->setInfoLogFilePath($infoLogFilePath);
        $this->setSoftStopFilePath($softStopFilePath);
        $this->setPidFilePath($pidFilePath);
        $this->setMaxLogFileSize($maxLogFileSize);
        $this->setIniSettings($iniSettings);
        $this->setWhitelistedUsers($whitelistedUsers);
    }

    /**
     * Set an array of system user names to whitelist executing the daemon, for example ['www-data', 'root']
     *
     * @param array $whitelistUsers
     * @return $this
     */
    public function setWhitelistedUsers(array $whitelistUsers)
    {
        $this->whitelistedUsers = $whitelistUsers;
        return $this;
    }

    /**
     * Get an array of system user names to whitelist executing the daemon, for example ['www-data', 'root']
     *
     * @return array
     */
    public function getWhitelistedUsers()
    {
        return $this->whitelistedUsers;
    }

    /**
     * Is a given user allowed to execute this command?
     *
     * @param string $username
     * @return bool
     */
    public function isUserWhitelisted($username)
    {
        if (empty($this->whitelistedUsers)) {
            return true;
        }

        return in_array($username, $this->getWhitelistedUsers());
    }

    /**
     * Override the system from using `tmp/log` and `tmp/pid` for the default log and pid file storage locations.
     *
     * @param string $path
     * @return DaemonConfig
     * @throws DaemonException
     */
    public function setDefaultWritableFolder($path)
    {
        $path = realpath($path);
        if (false === $path) {
            throw new DaemonException(
                'setDefaultWritableFolder expects the path to be a writable directory that exists.'
            );
        }
        $path = rtrim($path, '/');
        if (!is_dir($path) || !is_writable($path)) {
            throw new DaemonException(
                'setDefaultWritableFolder expects the path to be a writable directory that exists.'
            );
        }
        if (!is_dir($path . '/log') || !is_writable($path . '/log')) {
            throw new DaemonException(
                'setDefaultWritableFolder expects the directory passed to contain a directory called "log" that is writable.'
            );
        }
        if (!is_dir($path . '/pid') || !is_writable($path . '/pid')) {
            throw new DaemonException(
                'setDefaultWritableFolder expects the directory passed to contain a directory called "pid" that is writable.'
            );
        }

        $this->defaultWritableFolder = $path;

        return $this;
    }

    /**
     * Get the default directory to use for storage.
     *
     * @return string|null
     */
    public function getDefaultWritableFolder()
    {
        return $this->defaultWritableFolder;
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
     * Set the path to the soft stop file.
     *
     * @param string $softStopFile
     * @return $this
     * @throws DaemonException
     */
    public function setSoftStopFilePath($softStopFile)
    {
        if ($softStopFile === null) {
            $softStopFile = $this->getFileSystemResourceFile('stops', 'stop_', 'stop');
        }
        $this->checkProvidedFilePath($softStopFile, 'stop', 'stop');
        $this->softStopFilePath = $softStopFile;

        return $this;
    }

    /**
     * Get the path to the log file for info logs.
     *
     * @return string
     */
    public function getSoftStopFilePath()
    {
        return $this->softStopFilePath;
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
        if (substr($filePath, (-1-strlen($extension))) !== '.' . $extension) {
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
        if ($folderName === 'log' && $this->getDefaultWritableFolder() !== null) {
            $folderLocation = realpath($this->getDefaultWritableFolder() . '/' . $folderName);
        }
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
