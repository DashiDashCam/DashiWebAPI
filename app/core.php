<?php

exit(php_info());

if(file_exists(__DIR__ . '/debug_mode')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
    ini_set('display_startup_errors', 'On');
}

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require 'vendor/autoload.php';

session_cache_limiter(false);
session_start();

// Create and configure slim app
$config = ['settings' => [
    'addContentLengthHeader' => false,
    'displayErrorDetails' => true,
    'debug' => true,
    'log' => [
        'name' => 'api.insecurity.com',
        'level' => Monolog\Logger::DEBUG,
        'path' => __DIR__ . '/logs/app.log',
    ],
    'db' => [
        'host' => 'localhost',
        'dbname' => 'InSecurity',
        'user' => 'api',
        'pass' => 'password'
    ],
]];

$app = new \Slim\App($config);

$container = $app->getContainer();

// Setup Monolog
$container['log'] = function($c) {
    $log = new \Monolog\Logger($c['settings']['log']['name']);
    $fileHandler = new \Monolog\Handler\StreamHandler($c['settings']['log']['path'], $c['settings']['log']['level']);
    $log->pushHandler($fileHandler);
    return $log;
};

// Setup Database Connection
$container['db'] = function ($c) {
    $settings = $c->get('settings')['db'];
    $pdo = new PDO('mysql:host=' . $settings['host'] . ';dbname=' . $settings['dbname'],
        $settings['user'], $settings['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

# Middleware to easily capture request ip address
$app->add(new RKA\Middleware\IpAddress(false));

# Middleware for addressing CORS issue on web frontend
$app->add(function (Request $request, Response $response, $next) {
    $response = $next($request, $response);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:4200')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});


$app->get('/', function(Request $request, Response $response) {
    return $response->withRedirect('http://docs.dashidamcam.apiary.io/');
})->setName('index');


$app->group('/Account', function () use ($app) {

    $app->post('/Create', function(Request $request, Response $response) {

        $data = $request->getParsedBody();



    })->setName('create');


    $app->post('/Login', function(Request $request, Response $response) {

//        $stmt = $this->db->prepare('
//            SELECT Scan.id, scanType, started, completed, status, creator, count(Scan.id) as `deviceCount`
//            FROM Scan LEFT JOIN Devices
//            ON Scan.id = Devices.id
//            GROUP BY Scan.id;');
//
//        $stmt->execute();
//
//        return $response->withJson($stmt->fetchAll());

    })->setName('login');

    $app->delete('Logout', function(Request $request, Response $response) {

    })->setName('logout');

});


$app->group('/Video', function () use ($app) {

    $app->post('/Upload', function (Request $request, Response $response, $args) {

    })->setName('upload');

    $app->patch('/Upload/{id}', function (Request $request, Response $response, $args) {

    })->setName('partialUpload');

    $app->get('/Download/{id}', function (Request $request, Response $response) {

    })->setName('download');

});


// Github webhook proxy (for deployment hooks)
$app->post('/update/{project}', function (Request $request, Response $response, $args) {

    $data = $request->getParsedBody();

    $cmd = escapeshellcmd(__DIR__ . '/get_updates.sh ' . $request->getAttribute('project'));

    // Run the command and log output with timestamps
    system("$cmd 2>&1 | while IFS= read -r line; do echo \"\$(date -u) \$line\"; done >> " . __DIR__ . '/logs/update.log');

})->setName('update');


$app->run();
