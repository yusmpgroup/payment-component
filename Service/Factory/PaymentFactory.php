<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\Payment;
use Yusmp\CoreComponent\Domain\Room\Enum\PaymentSystemEnum;
use Yusmp\CoreComponent\Domain\User\Entity\User;
use Yusmp\PaymentComponent\Exception\PaymentException;
use Doctrine\ORM\EntityManagerInterface;

class PaymentFactory
{
    private EntityManagerInterface $entityManager;
    /** @var \Generator|PaymentFactoryHandlerInterface[] */
    private \Traversable $paymentFactoryHandlers;

    public function __construct(
        EntityManagerInterface $entityManager,
        \Traversable $paymentFactoryHandlers
    ) {
        $this->entityManager = $entityManager;
        $this->paymentFactoryHandlers = $paymentFactoryHandlers;
    }

    public function createPaymentByAmount(
        User $user,
        PaymentSystemEnum $paymentSystem,
        int $amount,
        string $currency
    ): Payment {
        foreach ($this->paymentFactoryHandlers as $paymentFactoryHandler) {
            if ($paymentFactoryHandler->supports($paymentSystem)) {
                return $paymentFactoryHandler->create($user, $amount, $currency);
            }
        }

        throw new PaymentException('error.payment-system-not-avail');
    }
}
