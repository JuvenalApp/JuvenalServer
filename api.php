<?php

class BadRequestException extends Exception { }

$method = $_SERVER['REQUEST_METHOD'];
$pattern = '/^\/([a-z0-9]{40}|)\/?(\w+$|\w+(?=[\/]))\/?(.+)?/';
list($apiPath, $filter) = explode("?", $_SERVER['QUERY_STRING']);

/* Depending on server configuration,
   the / may or may not preceed the path
*/

if (substr($apiPath,0,1) != "/" ) {
	$apiPath = "/" . $apiPath;
}	

preg_match($pattern, $apiPath, $matches);

switch (count($matches)) {
    case 4:
        $path = explode("/", $matches[3]);
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

try {
    if (function_exists($action)) {
        $action();
    } else {
        throw new BadRequestException();    
    }
} catch (BadRequestException $bre) {
    $message = $bre->getMessage();
    if (strlen($message) == 0) {
        $message = "Bad Request";
    }
    Header("HTTP/1.1 400 {$message}");
    exit();        
}

exit(1);

function api_EVENTS_GET() {

    if ($GLOBALS['apiKey'] != "d9cef133acdb2c35c21a20031a5dfc10f77d03f4") {
        throw new BadRequestException();
    }

    switch(count($GLOBALS['path'])) {
        case 3:
            $attachmentIndex = $GLOBALS['path'][2];
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

    if (strlen($session) != 8) {
        throw new BadRequestException();
    }

    if (isset($object2) && strlen($object2) > 0) {
        if ($object2 != "attachments") {
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

function api_EVENTS_POST() {
    switch(count($GLOBALS['path'])) {
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
	if (strlen($session) != 8) {
	    throw new BadRequestException();
	}

        if ($object2 != "attachments" || $GLOBALS['apiKey'] != "d9cef133acdb2c35c21a20031a5dfc10f77d03f4") {
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
           Header("HTTP/1.1 201 Created");
	   Header("Content-type: application/json");
	   $sessionId = strtoupper(substr(str_shuffle(str_repeat("aeufhlmr145670", 8)), 0, 8));
	   print <<<EOF
{"session":"{$sessionId}","dial":"+1 407 934 7639","apiKey":"d9cef133acdb2c35c21a20031a5dfc10f77d03f4"}

EOF;
    }
    
}

/**/
?>