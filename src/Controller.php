<?php

namespace App;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Rakit\Validation\Validator;

class Controller
{
    public function __construct(private readonly Service $service)
    {
    }

    public function sell(Request $request, Response $response): Response
    {
        //TODO: поступал бы на вход весь объект чека,
        // то можно бы было провалидировать его по json-схеме через библиотеку
        // подобную этой https://github.com/justinrainbow/json-schema
        // и возвращать уже подготовленным объектом, а не массивом
        // но по ТЗ принимаем только 4 поля, поэтому провалидируем их вручную
        $rules = [
            'email'           => ['email', 'max:64'],
            'sno'             => ['required', 'in:osn,usn_income,usn_income_outcome,envd,esn,patent'],
            'inn'             => ['required', 'regex:/^([0-9]{10}$)|(^[0-9]{12})$/'],
            'payment_address' => ['required', 'max:256'],
        ];

        $validator = new Validator;
        $payload = $request->getParsedBody();
        $validation = $validator->validate($payload, $rules);
        if ($validation->errors()->count()) {
            return $response->withJson($validation->errors()->toArray(), 400);
        }

        $data = $validation->getValidData();

        try {
            $document_id = $this->service->registerDocumentSell(
                $data['sno'],
                $data['inn'],
                $data['payment_address'],
                $data['email'] === '' ? null : $data['email']
            );
            return $response->write("Документ зарегистрирован. Для проверки статуса используйте <a href='/status/{$document_id}' target='_blank'>ссылку</a>");

        } catch (HasDocumentWithSameEmailException $e) {
            return $response->withStatus(400, $e->getMessage());
        } catch (GuzzleException) {
            return $response->withStatus(503, 'Error request to ATOL-service');
        } catch (RegisterDocumentException $e) {
            return $response->withStatus(400, $e->getMessage())->withJson($e->getData());
        }
    }


    public function status(Request $request, Response $response): Response
    {
        $rules = [
            'id' => ['required', 'max:36'],
        ];

        $validator = new Validator;
        $attributes = $request->getAttributes();
        $validation = $validator->validate($attributes, $rules);
        if ($validation->errors()->count()) {
            return $response->withJson($validation->errors()->toArray(), 400);
        }

        $data = $validation->getValidData();

        try {

            $result = $this->service->checkDocumentStatus($data['id']);
            return $response->withJson($result);

        } catch (DocumentNotFoundException $e) {
            return $response->withStatus(404, $e->getMessage());
        }

    }
}
