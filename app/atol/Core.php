<?php

namespace App\atol;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

abstract class Core
{
    private const MAX_AUTH_RETRY = 2;

    private string $callback_url = '';

    private static string $token = '';
    private int $auth_retry_counter = 0;

    public function __construct(
        private readonly string $base_uri = 'https://testonline.atol.ru/possystem/v5/', //TODO: все значения по умолчанию должны получаться из ЭНВов
        private readonly string $group_code = 'v5-online-atol-ru_5179',
        private readonly string $login = 'v5-online-atol-ru',
        private readonly string $pass = 'zUr0OxfI'
    ) {
    }

    public function withCallbackUrl(string $url): self
    {
        $this->callback_url = $url;
        return $this;
    }

    protected function getGroupCode(): string
    {
        return $this->group_code;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    protected function doRequest(string $method, string $uri, array $query = [], array $body = [], float $timeout_sec = 2.0): array
    {
        if (self::isNotAuthorized()) {
            $this->login();
        }

        $client = new Client([
            'base_uri' => $this->base_uri,
            'timeout'  => $timeout_sec,
        ]);

        $options = [
            'headers' => [
                'Content-type' => 'application/json; charset=utf-8',
                'Token' => self::$token,
            ]
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }
        if (!empty($body)) {
            $options['json'] = $body;
        }

        $response = $client->request($method, $uri, $options);

        if ($response->getStatusCode() === 401) {
            self::$token = ''; //TODO: при использовании редиса для кеширования токена - обнулить его и там
            $this->login();
            $this->doRequest($method, $uri, $query, $body, $timeout_sec);
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase(), $response->getStatusCode());
        }

        $content = $response->getBody()->getContents();

        $data = json_decode($content, true);

        if (json_last_error()) {
            throw new Exception(json_last_error_msg(), json_last_error());
        }

        return $data;
    }

    private static function isNotAuthorized(): bool
    {
        return empty(self::$token);
    }

    /**
     * @throws Exception|GuzzleException
     */
    private function login(): void
    {
        $this->auth_retry_counter++;
        if ($this->auth_retry_counter > self::MAX_AUTH_RETRY) {
            throw new Exception('Retry auth counter exceeded');
        }

        //TODO: для начала поискать закешированный токен например в редисе (сэкономим на сетевом запросе)
        // если в кэше нет, то авторизоваться в самом сервисе атол-онлайн
        $result = $this->getToken();

        $error = $result['error'] ?? true;
        $token = $result['token'] ?? null;
        if ($error || empty($token)) {
            throw new Exception('Can\'t login at ATOL-service');
        }
        self::$token = $token;
    }

    /**
     * @throws GuzzleException
     */
    private function getToken(): array
    {
        return $this->doRequest('POST', 'getToken', body: [
            'login' => $this->login,
            'pass'  => $this->pass,
        ]);
    }
}
