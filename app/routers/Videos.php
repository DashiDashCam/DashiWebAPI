<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->group('/Account', function () use ($app) {

    $app->get('/Videos', function (Request $request, Response $response) use ($app) {

        // Get meta data for all of user's videos
        $stmt = $this->db->prepare("SELECT id, started, `size`, `length` FROM Videos WHERE accountID=:accountID;");

        $stmt->execute(['accountID' => $request->getAttribute('accountID')]);

        return $response->withJson($stmt->fetch());

    })->setName('downloadVideos');

    $app->put('/Videos/{id}', function (Request $request, Response $response, $args) {

        $data = $request->getParsedBody();

        $stmt = $this->db->prepare("
            INSERT INTO Videos (id, accountID, started, `size`, `length`) 
            WHERE (:id, :accountID, :started, :size, :length);
        ");

        $errors = [];

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
                $stmt->execute([
                    ':id' => $args['id'],
                    ':accountID' => $request->getAttribute('accountID'),
                    ':started' => $data['started'],
                    ':size' => $data['size'],
                    ':length' => $data['length']
                ]);

                return $response
                    ->withStatus(201)
                    ->withHeader('Location', '/Videos/' . $args['id']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    return $response->withJson([
                        'code' => 1025,
                        'message' => 'Input Constraint Violation',
                        'description' => 'The provided input does violates data constraints',
                        'errors' => [
                            'code' => 1073,
                            'field' => 'id',
                            'message' => 'Video id is invalid'
                        ]
                    ], 400);
                } else {
                    throw $e;
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

        if ($offset = $request->getQueryParam('offset')) {
            $stmt = $this->db->prepare("SELECT videoContent FROM Videos WHERE id=:id AND accountID=:accountID;");

            $stmt->execute([
                ':id' => $args['id'],
                ':accountID' => $request->getAttribute('accountID')
            ]);

            if ($row = $stmt->fetch()) {
                $videoContent = $row['videoContent'] . $videoContent;
            }
            else {
                $notFound = true;
            }
        }

        if (!$notFound) {
            $stmt = $this->db->prepare("UPDATE Videos SET videoContent=:video WHERE id=:id AND accountID=:accountID;");

            try {
                $stmt->execute([
                    ':video' => $videoContent,
                    ':id' => $args['id'],
                    ':accountID' => $request->getAttribute('accountID')
                ]);

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

        $stmt->execute([':id' => $args['id']]);

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

});
