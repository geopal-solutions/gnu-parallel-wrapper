<?php

namespace tests;

use Parallel\Exceptions\InvalidBinaryException;
use Parallel\Wrapper;

class WrapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Data provider to test configuring Wrapper via its constructor
     *
     * @return array
     */
    public function providerTestConstructor()
    {
        $provider = array();

        $provider[] = array(
            array(),
            array(
                'binaryPath' => '/usr/local/bin/parallel',
                'commandList' => array(),
                'maxParallelism' => Wrapper::DEFAULT_MAX_PARALLELISM,
                'parallelism' => 0,
                'remoteServersOnly' => false,
                'sameOrder' => false,
                'serverList' => array()
            )
        );

        $provider[] = array(
            array(
                'commandList' => null,
                'maxParallelism' => null
            ),
            array(
                'binaryPath' => '/usr/local/bin/parallel',
                'commandList' => array(),
                'maxParallelism' => Wrapper::DEFAULT_MAX_PARALLELISM,
                'parallelism' => 0,
                'remoteServersOnly' => false,
                'sameOrder' => false,
                'serverList' => array()
            )
        );

        $provider[] = array(
            array(
                'commandList' => array('test 1', 'test 2'),
                'maxParallelism' => -1
            ),
            array(
                'binaryPath' => '/usr/local/bin/parallel',
                'commandList' => array("'test 1'", "'test 2'"),
                'maxParallelism' => Wrapper::DEFAULT_MAX_PARALLELISM,
                'parallelism' => 0,
                'remoteServersOnly' => false,
                'sameOrder' => false,
                'serverList' => array()
            )
        );

        $provider[] = array(
            array(
                'commandList' => array('test 1', 'test 2'),
                'maxParallelism' => 3
            ),
            array(
                'binaryPath' => '/usr/local/bin/parallel',
                'commandList' => array("'test 1'", "'test 2'"),
                'maxParallelism' => 3,
                'parallelism' => 0,
                'remoteServersOnly' => false,
                'sameOrder' => false,
                'serverList' => array()
            )
        );

        return $provider;
    }

    /**
     * Test Wrapper::__construct
     *
     * @param array $initParams
     * @param array $afterInitState
     *
     * @covers \Parallel\Wrapper::__construct()
     * @dataProvider providerTestConstructor
     */
    public function testConstructor($initParams, $afterInitState)
    {

        if (isset($initParams['commandList']) && isset($initParams['maxParallelism'])) {
            $wrapper = new Wrapper('', $initParams['commandList'], $initParams['maxParallelism']);
        } else {
            $wrapper = new Wrapper();
        }

        foreach ($afterInitState as $paramName => $paramValue) {
            $this->assertEquals($paramValue, $this->getReflectionPropertyValue($wrapper, $paramName));
        }

    }

    /**
     * Data provider for testing the addCommand method
     *
     * @return array
     */
    public function providerTestAddCommand()
    {
        $provider = array();

        $provider[] = array(
            'test command',
            true,
            array(
                "'test command'"
            )
        );

        $provider[] = array(
            array(
                'test command 1',
                'test command 2'
            ),
            true,
            array(
                "'test command 1'",
                "'test command 2'"
            )
        );

        $provider[] = array(null, false, array());

        $provider[] = array('', false, array());

        return $provider;
    }

    /**
     * Tests Wrapper::addCommand
     *
     * @param array|string $command
     * @param array|string $output
     * @param array $commandList
     *
     * @covers \Parallel\Wrapper::addCommand
     * @dataProvider providerTestAddCommand
     */
    public function testAddCommand($command, $output, $commandList)
    {
        $wrapper = new Wrapper();
        $this->assertEquals($output, $wrapper->addCommand($command));
        $this->assertEquals($commandList, $this->getReflectionPropertyValue($wrapper, 'commandList'));
    }

    /**
     * Data provider for testing the addServer method
     *
     * @return array
     */
    public function providerTestAddServer()
    {
        $provider = array();

        $provider[] = array(
            'test server',
            true,
            array(
                'test server'
            )
        );

        $provider[] = array(
            array(
                'test server 1',
                'test server 2'
            ),
            true,
            array(
                'test server 1',
                'test server 2'
            )
        );

        $provider[] = array(null, false, array());

        $provider[] = array('', false, array());

        return $provider;
    }

    /**
     * Tests Wrapper::addServer
     *
     * @param array|string $server
     * @param array|string $output
     * @param array $serverList
     *
     * @covers \Parallel\Wrapper::addServer
     * @dataProvider providerTestAddServer
     */
    public function testAddServer($server, $output, $serverList)
    {
        $wrapper = new Wrapper();
        $this->assertEquals($output, $wrapper->addServer($server));
        $this->assertEquals($serverList, $this->getReflectionPropertyValue($wrapper, 'serverList'));
    }

    /**
     * Data provider for testing the getTrueParallelism method
     *
     * @return array
     */
    public function providerTestGetTrueParallelism()
    {
        $commands = array('test 1', 'test 2', 'test 3');
        return array(
            array($commands, 0, 4, 3),
            array($commands, 0, 2, 2),
            array($commands, 2, 4, 2),
            array($commands, 3, 2, 2)
        );
    }

    /**
     * Tests Wrapper::getTrueParallelism
     *
     * @param array $commands
     * @param int $parallelism
     * @param int $maxParallelism
     * @param int $expectedResult
     *
     * @covers \Parallel\Wrapper::getTrueParallelism
     * @dataProvider providerTestGetTrueParallelism
     */
    public function testGetTrueParallelism($commands, $parallelism, $maxParallelism, $expectedResult)
    {
        $wrapper = new Wrapper();
        $wrapper->addCommand($commands);
        $wrapper->setParallelism($parallelism);
        $wrapper->setMaxParallelism($maxParallelism);
        $this->assertEquals($expectedResult, $this->getReflectionMethodResult($wrapper, 'getTrueParallelism'));
    }

    /**
     * Data provider for testing the initBinary method
     *
     * @return array
     */
    public function providerTestInitBinary()
    {
        $provider = array(
            array('', false),
            array(null, false),
            array('`"\'/\\@?:;%$&*!`', false)
        );

        if (isset($_ENV['SHELL']) && is_executable($_ENV['SHELL'])) {
            $provider[] = array($_ENV['SHELL'], true);
        }

        return $provider;
    }

    /**
     * Tests Wrapper::initBinary
     *
     * @param string $binaryPathToTest
     * @param bool $expectSuccess
     *
     * @covers \Parallel\Wrapper::initBinary
     * @dataProvider providerTestInitBinary
     */
    public function testInitBinary($binaryPathToTest, $expectSuccess)
    {
        $wrapper = new Wrapper();

        if ($expectSuccess) {
            $this->assertTrue($wrapper->initBinary($binaryPathToTest));

            $storedBinaryPath = $this->getReflectionPropertyValue($wrapper, 'binaryPath');
            $this->assertEquals($binaryPathToTest, $storedBinaryPath);
        } else {
            $exceptionThrown = false;

            try {
                $wrapper->initBinary($binaryPathToTest);
            } catch (InvalidBinaryException $exception) {
                $this->assertTrue($exception instanceof InvalidBinaryException);
                $exceptionThrown = true;
            }

            $this->assertTrue($exceptionThrown);
        }

    }

    /**
     * Data provider for testing the keepSameOrder method
     *
     * @return array
     */
    public function providerTestKeepSameOrder()
    {
        return array(
            array(true, true),
            array(false, false),
            array(null, false),
            array('string', false),
            array(array(true), false)
        );
    }

    /**
     * Tests Wrapper::keepSameOrder
     *
     * @param bool $input
     * @param bool $result
     *
     * @covers \Parallel\Wrapper::keepSameOrder
     * @dataProvider providerTestKeepSameOrder
     */
    public function testKeepSameOrder($input, $result)
    {
        $wrapper = new Wrapper();
        $this->assertEquals($result, $this->getReflectionMethodResult($wrapper, 'keepSameOrder', array($input)));
        $this->assertEquals($result, $this->getReflectionPropertyValue($wrapper, 'sameOrder'));
    }

    /**
     * Data provider for testing the run method
     *
     * @return array
     */
    public function providerTestRun()
    {
        $binaryPath = Wrapper::DEFAULT_BINARY_PATH;
        return array(
            array(
                'test command',
                $binaryPath . " -j 1 ::: 'test command'"
            ),
            array(
                "'test command'",
                $binaryPath . " -j 1 ::: ''\''test command'\'''"
            ),
            array(
                array('test 1', 'test 2', 'test 3'),
                $binaryPath . " -j 3 ::: 'test 1' 'test 2' 'test 3'"
            ),
            array(
                array("'test 1'", "'test 2'", "'test 3'"),
                $binaryPath . " -j 3 ::: ''\''test 1'\''' ''\''test 2'\''' ''\''test 3'\'''"
            ),
            array(
                '',
                false
            ),
            array(
                null,
                false
            )
        );
    }

    /**
     * Tests Wrapper::run
     *
     * @param array $commandList
     * @param string $expectedOutput
     *
     * @covers \Parallel\Wrapper::run
     * @dataProvider providerTestRun
     */
    public function testRun($commandList, $expectedOutput)
    {
        $wrapper = new Wrapper();
        $wrapper->addCommand($commandList);
        $this->assertEquals($expectedOutput, $wrapper->run(true));
    }

    /**
     * Data provider for testing the setMaxParallelism method
     *
     * @return array
     */
    public function providerSetMaxParallelism()
    {
        return array(
            array("auto", 0),
            array(0, 0),
            array(-1, Wrapper::DEFAULT_MAX_PARALLELISM),
            array(1, 1),
            array('2', 2),
            array(100, 100)
        );
    }

    /**
     * Tests Wrapper::setMaxParallelism
     *
     * @param int $input
     * @param int $expectedResult
     *
     * @covers \Parallel\Wrapper::setMaxParallelism
     * @dataProvider providerSetMaxParallelism
     */
    public function testSetMaxParallelism($input, $expectedResult)
    {
        $wrapper = new Wrapper();
        $this->assertEquals($expectedResult, $wrapper->setMaxParallelism($input));
    }

    /**
     * Data provider for testing the setMaxParallelism method
     *
     * @return array
     */
    public function providerSetParallelism()
    {
        return array(
            array(0, 0),
            array(-1, 0),
            array(2, 2),
            array('2', 2),
            array(100, 100)
        );
    }

    /**
     * Tests Wrapper::setParallelism
     *
     * @param int $input
     * @param int $expectedResult
     *
     * @covers \Parallel\Wrapper::setParallelism
     * @dataProvider providerSetParallelism
     */
    public function testSetParallelism($input, $expectedResult)
    {
        $wrapper = new Wrapper();
        $this->assertEquals($expectedResult, $wrapper->setParallelism($input));
    }

    /**
     * Data provider for testing the useRemoteOnly method
     *
     * @return array
     */
    public function providerTestUseRemoteOnly()
    {
        return array(
            array(true, true),
            array(false, false),
            array(1, true),
            array(0, false),
            array("true", false),
            array("false", false),
            array(null, false)
        );
    }

    /**
     * Tests Wrapper::useRemoteOnly
     *
     * @param int $input
     * @param int $expectedResult
     *
     * @covers \Parallel\Wrapper::useRemoteOnly
     * @dataProvider providerTestUseRemoteOnly
     */
    public function testUseRemoteOnly($input, $expectedResult)
    {
        $wrapper = new Wrapper();
        $this->assertEquals($expectedResult, $wrapper->useRemoteOnly($input));
        $this->assertEquals($expectedResult, $this->getReflectionPropertyValue($wrapper, 'remoteServersOnly'));
    }

    /**
     * Data provider for testing the run method with remote servers
     *
     * @return array
     */
    public function providerTestRunWithSsh()
    {
        $binaryPath = Wrapper::DEFAULT_BINARY_PATH;
        return array(
            array(
                false,
                'testServer',
                'test command',
                $binaryPath . " -j+0 -S testServer,: ::: 'test command'"
            ),
            array(
                true,
                array('testServer1', 'testServer2'),
                "'test command'",
                $binaryPath . " -j+0 -S testServer1,testServer2 ::: ''\''test command'\'''"
            ),
            array(
                false,
                'testServer',
                'test command',
                $binaryPath . " -j+0 -S testServer,: ::: 'test command'"
            ),
            array(
                true,
                array('testServer1', 'testServer2'),
                "'test command'",
                $binaryPath . " -j+0 -S testServer1,testServer2 ::: ''\''test command'\'''"
            ),
            array(
                false,
                'testServer',
                array('test 1', 'test 2', 'test 3'),
                $binaryPath . " -j+0 -S testServer,: ::: 'test 1' 'test 2' 'test 3'"
            ),
            array(
                true,
                array('testServer1', 'testServer2'),
                array("'test 1'", "'test 2'", "'test 3'"),
                $binaryPath . " -j+0 -S testServer1,testServer2 ::: " .
                    "''\''test 1'\''' ''\''test 2'\''' ''\''test 3'\'''"
            ),
            array(
                false,
                'testServer',
                array('test 1', 'test 2', 'test 3'),
                $binaryPath . " -j+0 -S testServer,: ::: 'test 1' 'test 2' 'test 3'"
            ),
            array(
                true,
                array('testServer1', 'testServer2'),
                array("'test 1'", "'test 2'", "'test 3'"),
                $binaryPath . " -j+0 -S testServer1,testServer2 ::: " .
                    "''\''test 1'\''' ''\''test 2'\''' ''\''test 3'\'''"
            ),
            array(
                false,
                '',
                '',
                false
            ),
            array(
                true,
                null,
                null,
                false
            )
        );
    }

    /**
     * Tests Wrapper::run with remote servers
     *
     * @param bool $remoteServersOnly
     * @param array|string $serverList
     * @param array|string $commandList
     * @param string $expectedResult
     *
     * @covers \Parallel\Wrapper::run
     * @dataProvider providerTestRunWithSsh
     */
    public function testRunWithSsh($remoteServersOnly, $serverList, $commandList, $expectedResult)
    {
        $wrapper = new Wrapper();

        $wrapper->setMaxParallelism('auto');
        $wrapper->useRemoteOnly($remoteServersOnly);
        $wrapper->addServer($serverList);
        $wrapper->addCommand($commandList);

        $this->assertEquals($expectedResult, $wrapper->run(true));
    }


    ///// Helper functions /////


    /**
     * @param object $object
     * @param string $methodName
     * @param array $params
     * @return mixed
     */
    private function getReflectionMethodResult($object, $methodName, $params = array())
    {
        $reflectionMethod = new \ReflectionMethod(get_class($object), $methodName);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($object, $params);
    }

    /**
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    private function getReflectionPropertyValue($object, $propertyName)
    {
        $reflectionProperty = new \ReflectionProperty(get_class($object), $propertyName);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty->getValue($object);
    }
}
