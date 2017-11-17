<?php

use \Slim\Http\Request as Request;
use \Slim\Http\Response as Response;

require_once __DIR__ . '/../middleware/Auth.php';

$app->get('/Account', function (Request $request, Response $response) use ($app) {

    $stmt = $this->db->prepare("SELECT id, email, fullName, created FROM Accounts WHERE id=:id");

    $stmt->execute([':id' => $request->getAttribute('accountID')]);

    return $response->withJson($stmt->fetch());

})->add('Authentication');

$app->post('/Accounts', function (Request $request, Response $response) use ($app) {

    $data = $request->getParsedBody();

    $errors = [];

    // Validate input data
    if (!isset($data['email'])) {
        $errors[] = [
            'code' => 1014,
            'field' => 'email',
            'message' => 'Must provide email'
        ];
    }
    else if (preg_match('/^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/', $data['email']) === 0) {
        $errors[] = [
            'code' => 1015,
            'field' => 'email',
            'message' => 'Not a valid email address'
        ];
    }

    if (!isset($data['fullName'])) {
        $errors[] = [
            'code' => 1016,
            'field' => 'fullName',
            'message' => 'Must provide full name'
        ];
    }
    else if (preg_match('/^\S+.*$/', $data['fullName']) === 0) {
        $errors[] = [
            'code' => 1017,
            'field' => 'fullName',
            'message' => 'Full name must not be blank'
        ];
    }

    if (!isset($data['password'])) {
        $errors[] = [
            'code' => 1018,
            'field' => 'password',
            'message' => 'Must provide password'
        ];
    }
    else if (preg_match('/^(?=.*[A-Z].*)(?=.*[0-9].*)(?=.*[a-z].*)(?=.*\W.*).{8,}$/', $data['password']) === 0) {
        $errors[] = [
            'code' => 1019,
            'field' => 'password',
            'message' => 'Password is too weak'
        ];
    }

    // Input data is valid
    if (count($errors) == 0) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Accounts (email, fullName, password) VALUES (:email, :fullName, :password);
            ");

            $stmt->execute([
                ':email' => $data['email'],
                ':fullName' => $data['fullName'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10])
            ]);

            $accountID = $this->db->lastInsertId();

            return $response
                ->withStatus(201)
                ->withHeader('Location', "/Accounts/$accountID");

        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                return $response->withJson([
                    'code' => 1025,
                    'message' => 'Input Constraint Violation',
                    'description' => 'The provided input does violates data constraints',
                    'errors' => [
                        'code' => 1013,
                        'field' => 'email',
                        'message' => 'Email address is already in use'
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

});

$app->patch('/Accounts/{id}', function (Request $request, Response $response, $args) use ($app) {

    $data = $request->getParsedBody();
    $errors = [];

    if ($request->getAttribute('accountID') == $args['id']) {
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'email':
                    if (preg_match('/^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/', $value) === 0) {
                        $errors = [
                            'code' => 1015,
                            'field' => 'email',
                            'message' => 'Not a valid email address'
                        ];
                    }
                    break;
                case 'password';
                    if (preg_match('/^(?=.*[A-Z].*)(?=.*[0-9].*)(?=.*[a-z].*)(?=.*\W.*).{8,}$/', $value) === 0) {
                        $errors = [
                            'code' => 1019,
                            'field' => 'password',
                            'message' => 'Password is too weak'
                        ];
                    }
                    break;
                case 'fullName':
                    if (preg_match('/^\S+.*$/', $data['fullName']) === 0) {
                        $errors = [
                            'code' => 1017,
                            'field' => 'fullName',
                            'message' => 'Full name must not be blank'
                        ];
                    }
                    break;
                default:
                    $errors = [
                        'code' => 1035,
                        'field' => $value,
                        'message' => 'Unsupported Field'
                    ];
            }
        }
    }
    else {
        return $response->withJson([
            'code' => 1030,
            'message' => 'Account Not Found',
            'description' => 'The provided account id is either invalid or you lack sufficient authorization'
        ], 404);
    }

    // Return the modified resource or error message
    if (count($errors) == 0) {
        $sql = "UPDATE Accounts SET ";

        $cols = ['fullName', 'email', 'password'];
        $sql_cols = [];

        foreach($cols as $col)
            $sql_cols[] = isset($data[$col]) ? $col . '=:' . $col : $col . '=' . $col;

        $stmt = $this->db->prepare($sql . implode(',', $sql_cols) . " WHERE id=:id;");

        $sql_data = [':id' => $request->getAttribute('accountID')];

        foreach($data as $key => $value)
            $sql_data[':' . $key] = $value;

        $stmt->execute($sql_data);

        $stmt = $this->db->prepare("SELECT * FROM Accounts WHERE id=:id;");
        $stmt->execute([':id' => $request->getAttribute('accountID')]);

        return $response->withJson($stmt->fetch(), 200);
    }
    else {
        return $response->withJson([
            'code' => 1040,
            'message' => 'Validation Failed',
            'description' => 'The provided input does not meet the required JSON schema',
            'errors' => $errors
        ], 400);
    }

})->add('Authentication');
