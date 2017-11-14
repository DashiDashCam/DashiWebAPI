<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->group('/oauth', function () use ($app) {

    $app->post('/token', function (Request $request, Response $response) use ($app) {

        $data = $request->getParsedBody();

        $errors = [];
        $json = [];
        $accountID = null;

        // Validate global parameters
        if (isset($data['grant_type'])) {
            if ($data['grant_type'] === 'refresh_token') {
                // Validate related parameters
                if (!isset($data['refresh_token'])) {
                    $errors[] = [
                        'code' => 1011,
                        'field' => 'refresh_token',
                        'message' => 'Must provide refresh_token'
                    ];
                }

                // Advance only if no errors occurred
                if (count($errors) == 0) {
                    $stmt = $this->container->db->prepare("
                        SELECT id, accountID FROM Auth_Tokens JOIN Token_Types ON Auth_Tokens.typeID=Token_Types.id
                        WHERE token=:token AND active=true AND `type`='refresh' AND expires > NOW(); 
                    ");

                    $stmt->execute([':token' => $data['refresh_token']]);

                    // Generate new access token if refresh token is valid
                    if ($row = $stmt->fetch()) {
                        // Extend lifespan of refresh token
                        $stmt = $this->db->prepare("
                            UPDATE Auth_Tokens 
                            SET expires=DATE_ADD(NOW(), INTERVAL 60 DAY), lastUsed=NOW()
                            WHERE token=:token 
                        ");

                        $stmt->execute([':id' => $row['id']]);

                        $json['refresh_token'] = $data['refresh_token'];
                        $accountID = $row['accountID'];
                    }
                    else {
                        $errors[] = [
                            'code' => 1012,
                            'field' => 'refresh_token',
                            'message' => 'The provided refresh token is invalid, expired, or revoked'
                        ];
                    }
                }
            }
            else if($data['grant_type'] === 'password') {
                // Validate related parameters
                if (!isset($data['username'])) {
                    $errors[] = [
                        'code' => 1007,
                        'field' => 'username',
                        'message' => 'Must provide username'
                    ];
                }
                if (!isset($data['password'])) {
                    $errors[] = [
                        'code' => 1009,
                        'field' => 'password',
                        'message' => 'Must provide password'
                    ];
                }

                // Advance only if no errors occurred
                if (count($errors) == 0) {
                    // Check username / retrieve hash
                    $stmt = $this->db->prepare("SELECT id, password FROM Accounts WHERE email=:email;");

                    $sqlData = $stmt->execute([':email' => $data['username']]);

                    if ($sqlData = $sqlData->fetch()) {
                        $accountID = $sqlData['id'];

                        // Generate refresh token if password is valid
                        if (isset($sqlData['password']) && password_verify($data['password'], $sqlData['password'])) {
                            // Load refresh type id
                            $refresh_type_id = $this->db->query("SELECT id FROM Token_Types WHERE `type`='refresh'")
                                ->fetchColumn('id');

                            // Create new access token
                            $json['refresh_token'] = bin2hex(random_bytes(64));

                            $stmt = $this->db->prepare("
                                INSERT INTO Auth_Tokens (token, accountID, expires, issuedTo, typeID)
                                VALUES (:token, :accountID, ADD_DATE(NOW(), INTERVAL 60 DAY), :issuedTo, :typeID);
                            ");

                            $stmt->execute([
                                ':token' => $json['access_token'],
                                ':accountID' => $accountID,
                                ':issuedTo' => $request->getAttribute('ip_address'),
                                ':typeID' => $refresh_type_id
                            ]);
                        }
                        else {
                            $errors[] = [
                                'code' => 1010,
                                'field' => 'password',
                                'message' => 'The provided password is incorrect'
                            ];
                        }
                    }
                    else {
                        $errors[] = [
                            'code' => 1008,
                            'field' => 'username',
                            'message' => 'The provided username does not exist'
                        ];
                    }
                }
            }
            else {
                $errors[] = [
                    'code' => 1006,
                    'field' => 'grant_type',
                    'message' => 'grant_type must be "password" or "refresh_token"'
                ];
            }
        }
        else {
            $errors[] = [
                'code' => 1005,
                'field' => 'grant_type',
                'message' => 'Must provide grant type'
            ];
        }

        // Add token to db and return to user if no errors
        if (count($errors) == 0 ) {

            // Create new access token
            $json['access_token'] = bin2hex(random_bytes(64));

            // Load access type id
            $access_type_id = $this->db->query("SELECT id FROM Token_Types WHERE `type`='access'")->fetchColumn('id');

            $stmt = $this->db->prepare("
                INSERT INTO Auth_Tokens (token, accountID, expires, issuedTo, typeID)
                VALUES (:token, :accountID, ADD_DATE(NOW(), INTERVAL 1 HOUR), :issuedTo, :typeID);
            ");

            $stmt->execute([
                ':token' => $json['access_token'],
                ':accountID' => $accountID,
                ':issuedTo' => $request->getAttribute('ip_address'),
                ':typeID' => $access_type_id
            ]);

            $json['token_type'] = 'Bearer';
            $json['scope'] = 'all';
            $json['expires_in'] = 3600;

            return $response->withJson($json, 201);
        }
        else {
            return $response->withJson([
                'code' => 1024,
                'message' => 'Validation Failed',
                'description' => 'The provided input does not meet the required JSON schema',
                'errors' => $errors
            ], 400);
        }

    });

    $app->delete('/token', function (Request $request, Response $response) use ($app) {

        $stmt = $this->db->prepare("UPDATE Auth_Tokens SET active=false WHERE token=:token; ");

        // Deactivate access token
        if ($stmt->execute([':token' => $request->getAttribute('accountID')])->rowCount() != 1) {
            return $response->withJson([
                'code' => 1004,
                'message' => 'Invalid Access Token',
                'description' => 'The provided access token is invalid'
            ]);
        }

        // Deactivate refresh token (if supplied)
        if ($data = $request->getParsedBody() && isset($data['refresh_token'])) {
            if ($stmt->execute([':token' => $data['refresh_token']])->rowCount() != 1) {
                return $response->withJson([
                    'code' => 1003,
                    'message' => 'Invalid Refresh Token',
                    'description' => 'The provided refresh token is invalid'
                ], 400);
            }
        }

        return $response->withStatus(204);

    })->add('Authentication');

});
