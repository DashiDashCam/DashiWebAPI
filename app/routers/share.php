<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->get('/Share/{id}', function (Request $request, Response $response) use ($app) {

})->setName('downloadSharedVideo');

$app->post('/Share', function (Request $request, Response $response) use ($app) {

    $data = $request->getParsedBody();

    $stmt = $this->db->prepare("SELECT id FROM Videos WHERE id = :id;");

    $stmt->execute([':id' => $data['id']]);

    if ($row = $stmt->fetch()) {
        $stmt = $this->db->prepare("INSERT INTO Shares (id, videoID) VALUES (:id, :videoID)");

        $share_id = random_bytes(256);

        $stmt->bindValue(':id', $data['id']);
        $stmt->bindValue(':videoID', $share_id, PDO::PARAM_LOB);

        $stmt->exeucte();

        return $response->withJson([
           'shareID' => base64_encode($share_id)
        ]);
    }
    else {
        return $response->withJson([
            'code' => 1054,
            'message' => 'Video Not Found',
            'description' => 'The provided video id is either invalid or you lack sufficient authorization'
        ], 404);
    }

})->setName('shareVideo');
