<?php

namespace Yusmp\PaymentComponent\Service;

use Yusmp\CoreComponent\Domain\Room\Enum\PaymentSystemEnum;

class PaymentSystemService
{
    public function getPaymentSystemInList()
    {
        return [
            PaymentSystemEnum::WEBMONEY(),
        ];
    }

    /**
     * @return PaymentSystemEnum[]
     */
    public function getPaymentSystemOutList()
    {
        return [
            PaymentSystemEnum::PAXUM(),
            PaymentSystemEnum::EPAYSERVICE(),
            PaymentSystemEnum::WEBMONEY(),
        ];
    }
}
