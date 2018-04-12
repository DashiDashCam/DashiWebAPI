<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->group('/Account', function () use ($app) {

    $app->get('/Videos', function (Request $request, Response $response) use ($app) {

        // Get meta data for all of user's videos
        $stmt = $this->db->prepare("SELECT id, thumbnail, started, `size`, `length`,
            startLat, startLong, endLat, endLong 
            FROM Videos WHERE accountID=:accountID;
        ");

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
            INSERT INTO Videos (id, accountID, thumbnail, started, `size`, `length`, startLat, startLong, endLat, endLong) 
            VALUES (:id, :accountID, :thumbnail, :started, :size, :length, :startLat, :startLong, :endLat, :endLong);
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
        if (!isset($data['startLong'])) {
            $errors[] = [
                'code' => 1072,
                'field' => 'startLong',
                'message' => 'Must provide starting GPS Longitude'
            ];
        }
        if (!isset($data['startLat'])) {
            $errors[] = [
                'code' => 1072,
                'field' => 'startLat',
                'message' => 'Must provide starting GPS Latitude'
            ];
        }
        if (!isset($data['endLong'])) {
            $errors[] = [
                'code' => 1072,
                'field' => 'endLong',
                'message' => 'Must provide ending GPS Longitude'
            ];
        }
        if (!isset($data['endLat'])) {
            $errors[] = [
                'code' => 1072,
                'field' => 'endLat',
                'message' => 'Must provide ending GPS Latitude'
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
                $stmt->bindValue(':startLat', $data['startLat']);
                $stmt->bindValue(':startLong', $data['startLong']);
                $stmt->bindValue(':endLat', $data['endLat']);
                $stmt->bindValue(':endLong', $data['endLong']);

                $stmt->execute();

                return $response->withJson([], 201);
                    //->withStatus(201)
                    //->withHeader('Location', '/Videos/' . $args['id']);
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

        // Ensure offset given
        $offset = $request->getQueryParam('offset');

        if (is_null($offset)) {
            return $response->withJson([
                'code' => 1024,
                'message' => 'Validation Failed',
                'description' => 'The provided input does not meet the required JSON schema',
                'errors' => [
                    'code' => 1650,
                    'field' => 'offset',
                    'message' => 'The video chunk offset (i.e. part) must be provided'
                ]
            ], 400);
        }

        $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);
        $stmt->bindValue(':accountID', $request->getAttribute('accountID'));

        $stmt->execute();

        if ($row = $stmt->fetch()) {
                if ($offset == -1) {
                    try {
                        $stmt = $this->db->prepare("
                        SET group_concat_max_len = CAST(
                            (SELECT SUM(LENGTH(content))
                            FROM VideoChunks
                            WHERE videoID=:id)
                            AS UNSIGNED
                        );
                        UPDATE
                            Videos as V
                            inner join (
                                SELECT videoID, GROUP_CONCAT(content ORDER BY part SEPARATOR '') as videoContent
                                FROM VideoChunks
                                WHERE videoID=:id
                                GROUP BY videoID
                            ) as C on V.id = C.videoID
                        SET V.videoContent = C.videoContent WHERE id=:id AND accountID=:accountID;
                        ");

                        $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);
                        $stmt->bindValue(':accountID', $request->getAttribute('accountID'));

                        $stmt->execute();

                        // TODO: Delete all chunks (they are now duplicates) to free up space

                        return $response->withJSON([], 201);
                    } catch (PDOException $e) {
                        var_dump($e);
                    }
                }
                else {
                    $stmt = $this->db->prepare("INSERT INTO VideoChunks (part, videoID, content)
                        VALUES (:part, :videoID, :content);
                    ");

                    $stmt->execute([
                        ':part' => $offset,
                        ':videoID' => hex2bin($args['id']),
                        ':content' => $videoContent
                    ]);

                    return $response->withJSON([], 200);
                }
        }
        else {
            return $response->withJson([
                'code' => 1054,
                'message' => 'Video Not Found',
                'description' => 'The provided video id is either invalid or you lack sufficient authorization'
            ], 404);
        }

    })->setName('uploadVideoContent');

    $app->get('/Videos/{id}/content', function (Request $request, Response $response, $args) {

        ini_set('memory_limit', '512M');

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

        $stmt = $this->db->prepare("SELECT videoContent, accountID FROM Videos WHERE id=:id");

        $stmt->bindValue(':id', hex2bin($args['id']), PDO::PARAM_LOB);

        $stmt->execute();

        $row = $stmt->fetch();

        if ($row && $row['accountID'] == $request->getAttribute('accountID')) {
            return $response->getBody()->write($row['videoContent']);
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
