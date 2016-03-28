<?php

class LeagleEyeException extends Exception
{
    private $e;

    public function __construct($exception = ['error' => 'Unspecified Error'], $previous = null) {
        $this->e = $exception;

        if (!is_null($previous)) {
            $this->e['previous'] = $previous;
        }
        //parent::__construct($message['error'], $code, $previous);
    }

    public function getResponse() {
        return $this->e;
    }

    public function __toString() {
        return __CLASS__ . "\n" . print_r($this->e,true) . "\n";
    }
}

class Http400Exception extends LeagleEyeException {
    public function __construct($message='', $trace = [], $previous = null) {
        $response = [
            'status' => [
                'code' => 400
            ],
            'error' => [
                'message' => $message,
                'trace' => $trace
            ]
        ];
        parent::__construct($response, $previous);
    }
}

class Http401Exception extends LeagleEyeException {
    public function __construct($message='', $trace = [], $previous = null) {
        $response = [
            'status' => [
                'code' => 401
            ],
            'error' => [
                'message' => $message,
                'trace' => $trace
            ]
        ];
        parent::__construct($response, $previous);
    }
}

class Http403Exception extends LeagleEyeException {
    public function __construct($message='', $trace = [], $previous = null) {
        $response = [
            'status' => [
                'code' => 403
            ],
            'error' => [
                'message' => $message,
                'trace' => $trace
            ]
        ];
        parent::__construct($response, $previous);
    }
}

class Http500Exception extends LeagleEyeException {
    public function __construct($message='', $trace = [], $previous = null) {
        $response = [
            'status' => [
                'code' => 500
            ],
            'error' => [
                'message' => $message,
                'trace' => $trace
            ]
        ];
        parent::__construct($response, $previous);
    }
}

class EventNotAddedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class ApiKeyNotAddedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseNotConnectedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseStatementNotPreparedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseSelectQueryFailedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseInsertQueryFailedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseUpdateQueryFailedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseDeleteQueryFailedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseRowNotInsertedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseNothingSelectedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseNothingUpdatedException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class DatabaseInvalidQueryTypeException extends Http500Exception {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class BadRequestException extends Http400Exception {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class InvalidJsonException extends Http400Exception {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class InvalidIdentifierException extends Http400Exception {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class NoFilesProvidedException extends Http400Exception {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class UnsanitizedInputException extends Http400Exception {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class ApiKeyNotPrivilegedException extends Http401Exception{
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class WhatTheHeckIsThisException extends LeagleEyeException { }

?>