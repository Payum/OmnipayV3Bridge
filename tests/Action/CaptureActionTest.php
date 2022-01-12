<?php
namespace Payum\OmnipayV3Bridge\Tests\Action;

use Omnipay\Common\Message\AbstractResponse as OmnipayAbstractResponse;
use Omnipay\Common\Message\RequestInterface as OmnipayRequestInterface;
use Omnipay\Common\Message\ResponseInterface as OmnipayResponseInterface;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Model\CreditCard;
use Payum\Core\Request\Capture;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\ObtainCreditCard;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\SensitiveValue;
use Payum\Core\Tests\GenericActionTest;
use Payum\OmnipayV3Bridge\Action\BaseApiAwareAction;
use Payum\OmnipayV3Bridge\Action\CaptureAction;
use Payum\OmnipayV3Bridge\Tests\CreditCardGateway;
use Payum\OmnipayV3Bridge\Tests\OffsiteGateway;

class CaptureActionTest extends GenericActionTest
{
    protected $actionClass = CaptureAction::class;

    protected $requestClass = Capture::class;

    /**
     * @var  CaptureAction
     */
    protected $action;

    protected function setUp(): void
    {
        $this->action = new $this->actionClass();
        $this->action->setApi(new CreditCardGateway());
    }

    /**
     * @test
     */
    public function shouldBeSubClassOfBaseApiAwareAction()
    {
        $rc = new \ReflectionClass(CaptureAction::class);

        $this->assertTrue($rc->isSubclassOf(BaseApiAwareAction::class));
    }

    /**
     * @test
     */
    public function shouldImplementInterfaceGatewayAwareAction()
    {
        $rc = new \ReflectionClass(CaptureAction::class);

        $this->assertTrue($rc->implementsInterface(GatewayAwareInterface::class));
    }

    /**
     * @test
     */
    public function shouldImplementInterfaceGenericTokenFactoryAwareInterface()
    {
        $rc = new \ReflectionClass(CaptureAction::class);

        $this->assertTrue($rc->implementsInterface(GenericTokenFactoryAwareInterface::class));
    }

    public function shouldNotSupportIfOffsiteOmnipayGatewaySetAsApi()
    {
        $this->action->setApi(new OffsiteGateway());

        $this->assertFalse($this->action->supports(new Capture([])));
        $this->assertFalse($this->action->supports(new Capture(new \ArrayObject())));
    }

    /**
     * @test
     *
     * @dataProvider provideDetails
     */
    public function throwsIfPurchaseMethodReturnResponseNotInstanceOfAbstractResponse($details)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The bridge supports only responses which extends AbstractResponse. Their ResponseInterface is useless.');
        $requestMock = $this->createMock(OmnipayRequestInterface::class);
        $requestMock
            ->expects($this->once())
            ->method('send')
            ->willReturn($this->createMock(OmnipayResponseInterface::class))
        ;

        $gateway = new CreditCardGateway();
        $gateway->returnOnPurchase = $requestMock;

        $action = new CaptureAction;
        $action->setApi($gateway);
        $action->setGateway($this->createGatewayMock());

        $action->execute(new Capture($details));
    }

    /**
     * @test
     *
     * @dataProvider provideDetails
     */
    public function shouldCallGatewayPurchaseMethodWithExpectedArguments($details)
    {
        $responseMock = $this->createMock(OmnipayAbstractResponse::class, [], [], '', false);
        $responseMock
            ->expects($this->once())
            ->method('getData')
            ->willReturn([])
        ;

        $requestMock = $this->createMock(OmnipayRequestInterface::class);
        $requestMock
            ->expects($this->once())
            ->method('send')
            ->willReturn($responseMock)
        ;

        $gateway = new CreditCardGateway();
        $gateway->returnOnPurchase = $requestMock;

        $action = new CaptureAction;
        $action->setApi($gateway);
        $action->setGateway($this->createGatewayMock());

        $action->execute(new Capture($details));
    }

    /**
     * @test
     */
    public function shouldObtainCreditCardAndPopulateCardFieldIfNotSet()
    {
        $firstModel = new \stdClass();
        $model = new \ArrayObject([]);

        $responseMock = $this->createMock(OmnipayAbstractResponse::class, [], [], '', false);
        $responseMock
            ->expects($this->once())
            ->method('getData')
            ->willReturn([])
        ;

        $requestMock = $this->createMock(OmnipayRequestInterface::class);
        $requestMock
            ->expects($this->once())
            ->method('send')
            ->willReturn($responseMock)
        ;

        $omnipayGateway = $this->createMock(CreditCardGateway::class, [], [], '', false);
        $omnipayGateway
            ->expects($this->once())
            ->method('purchase')
            ->with([
                'card' => [
                    'number' => '4111111111111111',
                    'cvv' => '123',
                    'expiryMonth' => '11',
                    'expiryYear' => '10',
                    'firstName' => 'John Doe',
                    'lastName' => '',
                ],
                'clientIp' => '',
            ])
            ->willReturn($requestMock)
        ;

        $gateway = $this->createGatewayMock();
        $gateway
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->isInstanceOf(ObtainCreditCard::class)],
                [$this->isInstanceOf(GetHttpRequest::class)]
            )
            ->will($this->onConsecutiveCalls(
                $this->returnCallback(function(ObtainCreditCard $request) use ($firstModel, $model) {
                    $this->assertSame($firstModel, $request->getFirstModel());
                    $this->assertSame($model, $request->getModel());

                    $card = new CreditCard();
                    $card->setExpireAt(new \DateTime('2010-11-12'));
                    $card->setHolder('John Doe');
                    $card->setNumber('4111111111111111');
                    $card->setSecurityCode('123');

                    $request->set($card);
                })
            ))
        ;

        $action = new CaptureAction;
        $action->setApi($omnipayGateway);
        $action->setGateway($gateway);

        $capture = new Capture($firstModel);
        $capture->setModel($model);

        $action->execute($capture);

        $details = iterator_to_array($model);

        $this->assertArrayNotHasKey('cardReference', $details);
        $this->assertArrayHasKey('card', $details);
        $this->assertInstanceOf(SensitiveValue::class, $details['card']);
        $this->assertNull($details['card']->peek(), 'The card must be already erased');
    }

    /**
     * @test
     */
    public function shouldObtainCreditCardAndPopulateCardReferenceFieldIfNotSet()
    {
        $firstModel = new \stdClass();
        $model = new \ArrayObject([]);

        $responseMock = $this->createMock(OmnipayAbstractResponse::class, [], [], '', false);
        $responseMock
            ->expects($this->once())
            ->method('getData')
            ->willReturn([])
        ;

        $requestMock = $this->createMock(OmnipayRequestInterface::class);
        $requestMock
            ->expects($this->once())
            ->method('send')
            ->willReturn($responseMock)
        ;

        $omnipayGateway = $this->createMock(CreditCardGateway::class, [], [], '', false);
        $omnipayGateway
            ->expects($this->once())
            ->method('purchase')
            ->with([
                'cardReference' => 'theCardToken',
                'clientIp' => '',
            ])
            ->willReturn($requestMock)
        ;

        $gateway = $this->createGatewayMock();
        $gateway
            ->expects($this->atLeastOnce())
            ->method('execute')
            ->withConsecutive(
                [$this->isInstanceOf(ObtainCreditCard::class)],
                [$this->isInstanceOf(GetHttpRequest::class)]
            )
            ->will($this->onConsecutiveCalls(
                $this->returnCallback(function(ObtainCreditCard $request) use ($firstModel, $model) {
                    $this->assertSame($firstModel, $request->getFirstModel());
                    $this->assertSame($model, $request->getModel());

                    $card = new CreditCard();
                    $card->setToken('theCardToken');

                    $request->set($card);
                })
            ))
        ;

        $action = new CaptureAction;
        $action->setApi($omnipayGateway);
        $action->setGateway($gateway);

        $capture = new Capture($firstModel);
        $capture->setModel($model);

        $action->execute($capture);

        $details = iterator_to_array($model);

        $this->assertArrayNotHasKey('card', $details);
        $this->assertArrayHasKey('cardReference', $details);
        $this->assertSame('theCardToken', $details['cardReference']);
    }

    /**
     * @test
     */
    public function throwIfObtainCreditCardNotSupported()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Credit card details has to be set explicitly or there has to be an action that supports ObtainCreditCard request.');
        $omnipayGateway = $this->createMock(CreditCardGateway::class, [], [], '', false);
        $omnipayGateway
            ->expects($this->never())
            ->method('purchase')
        ;

        $gateway = $this->createGatewayMock();
        $gateway
            ->expects($this->atLeastOnce())
            ->method('execute')
            ->with($this->isInstanceOf(ObtainCreditCard::class))
            ->willThrowException(new RequestNotSupportedException())
        ;

        $action = new CaptureAction;
        $action->setApi($omnipayGateway);
        $action->setGateway($gateway);

        $action->execute(new Capture([]));
    }

    /**
     * @test
     */
    public function shouldDoNothingIfStatusAlreadySet()
    {
        $gatewayMock = $this->createMock(CreditCardGateway::class);
        $gatewayMock
            ->expects($this->never())
            ->method('purchase')
        ;

        $action = new CaptureAction;
        $action->setApi($gatewayMock);
        $action->setGateway($this->createGatewayMock());

        $action->execute(new Capture(array(
            '_status' => 'foo',
        )));
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|GatewayInterface
     */
    protected function createGatewayMock()
    {
        return $this->createMock(GatewayInterface::class, ['execute']);
    }

    public static function provideDetails(): \Iterator
    {
        yield array(
            array(
                'foo' => 'fooVal',
                'bar' => 'barVal',
                'card' => array('cvv' => 123),
                'clientIp' => '',
            )
        );
        yield array(
            array(
                'foo' => 'fooVal',
                'bar' => 'barVal',
                'cardReference' => 'abc',
                'clientIp' => '',
            )
        );
    }
}
