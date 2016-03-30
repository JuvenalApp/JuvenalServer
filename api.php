<?php
error_reporting(E_ALL);

// Set these to -something- in case the include fails
$API_PATH = '';
$SQL_PREFIX = '';
$CONNECTION_STRING = '';
$requestPath = '';
$requestQuery = [];
$database = null;

/** @noinspection PhpIncludeInspection */
require '../config.inc.php';
require 'exception.php';
require 'mysqli.php';

//main();
//
//exit();
//
//function main()
//{
//    global $API_PATH;
//    global $CONNECTION_STRING;
//    global $mysqlCredentials;
//    global $requestPath;
//    global $requestFilter;
//    global $database;

$mysqlCredentials = [];

foreach (explode(';', $CONNECTION_STRING) as $entry) {
    list($key, $value) = explode('=', $entry);
    $mysqlCredentials[$key] = $value;
}

$method = $_SERVER['REQUEST_METHOD'];
$pattern = '/^\/' . preg_quote($API_PATH, '/') . '([a-z0-9]{40}|)\/?(\w+$|\w+(?=[\/]))\/?(.+)?/';

if (strpos($_SERVER['REQUEST_URI'], "?") > 0) {
    list($requestPath, $queryString) = explode('?', $_SERVER['REQUEST_URI']);
} else {
    $requestPath = $_SERVER['REQUEST_URI'];
    $queryString = '';
}

$requestPath = strtolower($requestPath);

foreach (explode('&', $queryString . '&') as $entry) {
    if (strlen($entry) > 0) {
        list($key, $value) = explode('=', $entry);
        $requestQuery[$key] = $value;
    }
}

print_r($requestQuery);

/* Depending on server configuration,
   the path may or more not start with /
*/

if (substr($requestPath, 0, 1) != '/') {
    $requestPath = "/" . $requestPath;
}

preg_match($pattern, $requestPath, $matches);

$path = '';
$object = '';

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

$action = 'api_' . strtoupper($object . '_' . $method) . '_dispatch';
$action = filter_var($action, FILTER_SANITIZE_EMAIL);

try {
    $database = new MySQLiDriver($mysqlCredentials);
} catch (Exception $e) {
    sendResponse([$e]);
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
    tbl__apikeys
WHERE
    apikey=(?)
LIMIT 1
EOF;

    $permissions = null;
    try {
        $permissions = $database->select($sqlQuery, [['s' => $apiKey]])[0];
    } catch (DatabaseNothingSelectedException $e) {
        throw new ApiKeyNotPrivilegedException([$apiKey], $e);
    }

    $parameter = '';
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
                    'trace' => [$database]
                ];
                throw new WhatTheHeckIsThisException($response);
                break;
        }

        if (isset($table)) {
            $sqlQuery = <<<EOF
    SELECT
        {$field}
    FROM
        tbl__{$table}
    WHERE
        {$lookup}=(?)
    LIMIT 1
EOF;

            $scopeResult = $database->select($sqlQuery, [["i" => $parameter]])[0];

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
                $database
            ]
        ];
        throw new BadRequestException($response);
    }
} catch (Exception $e) {
    sendResponse($e);
}

exit();


function api_SEARCH_GET_dispatch()
{
    global $path;

    if (count($path) != 1) {
        throw new BadRequestException([$path]);
    }

    if (!getPermission("VIEW", getScopeByEventSession(''))) {
        $response = [
            'status' => ['code' => 401],
            'error' => ['message' => 'Underprivileged API Key.']
        ];
        throw new BadRequestException($response);
    }

    print "Search!";
}

function api_EVENTS_GET_dispatch()
{
    //global $database;
    global $path;
    global $requestQuery;

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

    if (!getPermission("LIST", getCurrentScope())) {
        $response = [
            'status' => ['code' => 401],
            'error' => ['message' => 'Underprivileged API Key.']
        ];
        throw new BadRequestException($response);
    }

    $eventColumns = [
        'session',
        'phonenumber',
        'emailaddress',
        'latitude',
        'longitude',
        'postal_code',
        'returned_number',
        'state'
    ];

    $columnsToSelect = [];
    $orderByColumns = [];

    print_r($requestQuery);

    if (count($requestQuery) > 0) {

        // Did they provide Query parameters?
        if (key_exists('select', $requestQuery)) {
            // We've been given specific columns to Select.
            $columns = explode(',', $requestQuery['select'] . ',');

            // Only include valid columns.
            foreach ($columns as $column) {
                if (key_exists($column, $eventColumns)) {
                    $columnsToSelect[] = $column;
                } else {
                    // Intentionally dropping this invalid entry.
                }
            }
        }

        if (key_exists('order', $requestQuery)) {
            // We've been given a specific order.
            $columns = explode(',', $requestQuery['order'] . ',');

            foreach ($columns as $column) {
                if (strlen($column) == 0) {
                    continue;
                }

                // Did they give us a column name only, or a column name and direction?
                if (stristr($column, " ") > 0) {
                    list($columnName, $direction) = explode(' ', $column);
                } else {
                    $columnName = explode(' ', $column);
                    $direction = '';
                }

                // Is it a valid column? Drop it if not.
                if (key_exists($columnName, $eventColumns)) {
                    switch ($direction) {
                        case 'ASC':
                        case 'DESC':
                            // These are acceptable Order directions.
                            break;
                        default:
                            $direction = 'ASC';
                            break;
                    }
                    $orderByColumns[] = $columnName . " " . $direction;
                } else {
                    // Intentionally dropping this invalid entry.
                }
            }
        }
    }

    if (count($columnsToSelect) == 0) {
        $columnsToSelect = $eventColumns;
    }
    $columnsInQuery = join(",\n            ", $columnsToSelect);

    $orderBy = '';
    if (count($orderByColumns) > 0) {
        $orderBy = "ORDER BY\n            ";
        $orderBy = $orderBy . join(",\n            ", $orderByColumns);
    }

    $scope = getCurrentScope();
    $criteria = [];
    if ($scope['product'] != "*") {
        $criteria[] = "productkey=" .$scope['product'];
    }
    if ($scope['segment'] != "*") {
        $criteria[] = "segmentkey=" . $scope['segment'];
    }
    if ($scope['event'] != "*") {
        $criteria[] = "session='" . $scope['event'] . "'";
    }

    $whereClause = join("\n         AND ", $criteria);

    $begin = 0;
    $end = 10;

    $sqlQuery = <<<EOF
            
        SELECT
            {$columnsInQuery}
        FROM
            tbl__events
        WHERE
            {$whereClause}
        {$orderBy}
        LIMIT
            {$begin}, {$end}
EOF;


    print $sqlQuery;
}

function api_EVENTS_PUT_ID($id)
{

}

function api_EVENTS_POST_dispatch()
{
    global $database;
    global $path;
    global $apiKey;

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
                'state' => [
                    'filter' => FILTER_VALIDATE_REGEXP,
                    'options' => [
                        'options' => [
                            'regexp' => "/^[A-Z]{2}$/"
                        ]
                    ]
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
                        phonenumber,
                        emailaddress,
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

                    $eventQuery = $database->insert($sqlQuery, [
                            ['s' => $sessionId],
                            ['i' => $jsonRequest['segment']],
                            ['s' => $jsonRequest['phoneNumber']],
                            ['s' => $jsonRequest['emailAddress']],
                            ['d' => $jsonRequest['latitude']],
                            ['d' => $jsonRequest['longitude']]
                        ]
                    );

                    $eventAdded = true;
                } catch (DatabaseInsertQueryFailedException $e) {
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

            $apiKeyQuery = null;
            do {
                try {
                    $apiKey = generateApiKey($sessionId);

                    $apiKeyQuery = $database->insert($sqlQuery, [
                            ['s' => $apiKey],
                            ['i' => $scopeKey]
                        ]
                    );

                    $apiKeyAdded = true;
                } catch (DatabaseInsertQueryFailedException $e) {
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

function api_EVENTS_POST_ID_ATTACHMENTS($id)
{
    global $database;

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

    $sqlQuery = <<<EOF

        SELECT
            eventkey
        FROM
            tbl__events
        WHERE
            session=?
        LIMIT 1

EOF;

    $sessionkey = $database->select($sqlQuery, [["s" => $id]])[0]['eventkey'];

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
                $sqlQuery = <<<EOF

                    INSERT INTO
                        tbl__files
                    (
                        eventkey,
                        filename,
                        filepath
                    )
                    VALUES
                    (?, ?, ?)

EOF;
                $database->insert($sqlQuery, [
                    ['i' => $sessionkey],
                    ['s' => $file['name']],
                    ['s' => $destination]
                ]);
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
            $sqlQuery = <<<EOF

                UPDATE
                    tbl__apikeys
                SET
                    expiration = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    last_renewal = NOW()
                WHERE
                    scope='EVENT'
                AND scopekey=(SELECT eventkey FROM tbl__events WHERE session=?)
                AND is_expired=0

EOF;

            try {
//                $eventQuery =
                    $database->update($sqlQuery, [
                        ['s' => $id]
                    ]
                );
            } catch (DatabaseInsertQueryFailedException $e) {
                //$lastError = print_r($e, true);
            }

        }
    } catch (LeagleEyeException $e) {
        $status['error']['apiKeyRenewalError'] = $e->getResponse();
    }
    sendResponse($status);
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

    header("HTTP/1.1 {$base['status']['code']} {$base['status']['message']}");
    header("Content-type: application/json");
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
    global $database;

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
    return $database->select($sqlQuery, [['s' => $event]])[0];
}

function getCurrentScope() {
    global $scope;
    return $scope;
}