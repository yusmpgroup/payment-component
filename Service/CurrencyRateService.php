<?php

namespace Yusmp\PaymentComponent\Service;

use Yusmp\CoreComponent\Domain\Payment\Entity\CurrencyRate;
use Doctrine\ORM\EntityManagerInterface;

class CurrencyRateService
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function updateInvertedRate(CurrencyRate $currencyRate)
    {
        $repository = $this->entityManager->getRepository(CurrencyRate::class);
        $invertedCurrencyRate = $repository->findOneBy([
            'from' => $currencyRate->getTo(),
            'to' => $currencyRate->getFrom(),
        ]);

        if (!$invertedCurrencyRate) {
            $invertedCurrencyRate = (new CurrencyRate())
                ->setFrom($currencyRate->getTo())
                ->setTo($currencyRate->getFrom())
            ;
        }

        $invertedCurrencyRate->setExchangeRate(1/$currencyRate->getExchangeRate());

        $this->entityManager->persist($invertedCurrencyRate);
        $this->entityManager->flush();
    }
}
