<?php

namespace Yusmp\PaymentComponent\Service;

use DateTime;

class TransactionSummaryFilter
{
    private ?DateTime $dateFrom = null;
    private ?DateTime $dateTo = null;
    private ?string $name = null;

    public function getDateFrom(): ?DateTime
    {
        return $this->dateFrom;
    }

    public function setDateFrom(?DateTime $dateFrom): self
    {
        $this->dateFrom = $dateFrom;
        return $this;
    }

    public function getDateTo(): ?DateTime
    {
        return $this->dateTo;
    }

    public function setDateTo(?DateTime $dateTo): self
    {
        $this->dateTo = $dateTo;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
