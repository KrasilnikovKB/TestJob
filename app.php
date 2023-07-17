<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new Comet\Comet([
    'host' => '0.0.0.0',
    'port' => getenv('PORT', 8000),
]);

$app->get('/json',
    function ($request, $response) {
        $data = [ "message" => "Hello, Comet!" ];
        return $response
            ->with($data);
    });

$app->run();
