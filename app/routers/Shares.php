<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->get('/Share/{id}', function (Request $request, Response $response, $args) use ($app) {

    $stmt = $this->db->prepare("SELECT videoContent FROM Shares JOIN Videos ON Shares.videoID=Videos.id WHERE Shares.id=:id");

    $stmt->bindValue(':id', base64_decode($args['id']), PDO::PARAM_LOB);

    $stmt->execute();

    $row = $stmt->fetch();

    if ($row) {
        $response->getBody()->write($row['videoContent']);

        return $response->withHeader('Content-Type', 'video/quicktime')
            ->withHeader('Content-Transfer-Encoding', 'binary')
            ->withHeader('Content-Disposition', 'inline; filename="' . basename('dashi_video.MOV') . '"');
    }
    else {
        return $response->withJson([
            'code' => 1054,
            'message' => 'Video Not Found',
            'description' => 'The provided video id is either invalid or you lack sufficient authorization'
        ], 404);
    }

})->setName('downloadSharedVideo');

$app->post('/Share', function (Request $request, Response $response) use ($app) {

    $data = $request->getParsedBody();

    $stmt = $this->db->prepare("SELECT id FROM Videos WHERE id=:id;");

    $stmt->bindValue(':id', hex2bin($data['id']), PDO::PARAM_LOB);

    $stmt->execute();

    if ($row = $stmt->fetch()) {
        $stmt = $this->db->prepare("INSERT INTO Shares (id, videoID) VALUES (:id, :videoID);");

        $share_id = random_bytes(255);

        $stmt->bindValue(':id', $share_id, PDO::PARAM_LOB);
        $stmt->bindValue(':videoID', hex2bin($data['id']), PDO::PARAM_LOB);

        $stmt->execute();

        return $response->withJson([
           'shareID' => urlencode(base64_encode($share_id))
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
