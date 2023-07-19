<?php

namespace App;

use Comet\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Controller
{
    public function __construct(private Service $service)
    {
    }

    public function sell(Request $request, Response $response): Response
    {
        //TODO: поступал бы на вход весь объект чека,
        // то можно б было провалидировать его по json-схеме через библиотеку
        // подобную этой https://github.com/justinrainbow/json-schema
        // и возвращать уже подготовленным объектом, а не массивом
        // но по ТЗ принимаем только 4 поля, поэтому провалидируем их вручную

        $payload = $request->getParsedBody();

        $rules = [
            'email'           => ['email', 'max:64'],
            'sno'             => ['required', 'in:osn,usn_income,usn_income_outcome,envd,esn,patent'],
            'inn'             => ['required', 'regex:/^([0-9]{10}$)|(^[0-9]{12})$/'],
            'payment_address' => ['required', 'max:256'],
        ];

        $validator = new Validator;
        $validation = $validator->validate($payload, $rules);
        if (count($validation->getErrors())) {
            return $response
                ->with($validation->getErrors(), 400);
        }
        $data = $validation->getValidData();
        $email = $data['email'] ? :'none';
        $sno = $data['sno'];
        $inn = $data['inn'];
        $payment_address = $data['payment_address'];

        $result = $this->service->sell($email, $sno, $inn, $payment_address);

        return $response->with($result);
    }

    public function status(Request $request, Response $response): Response
    {
        $result = $this->service->checkStatus($email, $sno, $inn, $payment_address);

        return $response->with($result);
    }
}
