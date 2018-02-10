<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->group('/Account', function () use ($app) {

    $app->get('/Videos', function (Request $request, Response $response) use ($app) {

        // Get meta data for all of user's videos
        $stmt = $this->db->prepare("SELECT id, thumbnail, started, `size`, `length` FROM Videos WHERE accountID=:accountID;");

        $stmt->execute(['accountID' => $request->getAttribute('accountID')]);

        $data = $stmt->fetchAll();

        // Client expects hex encoding
        foreach($data as $key => $datum) {
            $data[$key]['id'] = bin2hex($datum['id']);
            $data[$key]['thumbnail'] = base64_encode($datum['thumbnail']);
        }

        return $response->withJson($data);

    })->setName('downloadVideos');

    $app->put('/Videos/{id}', function (Request $request, Response $response, $args) {

        $data = $request->getParsedBody();

        $stmt = $this->db->prepare("
            INSERT INTO Videos (id, accountID, thumbnail, started, `size`, `length`) 
            VALUES (:id, :accountID, :thumbnail, :started, :size, :length);
        ");

        $errors = [];

        // Ensure ID is a valid SHA256 hash
        if (!ctype_xdigit($args['id']) || strlen($args['id']) != 64) {
            $errors[] = [
                'code' => 1650,
                'field' => 'id',
                'message' => 'ID must be hex representation of valid SHA256 hash'
            ];
        }
        if (!isset($data['thumbnail'])) {
            $errors[] = [
                'code' => 1589,
                'field' => 'thumbnail',
                'message' => 'Must provide thumbnail (base64 encoded)'
            ];
        }
        if (!isset($data['started'])) {
            $errors[] = [
                'code' => 1070,
                'field' => 'started',
                'message' => 'Must provide started timestamp'
            ];
        }
        if (!isset($data['size'])) {
            $errors[] = [
                'code' => 1071,
                'field' => 'size',
                'message' => 'Must provide size (in bytes)'
            ];
        }
        if (!isset($data['length'])) {
            $errors[] = [
                'code' => 1072,
                'field' => 'length',
                'message' => 'Must provide length (in seconds)'
            ];
        }

        if (count($errors) == 0) {
            try {
                $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);
                $stmt->bindValue(':accountID', $request->getAttribute('accountID'));
                $stmt->bindValue(':thumbnail', base64_decode($data['thumbnail']), PDO::PARAM_LOB);
                $stmt->bindValue(':started', $data['started']);
                $stmt->bindValue(':size', $data['size']);
                $stmt->bindValue(':length', $data['length']);

                $stmt->execute();

                return $response
                    ->withStatus(201)
                    ->withHeader('Location', '/Videos/' . $args['id']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    return $response->withJson([
                        'code' => 1025,
                        'message' => 'Input Constraint Violation',
                        'description' => 'The provided input violates data constraints',
                        'errors' => [
                            'code' => 1073,
                            'field' => 'id',
                            'message' => 'Video id is invalid'
                        ]
                    ], 400);
                } else {
                    var_dump($e);
                }
            }
        }
        else {
            return $response->withJson([
                'code' => 1024,
                'message' => 'Validation Failed',
                'description' => 'The provided input does not meet the required JSON schema',
                'errors' => $errors
            ], 400);
        }

    })->setName('uploadVideo');

    $app->put('/Videos/{id}/content', function (Request $request, Response $response, $args) {

        $videoContent = $request->getBody()->getContents();
        $notFound = false;

        $stmt = $this->db->prepare("SELECT videoContent FROM Videos WHERE id=:id AND accountID=:accountID;");

        // Ensure ID is a valid SHA256 hash
        if (!ctype_xdigit($args['id']) || strlen($args['id']) != 64) {
            return $response->withJson([
                'code' => 1024,
                'message' => 'Validation Failed',
                'description' => 'The provided input does not meet the required JSON schema',
                'errors' => [
                    'code' => 1650,
                    'field' => 'id',
                    'message' => 'ID must be hex representation of valid SHA256 hash'
                ]
            ], 400);
        }

        $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);
        $stmt->bindValue(':accountID', $request->getAttribute('accountID'));

        $stmt->execute();

        if ($row = $stmt->fetch()) {
            if ($offset = $request->getQueryParam('offset'))
                $videoContent = $row['videoContent'] . $videoContent;
        }
        else {
            $notFound = true;
        }

        if (!$notFound) {
            $stmt = $this->db->prepare("UPDATE Videos SET videoContent=:video WHERE id=:id AND accountID=:accountID;");

            try {
                $stmt->bindValue(':video', $videoContent, PDO::PARAM_LOB);
                $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);
                $stmt->bindValue(':accountID', $request->getAttribute('accountID'));

                $stmt->execute();

                return $response->withStatus(200);

            } catch (PDOException $e) {
                $notFound = true;
            }
        }

        return $response->withJson([
            'code' => 1054,
            'message' => 'Video Not Found',
            'description' => 'The provided video id is either invalid or you lack sufficient authorization'
        ], 404);

    })->setName('uploadVideoContent');

    $app->get('/Videos/{id}/content', function (Request $request, Response $response, $args) {

        $stmt = $this->db->prepare("SELECT videoContent, accountID FROM Videos WHERE id=:id");

        $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);

        $stmt->execute();

        $row = $stmt->fetch();

        if ($row && $row['accountID'] == $request->getAttribute('accountID')) {
            return $response->withJson($row['videoContent']);
        }
        else {
            return $response->withJson([
                'code' => 1054,
                'message' => 'Video Not Found',
                'description' => 'The provided video id is either invalid or you lack sufficient authorization'
            ], 404);
        }

    })->setName('downloadVideoContent');

})->add('Authentication');
