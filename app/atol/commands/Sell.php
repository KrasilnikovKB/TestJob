<?php

namespace App\atol\commands;

use App\atol\Core;
use GuzzleHttp\Exception\GuzzleException;

class Sell extends Core
{
    private array $request_data = [
        'timestamp' => '05.01.2022 10:44:51',
        'external_id' => 'api-o-61c326ab77aac7.14103930#1641375891',
        'receipt' => [
            'client' => [
                'phone' => '+79995956180'
            ],
            'company' => [
                'email' => 'jurchenko@softbalance.ru',
                'sno' => 'osn',
                'inn' => '5544332219',
                'payment_address' => 'г Санкт-Петербург, Заневский пр-кт д. 30 к.2 кв. 503'
            ],
            'items' => [
                [
                    'name' => 'Массажное кресло Комфорт 160x200',
                    'price' => 2754,
                    'quantity' => 1,
                    'measure' => 0,
                    'sum' => 2754,
                    'payment_method' => 'full_payment',
                    'payment_object' => 1,
                    'vat' => [
                        'type' => 'vat20',
                        'sum' => 459
                    ]
                ]
            ],
            'payments' => [
                [
                    'type' => 1,
                    'sum' => 2754
                ]
            ],
            'cashier' => 'Алексей Смирнов',
            'total' => 2754
        ]
    ];

    /**
     * Чек «Приход»
     *
     * @throws GuzzleException
     */
    public function sell(): array
    {
        return $this->doRequest('POST', "{$this->getGroupCode()}/sell");
    }
}
