<?php

require_once __DIR__ . '/../middleware/Auth.php';

$app->group('/Account', function () use ($app) {

    $app->get('/Videos', function () use ($app) {



    })->setName('downloadVideos');

    $app->put('/Videos/{id}', function (Request $request, Response $response) {

        // TODO: Actually load from session instead of hard coded id
        $account_id = 1;
        $started = null;
        $size = null;
        $length = null;

        $stmt = $this->db->prepare('
            INSERT INTO Videos (accountID, videoContent, started, `size`, `length`)
            VALUES (:accountID, :started, :size, :length);');

        $stmt->bindParam(':accountID', $account_id, PDO::PARAM_INT);
        $stmt->bindParam(':started', $started, PDO::PARAM_STR);
        $stmt->bindParam(':size', $size, PDO::PARAM_INT);
        $stmt->bindParam(':length', $length, PDO::PARAM_INT);

        if (isset($json_data['started']) && isset($json_data['size']) && isset($json_data['length'])) {
            $started = $json_data['started'];
            $size = $json_data['size'];
            $length = $json_data['length'];
        } else {
            return $response->withJson("JSON does not conform to the required schema", 400);
        }


        $stmt->execute();

        return $response->withJson($this->db->lastInsertId(), 201);

    })->setName('uploadVideo');

    $app->put('/Videos/{id}/content', function (Request $request, Response $response, $args) {

    })->setName('uploadVideoContent');

    $app->get('/Videos/{id}/content', function (Request $request, Response $response) {

    })->setName('downloadVideoContent');

});
