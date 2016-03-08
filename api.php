<?php

error_reporting(E_ALL);

require 'exception.php';

// Set connString to nothing to appease the IDE.
// mysqli_conn sets the variable $connString for use with MySQLi
$connString = '';

/** @noinspection PhpIncludeInspection */
require '../mysqli_conn.php';

$API_PATH = 'api';

$mysqlCredentials = array();

foreach (explode(';', $connString) as $entry) {
    list($key, $value) = explode('=', $entry);
    $mysqlCredentials[$key] = $value;
}

$method = $_SERVER['REQUEST_METHOD'];
$pattern = '/^\/' . $API_PATH . '\/([a-z0-9]{40}|)\/?(\w+$|\w+(?=[\/]))\/?(.+)?/';

if (strpos($_SERVER['REQUEST_URI'],"?") > 0) {
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

unset($pattern);
unset($matches);
unset($apiPath);

$action = 'api_' . strtoupper($object . '_' . $method);

$action = filter_var($action, FILTER_SANITIZE_EMAIL);

$mysqli = new mysqli();

try {
    $mysqli->connect($mysqlCredentials['Data Source'], $mysqlCredentials['User Id'], $mysqlCredentials['Password'], $mysqlCredentials['Database']);

    if ($mysqli->errno) {
        throw new MySQLiNotConnectedError($mysqli->errno . ": " . $mysqli->error);
    }
} catch (Exception $e) {
    print_r($e);
    exit();
}

try {
    if (function_exists($action)) {
        // Explicitly cast $action as a string to reassure the debugger.
        $action = (string) $action;
        $action();
    } else {
        /** @noinspection PhpUndefinedClassInspection */
        throw new BadRequestException();
    }
    /** @noinspection PhpUndefinedClassInspection */
} catch (BadRequestException $bre) {
    $message = $bre->getMessage();
    if (strlen($message) == 0) {
        $message = "Bad Request";
    }
    Header("HTTP/1.1 400 {$message}");
    exit();
}

exit();

function api_EVENTS_GET()
{

//    if (!($result = $GLOBALS['mysqli']->query("SHOW DATABASES;"))) {
//        throw new Exception($GLOBALS['mysqli']->errno . ": " . $GLOBALS['mysqli']->error);
//    }
//    while($row = $result->fetch_assoc()) {
//        print_r($row);
//    }

    if ($GLOBALS['apiKey'] != "d9cef133acdb2c35c21a20031a5dfc10f77d03f4") {
        /** @noinspection PhpUndefinedClassInspection */
        throw new BadRequestException();
    }

    $attachmentIndex = '';
    $object2 = '';
    $session = '';

    switch (count($GLOBALS['path'])) {
        /** @noinspection PhpMissingBreakStatementInspection */
        case 3:
            $attachmentIndex = $GLOBALS['path'][2];
        /** @noinspection PhpMissingBreakStatementInspection */
        case 2:
            $object2 = $GLOBALS['path'][1];
        case 1:
            $session = $GLOBALS['path'][0];
            break;
        case 0:
            break;
        default:
            /** @noinspection PhpUndefinedClassInspection */
            throw new BadRequestException();
            break;
    }

    if (strlen($session) != 8) {
        /** @noinspection PhpUndefinedClassInspection */
        throw new BadRequestException();
    }

    if (isset($object2) && strlen($object2) > 0) {
        if ($object2 != "attachments") {
            /** @noinspection PhpUndefinedClassInspection */
            throw new BadRequestException();
        } else {
            if (isset($attachmentIndex)) {
                print "Attachment information.";
            } else {
                print "Attachment list.";
            }
        }
    } else {
        print "Event list.";
    }
}

function api_EVENTS_POST()
{
    /** @var mysqli $mysqli */
    $mysqli = $GLOBALS['mysqli'];

    switch (count($GLOBALS['path'])) {
        /** @noinspection PhpMissingBreakStatementInspection */
        case 2:
            $object2 = $GLOBALS['path'][1];
        case 1:
            $session = $GLOBALS['path'][0];
            break;
        case 0:
            break;
        default:
            throw new BadRequestException();
            break;
    }

    if (isset($object2) && strlen($object2) > 0) {
        // Reassure the debugger
        if (!isset($session)) {
            $session = '';
        }
        if (strlen($session) != 8) {
            /** @noinspection PhpUndefinedClassInspection */
            throw new BadRequestException();
        }

        if ($object2 != "attachments" || $GLOBALS['apiKey'] != "d9cef133acdb2c35c21a20031a5dfc10f77d03f4") {
            /** @noinspection PhpUndefinedClassInspection */
            throw new BadRequestException();
        }

        if (!empty($_FILES)) {
            foreach ($_FILES as $file) {
                if ($file['error'] > 0) {
                    echo "Error: " . $file['error'];
                } else {
                    Header("HTTP/1.1 201 Created");
                    $destination = getcwd() . '/up/' . $session . '_' . $file['name'];
                    move_uploaded_file($file['tmp_name'], $destination);
                    //chmod($destination, 0644);
                }
            }
        } else {
            print "No file\n";
        }


    } else {
        try {
            $jsonRequest = json_decode($_POST['request'], true);

            if (!$jsonRequest) {
                print json_last_error_msg() . "\n";

                switch (json_last_error()) {
                    case JSON_ERROR_NONE:
                        echo ' - No errors';
                        break;
                    case JSON_ERROR_DEPTH:
                        echo ' - Maximum stack depth exceeded';
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        echo ' - Underflow or the modes mismatch';
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        echo ' - Unexpected control character found';
                        break;
                    case JSON_ERROR_SYNTAX:
                        echo ' - Syntax error, malformed JSON';
                        break;
                    case JSON_ERROR_UTF8:
                        echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                        break;
                    default:
                        echo ' - Unknown error';
                        break;
                }
                throw new InvalidJsonException();
            }

            foreach ($jsonRequest as $key => $value) {
                $filter = null;
                $options = [];
                switch ($key) {
                    case 'phone_number':
                        $filter = FILTER_VALIDATE_REGEXP;
                        $options = array("options"=>array("regexp"=>"/^\+? ?[0-9 ]+$/"));
                        break;
                    case 'email_address':
                        $filter = FILTER_VALIDATE_EMAIL;
                        break;
                    case 'latitude':
                    case 'longitude':
                        $filter = FILTER_VALIDATE_FLOAT;
                        break;
                    default:
                        throw new UnsanitizedInputException($key);
                }
                if (!filter_var($value, $filter, $options)) {
                    throw new BadRequestException("Parameter '{$key}':'{$value}' is not valid.");
                }
            }

            $sqlQuery = <<<EOF
    INSERT INTO
        api__dev__events
        (
            session,
            phone_number,
            email_address,
            latitude,
            longitude
        ) VALUES (?, ?, ?, ?, ?);
EOF;

            /** @var mysqli_stmt $eventQuery */
            if (!$eventQuery = $mysqli->prepare($sqlQuery)) {
                throw new MySQLiStatementNotPreparedException($mysqli);
            }

            do {
                $sessionId = generateSessionId();
                $eventQuery->bind_param("sssdd",
                        $sessionId,
                        $jsonRequest['phone_number'],
                        $jsonRequest['email_address'],
                        $jsonRequest['latitude'],
                        $jsonRequest['longitude']
                    );
                $eventQuery->execute();
            } while($eventQuery->errno == 1062);

            switch($eventQuery->affected_rows) {
                case -1:
                    throw new MySQLiInsertQueryFailureException(print_r($eventQuery,true));
                    break;
                case 0:
                    throw new MySQLiRowNotInsertedException(print_r($eventQuery,true));
                    break;
            }

            $sqlQuery = <<<EOF
INSERT INTO
    api__dev__apikeys
(
    expiration,
    is_renewable,
    scope,
    ALLOW_UPLOAD,
    apikey,
    scopekey
)
VALUES
(
    DATE_ADD(NOW(), INTERVAL 1 HOUR),
    1,
    'EVENT',
    1,
    ?, ?
)
;
EOF;

            /** @var mysqli_stmt $apiKeyQuery */
            if (!$apiKeyQuery = $mysqli->prepare($sqlQuery)) {
                throw new MySQLiStatementNotPreparedException($mysqli);
            }

            do {
                $apiKey = generateApiKey($sessionId);
                $scopeKey = (int) $eventQuery->insert_id;

                $apiKeyQuery->bind_param("si",
                        $apiKey,
                        $scopeKey
                    );
                $apiKeyQuery->execute();
            } while($apiKeyQuery->errno == 1062);

            switch($apiKeyQuery->affected_rows) {
                case -1:
                    throw new MySQLiInsertQueryFailureException(print_r($apiKeyQuery,true));
                    break;
                case 0:
                    throw new MySQLiRowNotInsertedException(print_r($apiKeyQuery,true));
                    break;
            }
            $eventQuery->close();
            $apiKeyQuery->close();

            $response = array();
            $response['session'] = $sessionId;
            $response['dial'] = "+1 407 934 7639";
            $response['apiKey'] = $apiKey;

            Header("HTTP/1.1 201 Created");
            Header("Content-type: application/json");
            print json_encode($response);
        } catch (Exception $e) {
            print_r($e);
            exit();
        }
    }

}

function generateSessionId() {
    return strtoupper(substr(str_shuffle(str_repeat("aeufhlmr145670", 8)), 0, 8));
}

function generateApiKey($sessionId){
    return sha1($sessionId . microtime(true) . mt_rand(10000,90000));
}