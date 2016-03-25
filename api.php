<?php

if (!function_exists('json_last_error_msg')) {
    function json_last_error_msg() {
        static $ERRORS = array(
            JSON_ERROR_NONE => 'No error',
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );

        $error = json_last_error();
        return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
    }
}

error_reporting(E_ALL);

require 'exception.php';

// Set these to -something- in case the include fails
$API_PATH          = '';
$SQL_PREFIX        = '';
$CONNECTION_STRING = '';

/** @noinspection PhpIncludeInspection */
require '../config.inc.php';

$mysqlCredentials = array();

foreach (explode(';', $CONNECTION_STRING) as $entry) {
    list($key, $value) = explode('=', $entry);
    $mysqlCredentials[ $key ] = $value;
}

$method  = $_SERVER['REQUEST_METHOD'];
$pattern = '/^\/' . preg_quote($API_PATH, '/') . '([a-z0-9]{40}|)\/?(\w+$|\w+(?=[\/]))\/?(.+)?/';

if (strpos($_SERVER['REQUEST_URI'], "?") > 0) {
    list($apiPath, $filter) = explode('?', strtolower($_SERVER['REQUEST_URI']));
} else {
    $apiPath = $_SERVER['REQUEST_URI'];
    $filter  = '';
}

/* Depending on server configuration,
   the path may or more not start with /
*/

if (substr($apiPath, 0, 1) != '/') {
    $apiPath = "/" . $apiPath;
}

preg_match($pattern, $apiPath, $matches);

$path   = '';
$object = '';
$apiKey = '';

switch (count($matches)) {
    /** @noinspection PhpMissingBreakStatementInspection */
    case 4:
        $path = explode("/", $matches[3]);
    /** @noinspection PhpMissingBreakStatementInspection */
    case 3:
        $object = $matches[2];
    case 2:
        $apiKey = $matches[1];
        break;
}

$action = 'api_' . strtoupper($object . '_' . $method);
$action = filter_var($action, FILTER_SANITIZE_EMAIL);

$mysqli = new mysqli();

try {
    $mysqli->connect(
        $mysqlCredentials['Data Source'],
        $mysqlCredentials['User Id'],
        $mysqlCredentials['Password'],
        $mysqlCredentials['Database']
    );

    if ($mysqli->errno) {
        $response = [
            'status' => 500,
            'error' => 'Could Not Connect to MySQLi',
            'trace' => $mysqli
        ];
        throw new MySQLiNotConnectedException($response);
    }
} catch (Exception $e) {
    sendResponse($response);
}

if (isset($apiKey) && strlen($apiKey) == 40) {
    $sqlQuery = <<<EOF
    SELECT
        is_expired,
        scope,
        scopekey,
        ALLOW_RENEW,
        ALLOW_LIST,
        ALLOW_UPLOAD,
        ALLOW_EDIT,
        ALLOW_SEARCH,
        ALLOW_APIKEY_CREATE
    FROM
        {$SQL_PREFIX}apikeys
    WHERE
        apikey=(?)
    LIMIT 1
EOF;

    if (!$permissionQuery = $mysqli->prepare($sqlQuery)) {
        $response = [
            'status' => 500,
            'error' => 'Could Not Prepare MySQLi Statement',
            'trace' => $mysqli
        ];
        throw new MySQLiStatementNotPreparedException($response);
    }

    $permissionQuery->bind_param("s", $apiKey);
    if (!$permissionQuery->execute()) {
        $response = [
            'status' => 500,
            'error' => 'Could Not Perform SELECT',
            'trace' => [
                $mysqli,
                $permissionQuery
            ]
        ];
        throw new MySQLiSelectQueryFailedException($response);
    }

    $result = $permissionQuery->get_result();

    if ($result->num_rows < 1) {
        $response = [
            'trace' => [
                $mysqli,
                $permissionQuery,
                $result
            ]
        ];
        throw new MySQLiNothingSelectedException($response);
    }
    $permissions = $result->fetch_array(MYSQLI_ASSOC);

    if ($permissions['is_expired']) {
        $scope = array();
    } else {
        switch ($permissions['scope']) {
            case 'GLOBAL':
                $scope['product'] = "*";
                $scope['segment'] = "*";
                $scope['event']   = "*";
                break;
            case 'PRODUCT':
                $scope['product'] = $permissions['scopekey'];
                $scope['segment'] = "*";
                $scope['event']   = "*";
                break;
            case 'SEGMENT':
                $scope['segment'] = $permissions['scopekey'];
                $scope['event']   = "*";
                $field            = 'productkey';
                $table            = 'segments';
                $lookup           = 'segment';
                $parameter        = $permissions['scopekey'];
                break;
            case 'EVENT':
                $scope['product'] = "*";
                $scope['segment'] = "*";
                $field            = "session";
                $table            = 'events';
                $lookup           = 'eventkey';
                $parameter        = $permissions['scopekey'];
                break;
            default:
                $response = [
                    'status' => 500,
                    'error' => 'Database Sanity Error',
                    'trace' => $mysqli
                ];
                throw new WhatTheHeckIsThisException($response);
                break;
        }

        if (isset($table)) {
            $sqlQuery = <<<EOF
        SELECT
            {$field}
        FROM
            {$SQL_PREFIX}{$table}
        WHERE
            {$lookup}=(?)
        LIMIT 1
EOF;

            if (!$scopeQuery = $mysqli->prepare($sqlQuery)) {
                throw new MySQLiStatementNotPreparedException($sqlQuery . "\n\n" . print_r($mysqli, true));
            }

            /** @var string $parameter */
            $temp = (int)$parameter;
            $scopeQuery->bind_param("i", $temp);
            if (!$scopeQuery->execute()) {
                $response = [
                    'status' => 500,
                    'error' => 'Could Not Perform SELECT',
                    'trace' => [
                        $mysqli,
                        $scopeQuery
                    ]
                ];
                throw new MySQLiSelectQueryFailedException($response);
            }

            $result = $scopeQuery->get_result();

            if ($result->num_rows < 1) {
                $response = [
                    'trace' => [
                        $mysqli,
                        $scopeQuery,
                        $result
                    ]
                ];
                throw new MySQLiNothingSelectedException($response);
            }
            $scopeResult = $result->fetch_array(MYSQLI_ASSOC);

            if (isset($scopeResult['productkey'])) {
                $scope['product'] = $scopeResult['productkey'];
            }
            if (isset($scopeResult['session'])) {
                $scope['event'] = $scopeResult['session'];
            }
        }
    }
}

try {
    if (function_exists($action)) {
        // Explicitly cast $action as a string to reassure the debugger.
        $action = (string)$action;
        $action();
    } else {
        $response = [
            'status' => 400,
            'error' => "HTTP `{$method}` not supported for object `{$object}`.",
            'trace' => $mysqli
        ];
        throw new BadRequestException($response);
    }
} catch (Exception $e) {
    sendResponse($e);
}

exit();

function api_EVENTS_GET() {
    global $mysqli;
    global $path;

    switch (count($path)) {
        /** @noinspection PhpMissingBreakStatementInspection */
        case 2:
            $object2 = $path[1];
        case 1:
            $session = $path[0];
            break;
        case 0:
            break;
        default:
            $response = [
                'status' => 400,
                'error' => 'What are you doing?',
            ];
            throw new BadRequestException($response);
            break;
    }

    $funcCall = __FUNCTION__;
    if (isset($session) && strlen($session) > 0) {
        $funcCall  = $funcCall . '_ID';
        $parameter = $session;
        if (isset($object2) && strlen($object2) > 0) {
            $funcCall = $funcCall . '_' . strtoupper($object2);
        }
    }

    if ($funcCall != __FUNCTION__) {
        if (function_exists($funcCall)) {
            // Explicitly cast $action as a string to reassure the debugger.
            $funcCall = (string)$funcCall;
            if (isset($parameter)) {
                $funcCall($parameter);
            } else {
                $funcCall();
            }
        } else {
            $response = [
                'status' => 400,
                'error' => 'Not supported',
            ];
            throw new BadRequestException($response);
        }
    }

    if (!getPermission("VIEW", getScopeByEventSession($id))) {
        $response = [
            'status' => 401,
            'error' => "Underprivileged API Key.",
        ];
        throw new BadRequestException($response);
    }

    print "Event list.";
}

function api_EVENTS_PUT_ID($id) {

}

/**
 * @param $id
 * @throws MySQLiNothingSelectedException
 * @throws MySQLiSelectQueryFailedException
 * @throws MySQLiStatementNotPreparedException
 * @throws MySQLiUpdateQueryFailedException
 */
function api_EVENTS_POST_ID_ATTACHMENTS($id) {
    if (!isset($id) || strlen($id) != 8) {
        $response['error'] = "No Session ID Provided";
        sendResponse($response, 400);
    }

    if (!getPermission("UPLOAD", getScopeByEventSession($id))) {
        $response['error'] = "Underprivileged API Key";
        sendResponse($response, 401);
    }

    if (empty($_FILES)) {
        $response['error'] = "No File to Upload";
        sendResponse($response, 400);
    }

    $status = array('files' => [],'errorCount' => 0);

    $i = 0;
    foreach ($_FILES as $file) {
        $status['files'][$i]['trace'] = $file;

        if ($file['error'] > 0) {
            $status['files'][$i]['error'] = $file['error'];
            $status['errorCount']++;
        } else {
            $destination = getcwd() . '/up/' . $id . '_' . $file['name'];
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $status['files'][$i]['error'] = "Failed to move `{$file['tmp_name']}` to `{$destination}`";
                $status['errorCount']++;
            } else {
                //chmod($destination, 0644);
                $status['files'][$i]['path'] = $destination;
            }
        }
        $i++;
    }

    if ($status['errorCount'] == 0) {
        // Everything Worked
        $httpCode = 200;
    } elseif($status['errorCount'] == count($status['files'])) {
        // Everything failed.
        $httpCode = 500;
    } else {
        // Mixed Results
        $httpCode = 207;
    }

    if (getPermission("RENEW", getScopeByEventSession($id))) {
        global $mysqli;
        global $SQL_PREFIX;

        $sqlQuery = <<<EOF
    UPDATE
        {$SQL_PREFIX}apikeys
    SET
        expiration = DATE_ADD(NOW(), INTERVAL 1 HOUR),
        last_renewal = NOW()
    WHERE
        scope='EVENT'
    AND scopekey=(SELECT eventkey FROM {$SQL_PREFIX}events WHERE session=?)
    AND is_expired=0
EOF;

        if (!$update = $mysqli->prepare($sqlQuery)) {
            throw new MySQLiStatementNotPreparedException(print_r($mysqli, true) . "\n" . print_r($update, true));
        }

        $update->bind_param("s", $id);
        if (!$update->execute()) {
            throw new MySQLiUpdateQueryFailedException(print_r($mysqli, true) . "\n" . print_r($update, true));
        }
    }
    sendResponse($status, $httpCode);

}

function api_EVENTS_POST() {
    global $mysqli;
    global $path;

    switch (count($path)) {
        /** @noinspection PhpMissingBreakStatementInspection */
        case 2:
            $object2 = $path[1];
        case 1:
            $session = $path[0];
            break;
        case 0:
            break;
        default:
            throw new BadRequestException();
            break;
    }

    $funcCall = __FUNCTION__;
    if (isset($session) && strlen($session) > 0) {
        $funcCall  = $funcCall . '_ID';
        $parameter = $session;
        if (isset($object2) && strlen($object2) > 0) {
            $funcCall = $funcCall . '_' . strtoupper($object2);
        }
    }

    if ($funcCall != __FUNCTION__) {
        if (function_exists($funcCall)) {
            // Explicitly cast $action as a string to reassure the debugger.
            $funcCall = (string)$funcCall;
            if (isset($parameter)) {
                $funcCall($parameter);
            } else {
                $funcCall();
            }
        } else {
            throw new BadRequestException();
        }
    } else {
        try {
            if (!$jsonRequest = json_decode($_POST['request'], true)) {
                $response = [
                    'status' => 400,
                    'error' => "Malformed JSON",
                    'trace' => [
                        $jsonRequest
                    ]
                ];
                throw new InvalidJsonException($response);
            }

            $requiredFields = [
                'segment' => [
                    'filter' => FILTER_VALIDATE_INT,
                ],
                'phoneNumber' => [
                    'filter' => FILTER_VALIDATE_REGEXP,
                    'options' => [
                        'options' => [
                            'regexp' => "/^\+? ?[0-9 ]+$/"
                        ]
                    ]
                ],
                'emailAddress' => [
                    'filter' => FILTER_VALIDATE_EMAIL,
                ],
                'latitude' => [
                    'filter' => FILTER_VALIDATE_FLOAT,
                ],
                'longitude' => [
                    'filter' => FILTER_VALIDATE_FLOAT,
                ]
            ];

            foreach ($requiredFields as $key => $parameters) {
                if (!isset($jsonRequest[$key])) {
                    throw new BadRequestException("Required parameter `{$key}` is missing.");
                }

                $value = $jsonRequest[$key];
                $filter = $parameters['filter'];
                $options = isset($parameters['options']) ? $parameters['options'] : [];

                if (!filter_var($value, $filter, $options)) {
                    throw new BadRequestException("Parameter `{$key}`:`{$value}` is not valid.");
                }
            }

            $sqlQuery = <<<EOF
    INSERT INTO
        dev__events
        (
            session,
            segmentkey,
            phone_number,
            email_address,
            latitude,
            longitude
        ) VALUES (?, ?, ?, ?, ?, ?)
EOF;

            /** @var mysqli_stmt $eventQuery */
            if (!$eventQuery = $mysqli->prepare($sqlQuery)) {
                throw new MySQLiStatementNotPreparedException(print_r($mysqli,true));
            }

            do {
                $sessionId = generateSessionId();
                $eventQuery->bind_param("sissdd",
                    $sessionId,
                    $jsonRequest['segment'],
                    $jsonRequest['phoneNumber'],
                    $jsonRequest['emailAddress'],
                    $jsonRequest['latitude'],
                    $jsonRequest['longitude']
                );
                $eventQuery->execute();
            } while ($eventQuery->errno == 1062);

            switch ($eventQuery->affected_rows) {
                case -1:
                    throw new MySQLiInsertQueryFailedException(print_r($eventQuery, true));
                    break;
                case 0:
                    throw new MySQLiRowNotInsertedException(print_r($eventQuery, true));
                    break;
            }

            $sqlQuery = <<<EOF
INSERT INTO
    dev__apikeys
(
    expiration,
    scope,
    ALLOW_RENEW,
    ALLOW_UPLOAD,
    apikey,
    scopekey
)
VALUES
(
    DATE_ADD(NOW(), INTERVAL 1 HOUR),
    'EVENT',
    1,
    1,
    ?, ?
)
EOF;

            /** @var mysqli_stmt $apiKeyQuery */
            if (!$apiKeyQuery = $mysqli->prepare($sqlQuery)) {
                throw new MySQLiStatementNotPreparedException($mysqli);
            }

            do {
                $apiKey   = generateApiKey($sessionId);
                $scopeKey = (int)$eventQuery->insert_id;

                $apiKeyQuery->bind_param("si",
                    $apiKey,
                    $scopeKey
                );
                $apiKeyQuery->execute();
            } while ($apiKeyQuery->errno == 1062);

            switch ($apiKeyQuery->affected_rows) {
                case -1:
                    throw new MySQLiInsertQueryFailedException(print_r($apiKeyQuery, true));
                    break;
                case 0:
                    throw new MySQLiRowNotInsertedException(print_r($apiKeyQuery, true));
                    break;
            }
            $eventQuery->close();
            $apiKeyQuery->close();

            $response            = array();
            $response['session'] = $sessionId;
            $response['dial']    = "+1 407 934 7639";
            $response['apiKey']  = $apiKey;

            sendResponse($response, 201);
        } catch (Exception $e) {
            sendResponse($e);
        }
    }

}

function generateSessionId() {
    return strtoupper(substr(str_shuffle(str_repeat("aeufhlmr145670", 8)), 0, 8));
}

function generateApiKey($sessionId) {
    return sha1($sessionId . microtime(true) . mt_rand(10000, 90000));
}

function sendResponse($response, $exitAfter = true) {
    if ($response instanceof LeagleEyeException) {
        $base = $response->getResponse();
    } else {
        $base = $response;
    }

    if (!isset($base['status'])) {
        $base['status'] = "500";
        $base['statusMessage'] = "No Status Provided";
    }
    $base['statusMessage'] = isset($base['statusMessage']) ? $base['statusMessage'] : "";

    if (!isset($base['statusMessage']) OR $base['statusMessage'] == '') {
        switch ($base['status']) {
            case 200:
                $base['statusMessage'] = "OK";
                break;
            case 201:
                $base['statusMessage'] = "Created";
                break;
            case 400:
                $base['statusMessage'] = "Bad Request";
                break;
        }
    }

    Header("HTTP/1.1 {$base['status']} {$base['statusMessage']}");
    Header("Content-type: application/json");
    print json_encode($base);
    if ($exitAfter) {
        exit();
    }
}

function getPermission($action, $compare = array()) {
    global $scope;
    global $permissions;

    if (count($scope) == 0) {
        return false;
    }

    foreach (array("event", "product", "segment") as $attribute) {
        if (!fnmatch($scope[ $attribute ], $compare[ $attribute ])) {
            return false;
        }
    }

    $key = strtoupper("allow_" . $action);
    if (!isset($permissions[ $key ])) {
        return false;
    } else {
        return ($permissions[ $key ] == 1);
    }
}

function getScopeByEventSession($event) {
    global $mysqli;
    global $SQL_PREFIX;

    $sqlQuery = <<<EOF
    SELECT
        session AS event,
        {$SQL_PREFIX}events.segmentkey AS segment,
        {$SQL_PREFIX}segments.productkey AS product
    FROM
        {$SQL_PREFIX}events
    LEFT JOIN
        {$SQL_PREFIX}segments
    ON
        {$SQL_PREFIX}events.segmentkey={$SQL_PREFIX}segments.segmentkey
    WHERE
        session=(?)
    LIMIT 1
EOF;

    if (!$scopeQuery = $mysqli->prepare($sqlQuery)) {
        throw new MySQLiStatementNotPreparedException(print_r($mysqli, true));
    }

    $scopeQuery->bind_param("s", $event);
    if (!$scopeQuery->execute()) {
        throw new MySQLiSelectQueryFailedException();
    }

    $result = $scopeQuery->get_result();

    if ($result->num_rows < 1) {
        throw new MySQLiNothingSelectedException();
    }

    return $result->fetch_array(MYSQLI_ASSOC);
}