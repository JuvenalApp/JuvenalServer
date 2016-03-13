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

class LeagleEyeGeneralServerErrorException extends LeagleEyeException {
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

class MySQLiNotConnectedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiStatementNotPreparedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiSelectQueryFailedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiInsertQueryFailedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiUpdateQueryFailedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiDeleteQueryFailedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiRowNotInsertedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class MySQLiNothingSelectedException extends LeagleEyeGeneralServerErrorException {
    function __construct(array $trace, $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class LeagleEyeGeneralBadRequestException extends LeagleEyeException {
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

class BadRequestException extends LeagleEyeGeneralBadRequestException {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class InvalidJsonException extends LeagleEyeGeneralBadRequestException {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}
class UnsanitizedInputException extends LeagleEyeGeneralBadRequestException {
    function __construct(array $trace = [], $previous = null) {
        parent::__construct(__CLASS__, $trace, $previous);
    }
}

class ApiKeyNotPrivilegedException extends LeagleEyeException {}
class WhatTheHeckIsThisException extends LeagleEyeException { }

?>