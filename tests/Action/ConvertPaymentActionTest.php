<?php
namespace Payum\OmnipayV3Bridge\Tests\Action\Api;

use Payum\Core\Model\CreditCard;
use Payum\Core\Request\GetCurrency;
use Payum\Core\Security\SensitiveValue;
use Payum\OmnipayV3Bridge\Action\ConvertPaymentAction;
use Payum\Core\Model\Payment;
use Payum\Core\Request\Convert;
use Payum\Core\Tests\GenericActionTest;

class ConvertPaymentActionTest extends GenericActionTest
{
    protected $actionClass = 'Payum\OmnipayV3Bridge\Action\ConvertPaymentAction';

    protected $requestClass = 'Payum\Core\Request\Convert';

    public function provideSupportedRequests(): \Iterator
    {
        yield array(new $this->requestClass(new Payment, 'array'));
        yield array(new $this->requestClass($this->createMock('Payum\Core\Model\PaymentInterface'), 'array'));
        yield array(new $this->requestClass(new Payment, 'array', $this->createMock('Payum\Core\Security\TokenInterface')));
    }

    public function provideNotSupportedRequests(): \Iterator
    {
        yield array('foo');
        yield array(array('foo'));
        yield array(new \stdClass());
        yield array($this->getMockForAbstractClass('Payum\Core\Request\Generic', array(array())));
        yield array(new $this->requestClass($this->createMock('Payum\Core\Model\PaymentInterface'), 'notArray'));
    }

    /**
     * @test
     */
    public function shouldCorrectlyConvertPaymentToDetailsArray()
    {
        $gatewayMock = $this->createMock('Payum\Core\GatewayInterface');
        $gatewayMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf('Payum\Core\Request\GetCurrency'))
            ->willReturnCallback(function(GetCurrency $request) {
                $request->name = 'US Dollar';
                $request->alpha3 = 'USD';
                $request->numeric = 123;
                $request->exp = 2;
                $request->country = 'US';
            })
        ;

        $payment = new Payment;
        $payment->setNumber('theNumber');
        $payment->setCurrencyCode('USD');
        $payment->setTotalAmount(123);
        $payment->setDescription('the description');
        $payment->setClientId('theClientId');
        $payment->setClientEmail('theClientEmail');

        $action = new ConvertPaymentAction;
        $action->setGateway($gatewayMock);

        $action->execute($convert = new Convert($payment, 'array'));
        $details = $convert->getResult();

        $this->assertNotEmpty($details);

        $this->assertArrayHasKey('amount', $details);
        $this->assertSame(1.23, $details['amount']);

        $this->assertArrayHasKey('currency', $details);
        $this->assertSame('USD', $details['currency']);

        $this->assertArrayHasKey('description', $details);
        $this->assertSame('the description', $details['description']);
    }

    /**
     * @test
     */
    public function shouldCorrectlyConvertPaymentWithCreditCardToDetailsArray()
    {
        $gatewayMock = $this->createMock('Payum\Core\GatewayInterface');
        $gatewayMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf('Payum\Core\Request\GetCurrency'))
            ->willReturnCallback(function(GetCurrency $request) {
                $request->name = 'US Dollar';
                $request->alpha3 = 'USD';
                $request->numeric = 123;
                $request->exp = 2;
                $request->country = 'US';
            })
        ;

        $creditCard = new CreditCard();
        $creditCard->setNumber('4444333322221111');
        $creditCard->setHolder('John Doe');
        $creditCard->setSecurityCode('322');
        $creditCard->setExpireAt(new \DateTime('2015-11-12'));

        $payment = new Payment;
        $payment->setNumber('theNumber');
        $payment->setCurrencyCode('USD');
        $payment->setTotalAmount(123);
        $payment->setDescription('the description');
        $payment->setClientId('theClientId');
        $payment->setClientEmail('theClientEmail');
        $payment->setCreditCard($creditCard);

        $action = new ConvertPaymentAction;
        $action->setGateway($gatewayMock);

        $action->execute($convert = new Convert($payment, 'array'));
        $details = $convert->getResult();

        $this->assertNotEmpty($details);

        $this->assertArrayHasKey('card', $details);
        $this->assertInstanceOf(SensitiveValue::class, $details['card']);
        $this->assertEquals(array(
            'number' => '4444333322221111',
            'cvv' => '322',
            'expiryMonth' => '11',
            'expiryYear' => '15',
            'firstName' => 'John Doe',
            'lastName' => '',
        ), $details['card']->peek());
    }

    /**
     * @test
     */
    public function shouldNotOverwriteAlreadySetExtraDetails()
    {
        $gatewayMock = $this->createMock('Payum\Core\GatewayInterface');
        $gatewayMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf('Payum\Core\Request\GetCurrency'))
            ->willReturnCallback(function(GetCurrency $request) {
                $request->name = 'US Dollar';
                $request->alpha3 = 'USD';
                $request->numeric = 123;
                $request->exp = 2;
                $request->country = 'US';
            })
        ;

        $payment = new Payment;
        $payment->setCurrencyCode('USD');
        $payment->setTotalAmount(123);
        $payment->setDescription('the description');
        $payment->setDetails(array(
            'foo' => 'fooVal',
        ));

        $action = new ConvertPaymentAction;
        $action->setGateway($gatewayMock);

        $action->execute($convert = new Convert($payment, 'array'));
        $details = $convert->getResult();

        $this->assertNotEmpty($details);

        $this->assertArrayHasKey('foo', $details);
        $this->assertSame('fooVal', $details['foo']);
    }
}
