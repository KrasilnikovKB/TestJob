<?php

namespace App;

use App\atol\Atol;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class Service
{

    public function __construct(private Repository $repository, private Atol $atol)
    {
    }

    /**
     * @throws Exception|GuzzleException
     */
    public function sell(string $email, string $sno, string $inn, string $payment_address): array
    {
        return $this->atol->sell( $email, $sno, $inn, $payment_address);
    }

    public function checkStatus(string $atol_document_id)
    {

    }

    private function handleReport(array $report): void
    {

    }
}
