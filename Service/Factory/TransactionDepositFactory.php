<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\TokenPackage;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionDeposit;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionDepositAdmin;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionDepositBonus;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionDonate;
use Yusmp\CoreComponent\Domain\Payment\Service\CurrencyRateService;
use Yusmp\CoreComponent\Domain\Room\Enum\PaymentSystemEnum;
use Yusmp\CoreComponent\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TransactionDepositFactory
{
    private EntityManagerInterface $entityManager;
    private CurrencyRateService $currencyRateService;
    private PaymentFactory $paymentFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        CurrencyRateService $currencyRateService,
        PaymentFactory $paymentFactory
    ) {
        $this->entityManager = $entityManager;
        $this->currencyRateService = $currencyRateService;
        $this->paymentFactory = $paymentFactory;
    }

    public function createTransactionDonateByAmount(
        User $user,
        int $price,
        PaymentSystemEnum $paymentSystem
    ): TransactionDonate {
        $transaction = new TransactionDonate();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount(0);
        $transaction->setPrice($price);

        $payment = $this->paymentFactory->createPaymentByAmount(
            $user,
            $paymentSystem,
            $price,
            'USD'
        );

        $transaction->setPayment($payment);
        $payment->setTransaction($transaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function createTransactionDepositByAmount(
        User $user,
        int $amount
    ): TransactionDepositAdmin {
        $transaction = new TransactionDepositAdmin();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount($amount);
        $transaction->setPrice($this->currencyRateService->convertTokenToUsd($amount));

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function createTransactionDepositBonus(
        User $user,
        int $bonusTokens
    ): TransactionDepositBonus {
        $transaction = new TransactionDepositBonus();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount($bonusTokens);
        $transaction->setPrice($this->currencyRateService->convertTokenToUsd($bonusTokens));

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function createTransactionDepositByTokenPackage(
        User $user,
        TokenPackage $package,
        PaymentSystemEnum $paymentSystem
    ): TransactionDeposit {
        $transaction = new TransactionDeposit();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount($package->getTokenAmount());
        $transaction->setPrice($package->getPrice());

        $payment = $this->paymentFactory->createPaymentByAmount(
            $user,
            $paymentSystem,
            $package->getPrice(),
            'USD'
        );

        $transaction->setPayment($payment);
        $payment->setTransaction($transaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

}
