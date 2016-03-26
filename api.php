<?php

error_reporting(E_ALL);

require 'exception.php';
require 'mysqli.php';

// Set these to -something- in case the include fails
$API_PATH = '';
$SQL_PREFIX = '';
$CONNECTION_STRING = '';

/** @noinspection PhpIncludeInspection */
require '../config.inc.php';

$mysqlCredentials = [];

foreach (explode(';', $CONNECTION_STRING) as $entry) {
    list($key, $value) = explode('=', $entry);
    $mysqlCredentials[$key] = $value;
}

$method = $_SERVER['REQUEST_METHOD'];
$pattern = '/^\/' . preg_quote($API_PATH, '/') . '([a-z0-9]{40}|)\/?(\w+$|\w+(?=[\/]))\/?(.+)?/';

if (strpos($_SERVER['REQUEST_URI'], "?") > 0) {
    list($apiPath, $filter) = explode('?', strtolower($_SERVER['REQUEST_URI']));
} else {
    $apiPath = $_SERVER['REQUEST_URI'];
    $filter = '';
}

/* Depending on server configuration,
   the path may or more not start with /
*/

if (substr($apiPath, 0, 1) != '/') {
    $apiPath = "/" . $apiPath;
}

preg_match($pattern, $apiPath, $matches);

$path = '';
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

try {
    $mysqli = new MySQLiDriver($mysqlCredentials);
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

    $permissions = null;
    try {
        $permissions = doMySQLiSelect($sqlQuery, [['s' => $apiKey]])[0];
    } catch (MySQLiNothingSelectedException $e) {
        throw new ApiKeyNotPrivilegedException([$apiKey], $e);
    }

//    if (!$permissionQuery = $mysqli->prepare($sqlQuery)) {
//        throw new MySQLiStatementNotPreparedException([$sqlQuery, $permissionQuery]);
//    }
//
//    $permissionQuery->bind_param("s", $apiKey);
//    if (!$permissionQuery->execute()) {
//        throw new MySQLiSelectQueryFailedException([$sqlQuery, $permissionQuery]);
//    }
//
//    $result = $permissionQuery->get_result();
//
//    if ($result->num_rows < 1) {
//        throw new MySQLiNothingSelectedException([$sqlQuery, $permissionQuery, $result]);
//    }
//    $permissions = $result->fetch_array(MYSQLI_ASSOC);

    if ($permissions['is_expired']) {
        $scope = array();
    } else {
        switch ($permissions['scope']) {
            case 'GLOBAL':
                $scope['product'] = "*";
                $scope['segment'] = "*";
                $scope['event'] = "*";
                break;
            case 'PRODUCT':
                $scope['product'] = $permissions['scopekey'];
                $scope['segment'] = "*";
                $scope['event'] = "*";
                break;
            case 'SEGMENT':
                $scope['segment'] = $permissions['scopekey'];
                $scope['event'] = "*";
                $field = 'productkey';
                $table = 'segments';
                $lookup = 'segment';
                $parameter = $permissions['scopekey'];
                break;
            case 'EVENT':
                $scope['product'] = "*";
                $scope['segment'] = "*";
                $field = "session";
                $table = 'events';
                $lookup = 'eventkey';
                $parameter = $permissions['scopekey'];
                break;
            default:
                $response = [
                    'status' => [
                        'code' => 500
                    ],
                    'error' => [
                        'message' => 'Database Sanity Error'
                    ],
                    'trace' => [$mysqli]
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
                throw new MySQLiStatementNotPreparedException([$sqlQuery, $scopeQuery]);
            }

            /** @var string $parameter */
            $temp = (int)$parameter;
            $scopeQuery->bind_param("i", $temp);
            if (!$scopeQuery->execute()) {
                throw new MySQLiSelectQueryFailedException([$sqlQuery, $scopeQuery]);
            }

            $result = $scopeQuery->get_result();

            if ($result->num_rows < 1) {
                throw new MySQLiNothingSelectedException([$sqlQuery, $scopeQuery, $result]);
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
            'status' => [
                'code' => 400
            ],
            'error' => [
                'message' => "HTTP `{$method}` not supported for object `{$object}`."
            ],
            'trace' => [
                $mysqli
            ]
        ];
        throw new BadRequestException($response);
    }
} catch (Exception $e) {
    sendResponse($e);
}

exit();

function api_EVENTS_GET()
{
    //global $mysqli;
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
                'status' => [
                    'code' => 400
                ],
                'error' => [
                    'message' => 'What are you doing?'
                ],
                'trace' => [
                    $path
                ]
            ];
            throw new BadRequestException($response);
            break;
    }

    $funcCall = __FUNCTION__;
    if (isset($session) && strlen($session) > 0) {
        $funcCall = $funcCall . '_ID';
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
                'status' => ['code' => 400],
                'error' => ['message' => 'Not supported']
            ];
            throw new BadRequestException($response);
        }
    }

    if (!getPermission("VIEW", getScopeByEventSession(''))) {
        $response = [
            'status' => ['code' => 401],
            'error' => ['message' => 'Underprivileged API Key.']
        ];
        throw new BadRequestException($response);
    }

    print "Event list.";
}

function api_EVENTS_PUT_ID($id)
{

}

/**
 * @param $id
 * @throws ApiKeyNotPrivilegedException
 * @throws InvalidIdentifierException
 * @throws MySQLiNothingSelectedException
 * @throws MySQLiSelectQueryFailedException
 * @throws MySQLiStatementNotPreparedException
 * @throws NoFilesProvidedException
 */
function api_EVENTS_POST_ID_ATTACHMENTS($id)
{
    if (!isset($id) || strlen($id) != 8) {
        throw new InvalidIdentifierException();
    }

    if (!getPermission("UPLOAD", getScopeByEventSession($id))) {
        throw new ApiKeyNotPrivilegedException();
    }

    if (empty($_FILES)) {
        throw new NoFilesProvidedException();
    }

    $status = [
        'data' => [],
        'error' => [
            'count' => 0
        ]
    ];

    $i = 0;
    foreach ($_FILES as $file) {
        $status['data']['files'][$i]['trace'] = $file;

        if ($file['error'] > 0) {
            $status['data']['files'][$i]['error'] = $file['error'];
            $status['error']['count']++;
        } else {
            $destination = getcwd() . DIRECTORY_SEPARATOR . 'up' . DIRECTORY_SEPARATOR . $id . '_' . $file['name'];
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $status['data']['files'][$i]['error'] = "Failed to move `{$file['tmp_name']}` to `{$destination}`";
                $status['error']['count']++;
            } else {
                //chmod($destination, 0644);
                $status['data']['files'][$i]['path'] = $destination;
            }
        }
        $i++;
    }

    if ($status['error']['count'] == 0) {
        // Everything Worked
        $status['status']['code'] = 200;
    } elseif ($status['error']['count'] == count($status['data']['files'])) {
        // Everything failed.
        $status['status']['code'] = 500;
    } else {
        // Mixed Results
        $status['status']['code'] = 207;
    }

    try {
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
                throw new MySQLiStatementNotPreparedException([$sqlQuery, $mysqli, $update]);
            }

            $update->bind_param("s", $id);
            if (!$update->execute()) {
                throw new MySQLiUpdateQueryFailedException([$sqlQuery, $mysqli, $update]);
            }
        }
    } catch (LeagleEyeException $e) {
        $status['error']['apiKeyRenewalError'] = $e->getResponse();
    }
    sendResponse($status);
}

function api_EVENTS_POST()
{
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
        $funcCall = $funcCall . '_ID';
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
                throw new InvalidJsonException([$jsonRequest]);
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
                    throw new BadRequestException(["Required parameter `{$key}` is missing."]);
                }

                $value = $jsonRequest[$key];
                $filter = $parameters['filter'];
                $options = isset($parameters['options']) ? $parameters['options'] : [];

                if (!filter_var($value, $filter, $options)) {
                    throw new BadRequestException(["Parameter `{$key}`:`{$value}` is not valid."]);
                }
            }


            $sqlQuery = <<<EOF
            
                INSERT INTO
                    tbl__events
                    (
                        session,
                        segmentkey,
                        phone_number,
                        email_address,
                        latitude,
                        longitude
                    ) VALUES (?, ?, ?, ?, ?, ?)
        
EOF;

            $sessionId = null;
            $eventQuery = null;
            $eventAdded = false;
            $attempts = 1;
            $i = 0;
            $lastError = null;

            do {
                try {
                    $sessionId = generateSessionId();

                    $eventQuery = $mysqli->insert($sqlQuery, [
                            ['s' => $sessionId],
                            ['i' => $jsonRequest['segment']],
                            ['s' => $jsonRequest['phoneNumber']],
                            ['s' => $jsonRequest['emailAddress']],
                            ['d' => $jsonRequest['latitude']],
                            ['d' => $jsonRequest['longitude']]
                        ]
                    );

                    $eventAdded = true;
                } catch (MySQLiInsertQueryFailedException $e) {
                    $lastError = print_r($e, true);
                }

                $i++;

            } while (!$eventAdded AND $i <= $attempts);

            if (!$eventAdded) {
                throw new EventNotAddedException([$lastError]);
            }


            $sqlQuery = <<<EOF
            
                INSERT INTO
                    tbl__apikeys
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

            $apiKey = null;
            $scopeKey = (int)$eventQuery->insert_id;
            $apiKeyAdded = false;
            $attempts = 1;
            $i = 0;

            do {
                try {
                    $apiKey = generateApiKey($sessionId);

                    $apiKeyQuery = $mysqli->insert($sqlQuery, [
                            ['s' => $apiKey],
                            ['i' => $scopeKey]
                        ]
                    );

                    $apiKeyAdded = true;
                } catch (MySQLiInsertQueryFailedException $e) {
                    $lastError = print_r($e, true);
                }

                $i++;

            } while (!$apiKeyAdded AND $i <= $attempts);

            if (!$apiKeyAdded) {
                throw new ApiKeyNotAddedException([$lastError]);
            }

            $eventQuery->close();
            $apiKeyQuery->close();

            $response = [
                'data' => [
                    'session' => $sessionId,
                    'dial' => "+1 407 934 7639",
                    'apiKey' => $apiKey
                ],
                'status' => [
                    'code' => 201
                ]
            ];
            sendResponse($response);
        } catch (Exception $e) {
            sendResponse($e);
        }
    }

}

function generateSessionId()
{
    return strtoupper(substr(str_shuffle(str_repeat("aeufhlmr145670", 8)), 0, 8));
}

function generateApiKey($sessionId)
{
    return sha1($sessionId . microtime(true) . mt_rand(10000, 90000));
}

function sendResponse($response, $exitAfter = true)
{
    if ($response instanceof LeagleEyeException) {
        $base = $response->getResponse();
    } else {
        $base = $response;
    }

    if (!isset($base['status'])) {
        $base['status'] = ['code' => null, 'message' => ''];
    }

    //$status = &$base['status'];

    if ($base['status']['message'] == '') {
        switch ($base['status']['code']) {
            case 200:
                $base['status']['message'] = "OK";
                break;
            case 201:
                $base['status']['message'] = "Created";
                break;
            case 400:
                $base['status']['message'] = "Bad Request";
                break;
            case 500:
                $base['status']['message'] = "Internal Server Error";
                break;
            case null:
                $base['status']['code'] = 500;
                $base['status']['message'] = "No Status Provided";
                break;
        }
    }

    Header("HTTP/1.1 {$base['status']['code']} {$base['status']['message']}");
    Header("Content-type: application/json");
    print json_encode($base);
    if ($exitAfter) {
        exit();
    }
}

function getPermission($action, $compare = array())
{
    global $scope;
    global $permissions;

    if (count($scope) == 0) {
        return false;
    }

    foreach (array("event", "product", "segment") as $attribute) {
        if (!fnmatch($scope[$attribute], $compare[$attribute])) {
            return false;
        }
    }

    $key = strtoupper("allow_" . $action);
    if (!isset($permissions[$key])) {
        return false;
    } else {
        return ($permissions[$key] == 1);
    }
}

function getScopeByEventSession($event)
{
    global $mysqli;

    $sqlQuery = <<<EOF
    SELECT
        session AS event,
        tbl__events.segmentkey AS segment,
        tbl__segments.productkey AS product
    FROM
        tbl__events
    LEFT JOIN
        tbl__segments
    ON
        tbl__events.segmentkey=tbl__segments.segmentkey
    WHERE
        session=(?)
    LIMIT 1
EOF;

    // By virtue of LIMIT 1 this can only ever have a single row, so send back the zeroth element.
    return $mysqli->select($sqlQuery, [['s' => $event]])[0];
}