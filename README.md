gnu-parallel-wrapper
====================
Simple PHP wrapper class for the GNU Parallel tool.
Allows for running single threaded tasks to be run in parallel on one or multiple machines, from within PHP.

### Example:

```php
$parallel = new Wrapper();

try {
    $parallel->initBinary('/path/to/parallel/binary');

    $parallel->addCommand('/path/to/command/one.sh');
    $parallel->addCommand('/path/to/command/two.sh');
    $parallel->addCommand('/path/to/command/three.sh');

    $parallel->setParallelism('auto');
    $parallel->setMaxParallelism(10);

    $output = $parallel->run();
} catch (InvalidBinaryException $exception) {
    // The binary file does not exist, or is not executable
}
```
