<?php

require_once __DIR__ . '/../middleware/Auth.php';

$app->get('/Account', function () use ($app) {

});

$app->post('/Accounts', function () use ($app) {
    // password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10])
});

$app->patch('/Accounts/{id}', function () use ($app) {

});
