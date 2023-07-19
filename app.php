<?php

use App\atol\Atol;
use App\Controller;
use App\Repository;
use App\Service;
use Workerman\MySQL\Connection as DB;

require __DIR__ . '/vendor/autoload.php';

$db = new DB(
    $_ENV['DB_HOST'] ?? '127.0.0.1',
    $_ENV['DB_PORT'] ?? 33060,
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? 'root',
    $_ENV['DB_NAME'] ?? 'test',
    $_ENV['DB_CHARSET'] ?? 'utf8',
);
$repository = new Repository($db);
$atol = (new Atol( //TODO: Убрать значения по умолчанию. Оставлены только на момент разработки!!!
    $_ENV['ATOL_BASE_URI'] ?? 'https://testonline.atol.ru/possystem/v5/',
    $_ENV['ATOL_GROUP_CODE'] ?? 'v5-online-atol-ru_5179',
    $_ENV['ATOL_LOGIN'] ?? 'v5-online-atol-ru',
    $_ENV['ATOL_PASS'] ?? 'zUr0OxfI',
))->withCallbackUrl($_ENV['ATOL_CALLBACK_URL'] ?? '');
$service = new Service($repository, $atol);
$controller = new Controller($service);

$app = new Comet\Comet([
    'host' => '127.0.0.1',
    'port' => (int) ($_ENV['APP_PORT'] ?? 8000),
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? true),
]);

//TODO: фронтом пока вообще не заморачиваемся.
// По-уму, это должен бы быть отдельный проект,
// который с бэком объединяет лишь одна swagger-схема
$app->serveStatic(__DIR__ . '/front');
$app->redirect('/', '/index.html');

$app->post('/sell', [$controller, 'sell']);

// автообновление токена для АТОЛа
// пока такая схема рабочая только для одного инстанса приложения
// если несколько инстансов работают с одним токеном и хранят его, например, в редисе
// то токен мог быть уже активен какое то время и обновлять его надо не через 24 часа
$app->addJob($_ENV['ATOL_TIMER_AUTH_REFRESH'] ?? /*23 * 60 * 60*/ 300, [$atol, 'refreshToken']);

$app->run();
