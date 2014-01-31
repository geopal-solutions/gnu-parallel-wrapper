gnu-parallel-wrapper
====================
Simple PHP wrapper class for the GNU Parallel tool.
Allows for running single threaded tasks in parallel on one or multiple machines, from within PHP.

### This package requires GNU Parallel to be installed beforehand!

In case you do not have Parallel on the machine you are running your PHP script on, an InvalidBinaryException will be thrown. (See examples.)

### Example 1

```php
/**
 *
 * Running commands on the local host
 *
 */

use Parallel\Exceptions\InvalidBinaryException;
use Parallel\Wrapper;

// You can initialize the Wrapper with or without parameters
$parallel = new Wrapper();

try {
    // Set path to binary
    $parallel->initBinary('/path/to/parallel/binary');

    // Add the commands you want to run in parallel
    $parallel->addCommand('/path/to/command/one.sh');
    $parallel->addCommand('/path/to/command/two.sh');
    $parallel->addCommand('/path/to/command/three.sh');

    /**
     * Setting the parallelism to 0 or "auto" will
     * result in a parallelism setting equal to the
     * number of commands you whish to run
     *
     * Use the maxParallelism setting to set a cap
     */
    $parallel->setParallelism('auto');
    $parallel->setMaxParallelism(10);

    // Run the commands and catch the output from the console
    $output = $parallel->run();
} catch (InvalidBinaryException $exception) {
    // The binary file does not exist, or is not executable
}
```

### Example 2

```php
/**
 *
 * Running commands on multiple hosts
 *
 */

use Parallel\Exceptions\InvalidBinaryException;
use Parallel\Wrapper;

$commands = array(
    '/path/to/command/one.sh',
    '/path/to/command/two.sh',
    '/path/to/command/three.sh'
);

$maxParallelism = 10;

// You can initialize the Wrapper with or without parameters
$parallel = new Wrapper('/path/to/parallel/binary', $commands, $maxParallelism);

try {
    /**
     * You can still set the parallelism manually, or leave it
     * to the wrapper to calculate it
     */
    $parallel->setParallelism(8);

    $parallel->addServer('foo@example1.com');
    $parallel->addServer('bar@example2.com');
    $parallel->addServer('baz@example3.com');

    /**
     * By default, the local host is also included
     * in the list of servers used for execution.
     * 
     * You can exclude it from the list by setting
     * the remoteOnly flag to true 
     */
    $parallel->useRemoteOnly(true);

    // Run the commands and catch the output from the console
    $output = $parallel->run();
} catch (InvalidBinaryException $exception) {
    // The binary file does not exist, or is not executable
}
```
