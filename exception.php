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

class LeagleEyeMySQLiException extends LeagleEyeException {
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
    }
}

class MySQLiNotConnectedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiStatementNotPreparedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiSelectQueryFailedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiInsertQueryFailedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiUpdateQueryFailedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiDeleteQueryFailedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiRowNotInsertedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiNothingSelectedException extends LeagleEyeMySQLiException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}


class BadRequestException extends LeagleEyeException { }
class WhatTheHeckIsThisException extends LeagleEyeException { }

class InvalidJsonException extends LeagleEyeException { }

class UnsanitizedInputException extends LeagleEyeException {}

class ApiKeyNotPrivilegedException extends LeagleEyeException {}

?>