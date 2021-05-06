<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\Payment;
use Yusmp\CoreComponent\Domain\Payment\Entity\PaymentPayPal;
use Yusmp\CoreComponent\Domain\Room\Enum\PaymentSystemEnum;
use Yusmp\CoreComponent\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PaymentPayPalFactory implements PaymentFactoryHandlerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function create(User $user, int $amount, string $currency): Payment
    {
        $payment = new PaymentPayPal();
        $payment->setAmount($amount);
        $payment->setCurrency($currency);
        $payment->setUser($user);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function supports(PaymentSystemEnum $paymentSystem)
    {
        return $paymentSystem->equals(PaymentSystemEnum::PAYPAL());
    }
}
