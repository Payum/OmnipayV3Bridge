<?php
namespace Payum\OmnipayV3Bridge\Tests;

use Omnipay\Common\GatewayInterface as OmnipayGatewayInterface;
use Payum\Core\Gateway;
use Payum\Core\GatewayFactoryInterface;
use Payum\OmnipayV3Bridge\OmnipayGatewayFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OmnipayGatewayFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function shouldImplementGatewayFactoryInterface()
    {
        $rc = new \ReflectionClass(OmnipayGatewayFactory::class);

        $this->assertTrue($rc->implementsInterface(GatewayFactoryInterface::class));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function couldBeConstructedWithoutAnyArguments()
    {
        new OmnipayGatewayFactory();
    }

    /**
     * @test
     */
    public function shouldThrowIfRequiredOptionsNotPassed()
    {
        $this->expectException(\Payum\Core\Exception\LogicException::class);
        $this->expectExceptionMessage('The type fields are required.');
        $factory = new OmnipayGatewayFactory();

        $factory->create();
    }

    /**
     * @test
     */
    public function shouldAllowCreateGatewayConfig()
    {
        $factory = new OmnipayGatewayFactory();

        $config = $factory->createConfig();

        $this->assertIsArray($config);
        $this->assertNotEmpty($config);
    }

    /**
     * @test
     */
    public function shouldThrowIfTypeNotValid()
    {
        $this->expectException(\Payum\Core\Exception\LogicException::class);
        $this->expectExceptionMessage('Given omnipay gateway type Invalid or class is not supported.');
        $factory = new OmnipayGatewayFactory();

        $factory->create(array('type' => 'Invalid'));
    }

    /**
     * @return MockObject|OmnipayGatewayInterface
     */
    protected function createGatewayMock()
    {
        return $this->createMock(OmnipayGatewayInterface::class);
    }
}
