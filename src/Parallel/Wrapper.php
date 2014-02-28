<?php

namespace Parallel;

use Parallel\Exceptions\InvalidBinaryException;
use Parallel\Exceptions\InvalidOutputDirectoryException;
use Parallel\Exceptions\InvalidTempDirectoryException;

class Wrapper
{
    const DEFAULT_BINARY_PATH = '/usr/local/bin/parallel';
    const DEFAULT_MAX_PARALLELISM = 4;

    /**
     * @var string
     */
    private $binaryPath;

    /**
     * @var array
     */
    private $commandList;

    /**
     * @var int
     */
    private $maxParallelism;

    /**
     * @var string
     */
    private $outputDirectory;

    /**
     * @var string
     */
    private $outputDirectoryHeader;

    /**
     * @var bool
     */
    private $outputToDirectories;

    /**
     * @var bool
     */
    private $outputToFiles;

    /**
     * @var bool
     */
    private $sameOrder;

    /**
     * @var int
     */
    private $parallelism;

    /**
     * @var bool
     */
    private $remoteServersOnly;

    /**
     * @var array
     */
    private $serverList;

    /**
     * @var string
     */
    private $tempDirectory;

    /**
     * Constructor
     *
     * @param string $parallelFullPath
     * @param array $commandsList
     * @param int $maxParallelism
     */
    public function __construct(
        $parallelFullPath = '',
        $commandsList = array(),
        $maxParallelism = self::DEFAULT_MAX_PARALLELISM
    ) {
        $this->binaryPath = self::DEFAULT_BINARY_PATH;

        if (!empty($parallelFullPath)) {
            $this->initBinary($parallelFullPath);
        }

        $this->commandList = array();
        $this->addCommand($commandsList);
        $this->setMaxParallelism($maxParallelism);
        $this->setParallelism(0);
        $this->keepSameOrder(false);
        $this->outputToDirectories = false;
        $this->outputToFiles = false;
        $this->tempDirectory = sys_get_temp_dir();
        $this->outputDirectory = $this->tempDirectory;
        $this->outputDirectoryHeader = '';
        $this->remoteServersOnly = false;
        $this->serverList = array();
    }

    /**
     * Adds a command or a list of commands to be executed using parallel
     *
     * @param array|string $commandString
     * @return bool
     */
    public function addCommand($commandString)
    {

        if (empty($commandString)) {
            return false;
        } else {

            if (is_array($commandString)) {

                foreach ($commandString as $command) {

                    if (is_string($command)) {
                        $this->commandList[] = escapeshellarg($command);
                    }

                }

            } elseif (is_string($commandString)) {
                $this->commandList[] = escapeshellarg($commandString);
            } else {
                return false;
            }

            return true;
        }

    }

    /**
     * Adds a server to parallel to use for command execution
     * (Parallel will distribute items from the command list between these servers)
     *
     * @param array|string $serverName
     * @return bool
     */
    public function addServer($serverName)
    {

        if (empty($serverName)) {
            return  false;
        } else {
            if (is_array($serverName)) {

                foreach ($serverName as $server) {

                    if (is_string($server)) {
                        $this->serverList[] = $server;
                    }

                }

            } elseif (is_string($serverName)) {
                $this->serverList[] = $serverName;
            } else {
                return false;
            }

            return true;
        }

    }

    /**
     * If saving results into a directory structure, [ $this->saveOutputInDirectories(true) ]
     * it returns all errors and output strings found in subdirectories.
     *
     * If the output is not set to be saved in a directory structure, or there are no
     * results in the directory, an empty array will be returned.
     *
     * The $label should be the same that was used when setting the results directory.
     *
     * @param string $label
     * @return array
     */
    public function getResultsFromDirectory($label)
    {

        if ($this->outputToDirectories === true) {
            // Map file names to array keys
            $fileMap = array('stderr' => 'errors', 'stdout' => 'output');

            // Output buffer
            $buffer = array();

            // Target directory
            $directory = $this->outputDirectory . DIRECTORY_SEPARATOR . $label;

            // Files to find
            $findFiles = array_keys($fileMap);

            if (is_dir($directory) && is_readable($directory)) {
                $directoryIterator = new \RecursiveDirectoryIterator($directory);

                foreach(new \RecursiveIteratorIterator($directoryIterator) as $file)
                {
                    $fileName = basename($file);

                    if (in_array($fileName, $findFiles)) {
                        $fileName = $fileMap[$fileName];
                        $buffer[basename(dirname($file))][$fileName] = file_get_contents($file);
                    }

                }

                return $buffer;
            } else {
                return array();
            }

        } else {
            // Not outputting results into a directory
            return array();
        }

    }

    /**
     * Returns the calculated value of actual parallelism to use
     *
     * @return int
     */
    private function getTrueParallelism()
    {

        if ($this->parallelism == 0) {
            $this->parallelism = count($this->commandList);
        }

        return ($this->parallelism > $this->maxParallelism) ? $this->maxParallelism : $this->parallelism;
    }

    /**
     * Checks if the executable's path points to a valid binary file and stores the path
     *
     * @param $binaryPath
     * @return bool
     * @throws InvalidBinaryException
     */
    public function initBinary($binaryPath)
    {

        if (@is_executable($binaryPath)) {
            $this->binaryPath = $binaryPath;
            return true;
        } else {
            throw new InvalidBinaryException();
        }

    }

    /**
     * Sets the flag for parallel to keep the original order of commands while executing
     *
     * @param bool $flag
     * @return bool
     */
    public function keepSameOrder($flag)
    {
        $this->sameOrder = (is_bool($flag) || is_integer($flag)) ? (bool)$flag : false;
        return $this->sameOrder;
    }

    /**
     * Runs the commands on the command list
     * Alternatively, it can also return the generated command string
     *
     * @param bool $getCommandOnly
     * @return string|bool
     */
    public function run($getCommandOnly = false)
    {

        if (is_array($this->commandList) && (count($this->commandList) > 0)) {
            $parallelism = $this->getTrueParallelism();

            $executable = array(
                $this->binaryPath,
                '-j' . (($parallelism == 0) ? '+0' : ' ' . $parallelism)
            );

            if (is_array($this->serverList) && (count($this->serverList) > 0)) {
                $sshBits = '-S ' . implode(',', $this->serverList);
                $executable[] = $sshBits . ($this->remoteServersOnly ? '' : ',:');
            }

            if ($this->sameOrder) {
                $executable[] = '-k';
            }

            /**
             * If both the "output to files" and "structured output to directories"
             * options are requested, outputting to directories takes precedence.
             */
            if ($this->outputToDirectories) {

                if (!empty($this->outputDirectoryHeader)) {
                    $executable[] = '--header :';
                    array_unshift($this->commandList, $this->outputDirectoryHeader);
                }

                $executable[] = '--results ' . $this->outputDirectory;
            } elseif ($this->outputToFiles) {
                $executable[] = '--tmpdir ' . $this->tempDirectory;
                $executable[] = '--files';
            }

            $parallelCommand = implode(
                ' ',
                array_merge(
                    $executable,
                    array(':::'),
                    $this->commandList
                )
            );

            return $getCommandOnly ? $parallelCommand : shell_exec($parallelCommand);
        } else {
            return false;
        }

    }

    /**
     * Tells parallel if the output should be saved in files
     *
     * @param bool $flag
     * @return bool
     */
    public function saveOutputInFiles($flag)
    {
        $this->outputToFiles = (is_bool($flag) || is_integer($flag)) ? (bool)$flag : false;
        return $this->outputToFiles;
    }

    /**
     * Allows for saving output in a structured way.
     * (See http://www.gnu.org/software/parallel/parallel_tutorial.html#saving_output_into_files)
     *
     * @param bool $flag
     * @return bool
     */
    public function saveOutputInDirectories($flag)
    {
        $this->outputToDirectories = (is_bool($flag) || is_integer($flag)) ? (bool)$flag : false;
        return $this->outputToDirectories;
    }

    /**
     * Sets the value of maximum parallelism
     *
     * 0 or "auto" sets the value to the number of processor cores
     * on the target server
     *
     * @param int|'auto' $value
     * @return int
     */
    public function setMaxParallelism($value)
    {
        $value = ($value == 'auto') ? 0 : $value;
        $this->maxParallelism = (is_numeric($value) && ($value > -1))
            ? intval($value)
            : self::DEFAULT_MAX_PARALLELISM;
        return $this->maxParallelism;
    }

    /**
     * Sets the value of parallelism
     *
     * 0 or "auto" sets the value to autodetect, which in turn means
     * that parallelism will either be set to the number of commands
     * on the command list, or to the value of $this->maxParallelism (the
     * higher value will be applied).
     *
     * @param int $value
     * @return int
     */
    public function setParallelism($value)
    {

        if (in_array($value, array('auto', 0, '0'))) {
            $this->parallelism = 0;
        } else{
            $this->parallelism = (is_numeric($value) && ($value > 0)) ? intval($value) : 0;
        }

        return $this->parallelism;
    }

    /**
     * Sets output directory for saving output in a structured way.
     * (See http://www.gnu.org/software/parallel/parallel_tutorial.html#saving_output_into_files)
     *
     * @param string $outputDirectory
     * @param string $header
     * @return bool
     * @throws InvalidOutputDirectoryException
     */
    public function setResultsDirectory($outputDirectory, $header = '')
    {

        if (is_dir($outputDirectory) && is_writable($outputDirectory)) {
            $this->outputDirectory = $outputDirectory;
            $this->outputDirectoryHeader = is_string($header) ? $header : '';
            return true;
        } else {
            throw new InvalidOutputDirectoryException();
        }

    }

    /**
     * Sets temp directory path, provided the temp dir $path is valid
     *
     * @param string $path
     * @return bool
     * @throws InvalidTempDirectoryException
     */
    public function setTempDirectory($path)
    {

        if (is_dir($path) && is_writable($path)) {
            $this->tempDirectory = $path;
            return true;
        } else {
            throw new InvalidTempDirectoryException();
        }

    }

    /**
     * Indicates whether the local machine should be included in the
     * list of servers when executing on multiple hosts
     *
     * @param bool $flag
     * @return bool
     */
    public function useRemoteOnly($flag)
    {
        $this->remoteServersOnly = (is_bool($flag) || is_integer($flag)) ? (bool)$flag : false;
        return $this->remoteServersOnly;
    }
}
