<?php
namespace Payum\OmnipayV3Bridge\Tests\Action;

use Omnipay\Common\GatewayInterface;
use Payum\Core\Exception\UnsupportedApiException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

class BaseApiAwareActionTest extends TestCase
{
    /**
     * @test
     */
    public function shouldImplementActionInterface()
    {
        $rc = new ReflectionClass('Payum\OmnipayV3Bridge\Action\BaseApiAwareAction');

        $this->assertTrue($rc->isSubclassOf('Payum\Core\Action\ActionInterface'));
    }

    /**
     * @test
     */
    public function shouldImplementApiAwareInterface()
    {
        $rc = new ReflectionClass('Payum\OmnipayV3Bridge\Action\BaseApiAwareAction');

        $this->assertTrue($rc->isSubclassOf('Payum\Core\ApiAwareInterface'));
    }

    /**
     * @test
     */
    public function shouldBeAbstract()
    {
        $rc = new ReflectionClass('Payum\OmnipayV3Bridge\Action\BaseApiAwareAction');

        $this->assertTrue($rc->isAbstract());
    }

    /**
     * @test
     */
    public function shouldAllowSetApi()
    {
        $expectedApi = $this->createGatewayMock();

        $action = $this->getMockForAbstractClass('Payum\OmnipayV3Bridge\Action\BaseApiAwareAction');

        $action->setApi($expectedApi);

        // Get private property
        $reflection = new ReflectionProperty($action, 'omnipayGateway');
        $reflection->setAccessible(true);

        $this->assertSame($reflection->getValue($action), $expectedApi);
    }

    /**
     * @test
     */
    public function throwIfUnsupportedApiGiven()
    {
        $this->expectException(UnsupportedApiException::class);
        $action = $this->getMockForAbstractClass('Payum\OmnipayV3Bridge\Action\BaseApiAwareAction');

        $action->setApi(new stdClass);
    }

    /**
     * @return MockObject|GatewayInterface
     */
    protected function createGatewayMock()
    {
        return $this->createMock('Omnipay\Common\GatewayInterface');
    }
}
