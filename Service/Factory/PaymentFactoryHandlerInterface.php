<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\Payment;
use Yusmp\CoreComponent\Domain\Room\Enum\PaymentSystemEnum;
use Yusmp\CoreComponent\Domain\User\Entity\User;

interface PaymentFactoryHandlerInterface
{
    public function create(User $user, int $amount, string $currency): Payment;

    public function supports(PaymentSystemEnum $paymentSystem);
}
