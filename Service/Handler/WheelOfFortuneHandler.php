<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberWheelOfFortune;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Yusmp\CoreComponent\Services\Games\WheelOfFortuneService;

class WheelOfFortuneHandler implements TransactionHandlerInterface
{
    private WheelOfFortuneService $wheelOfFortune;

    public function __construct(
        WheelOfFortuneService $wheelOfFortune
    ) {
        $this->wheelOfFortune = $wheelOfFortune;
    }

    public function supports(TransactionInterface $transaction): bool
    {
        return $transaction instanceof TransactionMemberWheelOfFortune;
    }

    public function handle(TransactionInterface $transaction)
    {
        assert($transaction instanceof TransactionMemberWheelOfFortune);

        $result = $this->wheelOfFortune->roll($transaction->getUser(), $transaction->getRoom());
        $transaction->setWheelOfFortune($result);
    }
}
