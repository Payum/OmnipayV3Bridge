<?php

namespace Payum\OmnipayV3Bridge\Tests\Action;

use Omnipay\Common\Message\AbstractResponse as OmnipayAbstractResponse;
use Omnipay\Common\Message\RequestInterface as OmnipayRequestInterface;
use Omnipay\Common\Message\ResponseInterface as OmnipayResponseInterface;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\Notify;
use Payum\Core\Tests\GenericActionTest;
use Payum\OmnipayV3Bridge\Action\NotifyAction;
use Payum\OmnipayV3Bridge\Tests\OffsiteGateway;

/**
 * @author Steffen Brem <steffenbrem@gmail.com>
 */
class NotifyActionTest extends GenericActionTest
{
    protected $actionClass = NotifyAction::class;

    protected $requestClass = Notify::class;

    protected function setUp(): void
    {
        $this->action = new $this->actionClass();
        $this->action->setApi(new OffsiteGateway());
    }

    /**
     * @test
     */
    public function shouldSetStatusCapturedWhenSuccessful()
    {
        $model = new \ArrayObject([]);

        $responseMock = $this->createMock(OmnipayResponseInterface::class);
        $responseMock
            ->method('isSuccessful')
            ->willReturn(true)
        ;

        $requestMock = $this->createMock(OmnipayRequestInterface::class);
        $requestMock
            ->expects($this->once())
            ->method('send')
            ->willReturn($responseMock)
        ;

        $action = new NotifyAction();

        $gateway = new OffsiteGateway();
        $gateway->returnOnCompletePurchase = $requestMock;
        $action->setApi($gateway);

        try {
            $action->execute(new Notify($model));
        } catch (HttpResponse $e) {
            $this->assertSame(200, $e->getStatusCode());
        }

        $details = iterator_to_array($model);

        $this->assertSame('captured', $details['_status']);
    }

    /**
     * @test
     */
    public function shouldSetStatusFailedWhenNotSuccessful()
    {
        $model = new \ArrayObject([]);

        $responseMock = $this->createMock(OmnipayResponseInterface::class);
        $responseMock
            ->method('isSuccessful')
            ->willReturn(false)
        ;

        $requestMock = $this->createMock(OmnipayRequestInterface::class);
        $requestMock
            ->expects($this->once())
            ->method('send')
            ->willReturn($responseMock)
        ;

        $action = new NotifyAction();

        $gateway = new OffsiteGateway();
        $gateway->returnOnCompletePurchase = $requestMock;
        $action->setApi($gateway);

        try {
            $action->execute(new Notify($model));
        } catch (HttpResponse $e) {
            $this->assertSame(200, $e->getStatusCode());
        }

        $details = iterator_to_array($model);

        $this->assertSame('failed', $details['_status']);
    }
}
