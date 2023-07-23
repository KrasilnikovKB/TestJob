<?php

namespace App\atol;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Redis;

class Atol
{
    public const STATUS_WAIT = 'wait';
    public const STATUS_DONE = 'done'; // я не в курсе какой там финальный статус обработки документа, но он должен же быть ? )))

    private const REDIS_TOKEN_KEY = 'atol:token';
    private const REDIS_TOKEN_TTL = 24 * 60 * 60 - 60; // По документации токен выдаётся на сутки, удалим его на минуту раньше.

    private const MAX_AUTH_RETRY = 2;

    private string $callback_url = '';

    private string $token = '';
    private int $auth_retry_counter = 0;

    protected const TEMPLATE_BODY_SELL_REQUEST = [
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
     * @throws GuzzleException
     */
    public function __construct(
        private readonly Redis $redis,
        private readonly string $base_uri,
        private readonly string $group_code,
        private readonly string $login,
        private readonly string $pass
    ) {
        $this->login();
    }

    public function withCallbackUrl(string $url): self
    {
        $this->callback_url = $url;
        return $this;
    }

    /**
     * Чек «Приход»
     *
     * @throws GuzzleException
     */
    public function sell(string $id, ?string $email, string $sno, string $inn, string $payment_address): array
    {
        $payload = self::TEMPLATE_BODY_SELL_REQUEST;
        $payload['external_id'] = $id;
        $payload['receipt']['company'] = [
            'email'           => $email ?? 'none',
            'sno'             => $sno,
            'inn'             => $inn,
            'payment_address' => $payment_address,
        ];

        // исключительно как намёк, на то что так бы было быстрей получать статусы,
        // что не исключает необходимости проверки "просроченных" документов.
        if (!empty($this->callback_url )) {
            $payload['service']['callback_url'] = $this->callback_url;
        }

        return $this->doRequest(
            'POST',
            "{$this->getGroupCode()}/sell",
            $payload
        );
    }

    /**
     * @throws GuzzleException
     */
    public function report(string $id): array
    {
        return $this->doRequest('GET', "{$this->getGroupCode()}/report/{$id}");
    }

    protected function getGroupCode(): string
    {
        return $this->group_code;
    }

    /**
     * @throws Exception|GuzzleException
     */
    protected function doRequest(string $method, string $uri, array $body = [], float $timeout_sec = 2.0): array
    {
        $client = new Client([
            'base_uri' => $this->base_uri,
            'timeout'  => $timeout_sec,
        ]);

        $options = [
            'http_errors' => false,
            'headers' => [
                'Content-type' => 'application/json; charset=utf-8',
                'Token' => $this->token,
            ]
        ];

        if (!empty($body)) {
            $options['json'] = $body;
        }

        $response = $client->request($method, $uri, $options);

        if ($response->getStatusCode() === 401) {
            $this->token = '';
            $this->redis->del(self::REDIS_TOKEN_KEY);
            $this->refreshToken();
            return $this->doRequest($method, $uri, $body, $timeout_sec); // что бы не застрять в рекурсии, как Коуб в лимбе, как раз и используем ограничение self::MAX_AUTH_RETRY.
        }

        // Вот тут по началу кидал исключение если код ответа не 200, но не тут-то было ( таков протокол… Придётся как то с этим жить (

        $content = $response->getBody()->getContents();

        $data = json_decode($content, true);

        if (json_last_error()) {
            throw new Exception(json_last_error_msg(), json_last_error());
        }

        return $data;
    }

    /**
     * @throws Exception|GuzzleException
     */
    private function login(): void
    {
        $this->token = $this->redis->get(self::REDIS_TOKEN_KEY);

        if ($this->token !== false) {
            return;
        }

        $this->refreshToken();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function refreshToken(): void
    {
        $this->auth_retry_counter++;
        if ($this->auth_retry_counter > self::MAX_AUTH_RETRY) {
            throw new Exception('Retry auth counter exceeded');
        }

        $result = $this->getToken();

        $error = $result['error'] ?? false;
        $token = $result['token'] ?? null;
        if ($error || empty($token)) {
            throw new Exception('Can\'t login at ATOL-service');
        }

        $this->auth_retry_counter = 0;
        $this->token = $token;
        $this->redis->setex(self::REDIS_TOKEN_KEY, self::REDIS_TOKEN_TTL, $token); //TODO: да. два инстанса могут поочерёдно обновлять токен, но сейчас с мьютексами заморачиваться нет желания
    }

    /**
     * @throws GuzzleException
     */
    private function getToken(): array
    {
        return $this->doRequest('POST', 'getToken', [
            'login' => $this->login,
            'pass'  => $this->pass,
        ]);
    }
}
