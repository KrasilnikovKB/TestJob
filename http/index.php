<?php

use App\atol\Atol;
use App\Cache;
use App\Controller;
use App\Repository;
use App\Service;
use Slim\Factory\AppFactory;
use Workerman\MySQL\Connection as DB;

require __DIR__ . '/../vendor/autoload.php';

//TODO: так то бы надо сделать контейнер, но пока обойдёмся ручным DI
$db = new DB(
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME'],
    $_ENV['DB_CHARSET'],
);
$repository = new Repository($db);

$redis = new Redis();
$redis->pconnect($_ENV['REDIS_HOST'], (int) ($_ENV['REDIS_PORT']));
$redis->auth($_ENV['REDIS_PASSWORD']);

$atol = (new Atol(
    $redis,
        $_ENV['ATOL_BASE_URI'],
    $_ENV['ATOL_GROUP_CODE'],
    $_ENV['ATOL_LOGIN'],
    $_ENV['ATOL_PASS'],
))->withCallbackUrl($_ENV['ATOL_CALLBACK_URL']);


$service = new Service($repository, $atol, $redis);
$controller = new Controller($service);

$app = AppFactory::create();

// Фронтом пока вообще не заморачиваемся.
// По-уму, это должен бы быть отдельный проект,
// который с бэком объединяет лишь одна swagger-схема
// а сейчас мы зря инициализируем все объекты и подключения к внешним сервисам,
// при том, что отдаём просто статику ((
$app->redirect('/', '/index.html');

$app->post('/sell', [$controller, 'sell']);
$app->get('/status/{id}', [$controller, 'status']);

$app->run();
