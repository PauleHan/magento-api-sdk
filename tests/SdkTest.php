<?php
namespace Triggmine\Test;

use Triggmine\Sdk;

/**
 * Class SdkTest
 *
 * @cover Triggmine\Sdk
 */
class SdkTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \BadMethodCallException
     */
    public function testEnsuresMissingMethodThrowsException()
    {
        (new Sdk)->foo();
    }

    public function testHasMagicMethods()
    {
        $sdk = $this->getMockBuilder('Triggmine\Sdk')
            ->setMethods(['createClient'])
            ->getMock();
        $sdk->expects($this->once())
            ->method('createClient')
            ->with('Foo', ['bar' => 'baz']);
        $sdk->createFoo(['bar' => 'baz']);
    }

    public function testCreateClients()
    {
        $this->assertInstanceOf(
            'Triggmine\TriggmineClientInterface',
            (new SDK)->createCommerce([
                'version'     => 'latest',
            ])
        );
    }
}