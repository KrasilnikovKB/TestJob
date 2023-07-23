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
    $_ENV['DB_HOST'] ?? '127.0.0.1',
    $_ENV['DB_PORT'] ?? 33060,
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? 'root',
    $_ENV['DB_NAME'] ?? 'test',
    $_ENV['DB_CHARSET'] ?? 'utf8',
);
$repository = new Repository($db);

$redis = new Redis();
// TODO: Убрать значения по умолчанию. Оставлены только на момент разработки!!!
$redis->pconnect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int) ($_ENV['REDIS_PORT'] ?? 6379));
$redis->auth($_ENV['REDIS_PASSWORD'] ?? 'redis_password');

$atol = (new Atol( //TODO: Убрать значения по умолчанию. Оставлены только на момент разработки!!!
    $redis,
        $_ENV['ATOL_BASE_URI'] ?? 'https://testonline.atol.ru/possystem/v5/',
    $_ENV['ATOL_GROUP_CODE'] ?? 'v5-online-atol-ru_5179',
    $_ENV['ATOL_LOGIN'] ?? 'v5-online-atol-ru',
    $_ENV['ATOL_PASS'] ?? 'zUr0OxfI',
))->withCallbackUrl($_ENV['ATOL_CALLBACK_URL'] ?? '');


$service = new Service($repository, $atol, $redis);
$controller = new Controller($service);

$app = AppFactory::create();

//TODO: фронтом пока вообще не заморачиваемся.
// По-уму, это должен бы быть отдельный проект,
// который с бэком объединяет лишь одна swagger-схема
// а сейчас мы зазря инициализируем все объекты и подключения к внешним сервисам,
// при том, что отдаём просто статику ((
$app->redirect('/', '/index.html');

$app->post('/sell', [$controller, 'sell']);
$app->get('/status/{id}', [$controller, 'status']);

$app->run();
