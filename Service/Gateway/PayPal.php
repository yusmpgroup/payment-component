<?php

namespace Yusmp\PaymentComponent\Service\Gateway;

use Yusmp\CoreComponent\Domain\Payment\Entity\Payment;
use Yusmp\CoreComponent\Domain\Payment\Enum\TransactionStatusEnum;
use Yusmp\PaymentComponent\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Omnipay\Omnipay;
use Omnipay\PayPal\Message\RestResponse;
use Omnipay\PayPal\RestGateway;
use Psr\Log\LoggerInterface;

class PayPal
{
    private RestGateway $gateway;
    private TransactionService $transactionService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        TransactionService $transactionService,
        EntityManagerInterface $entityManager,
        LoggerInterface $paymentLogger,
        string $payPalClientId,
        string $payPalSecret
    ) {
        $this->transactionService = $transactionService;
        $this->entityManager = $entityManager;
        $this->logger = $paymentLogger;

        $this->gateway = Omnipay::create('PayPal_Rest');
        $this->gateway->setParameter('clientId', $payPalClientId);
        $this->gateway->setParameter('secret', $payPalSecret);
        $this->gateway->setParameter('testMode', true);
    }

    public function initialize(Payment $payment)
    {
        $amountFormat = $payment->getAmount() / 100;
        $amountFormat = (string) $amountFormat;
        $request = $this->gateway->purchase([
            'amount' => $amountFormat,
            //'currency' => $payment->getCurrency(), // TODO: currency
            //'currency' => 'USD',
            'currency' => 'RUB',
            'description' => 'Test payment 2', // TODO: payment description
            'returnUrl' => $payment->getSuccessUrl(),
            'cancelUrl' => $payment->getCancelUrl(),
        ]);

        $result = $request->send();

        $payment->setExternalId($result->getTransactionReference());
        $payment->setRedirectUrl($result->getRedirectUrl());
        $payment->setPaymentSystemData($result->getData());

        $this->logger->info("Payment #{$payment->getId()} was initialized");

        return $result;
    }

    public function update(Payment $payment)
    {
        $request = $this->gateway->fetchPurchase([
            'transactionReference' => $payment->getExternalId(),
        ]);

        /** @var RestResponse $result */
        $result = $request->send();
        $data = $result->getData();
        if (isset($data['payer']['payer_info']['payer_id'])) {
            $payerId = $data['payer']['payer_info']['payer_id'];
            $payment->setPayerId($payerId);
        }

        $payment->setPaymentSystemData($data);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $result;
    }

    public function confirm(Payment $payment)
    {
        if (!$payment->getPayerId()) {
            return null;
        }

        $request = $this->gateway->completePurchase([
            'transactionReference' => $payment->getExternalId(),
            'payerId' => $payment->getPayerId(),
        ]);

        /** @var RestResponse $result */
        $result = $request->send();
        $data = $result->getData();
        if (
            ($data['state'] === 'approved') &&
            ($payment->getTransaction()->getStatus()->equals(TransactionStatusEnum::NEW()))
        ) {
            $this->logger->info("Payment #{$payment->getId()} was confirmed");
            $this->transactionService->execute($payment->getTransaction());
        }

        return $result;
    }

    public function check(Payment $payment): Payment
    {
        $this->logger->info("Check payment #{$payment->getId()} status");

        $this->update($payment);
        $this->confirm($payment);

        return $payment;
    }
}
