<?php

// Import Dependencies
/** @noinspection PhpIncludeInspection */
require 'vendor/autoload.php';

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

class Authentication {

    private $container;

    public function __construct($container) {
        var_dump($container);
        die();
        $this->container = $container;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        // Deny if missing Authorization header
        if ($request->hasHeader('Authorization')) {

            $auth_header = explode(' ', $request->getHeader('Authorization'));

            $type = $auth_header[0];
            $token = $auth_header[1];

            // Only process if token type is valid
            if ($type === 'Bearer') {
                $stmt = $this->container->db->prepare("
                    SELECT id, accountID FROM Auth_Tokens JOIN Token_Types ON Auth_Tokens.typeID=Token_Types.id
                    WHERE token=:token AND active=true AND `type`='access' AND expires > NOW(); 
                ");

                $stmt->execute([':token' => $token]);

                // Authorization is valid, allow request to precede
                if ($data = $stmt->fetch()) {
                    // Update token's lastUsed timestamp
                    $this->container->db->exec("UPDATE Auth_Tokens SET lastUsed=NOW() WHERE id=${data['id']}");

                    // Pass account id to request as attribute and continue to application
                    return $next(
                        $request
                            ->withAttribute('accountID', $data['accountID'])
                            ->withAttribute('accessToken', $token),
                        $response
                    );
                }
                else {
                   return $response
                       ->withJson([
                           'code' => 1002,
                           'message' => 'Unauthorized',
                           'description' => 'The provided access token is invalid, expired, or revoked'
                       ], 401)
                       ->withHeader('WWW-Authenticate', 'Bearer');
                }
            }
            else {
                return $response->withJson([
                    'code' => 1001,
                    'message' => 'Malformed Authorization',
                    'description' => 'Authorization header is malformed. Proper format: "Authorization: Bearer <token>"'
                ], 400);
            }
        }
        else {
            return $response
                ->withJson([
                    'code' => 1000,
                    'message' => 'No Authorization Provided',
                    'description' => 'HTTP Authorization header required (e.g. Authorization: Bearer <token>)'
                ],401)
                ->withHeader('WWW-Authenticate', 'Bearer');
        }
    }

}