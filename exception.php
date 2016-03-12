<?php

class LeagleEyeException extends Exception
{
    public function __construct($message = ['error' => 'Unspecified Error'], $code = 0, Exception $previous = null) {

        parent::__construct($message['error'], $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: " . print_r($message,true) . "\n";
    }
}


class BadRequestException extends LeagleEyeException { }
class WhatTheHeckIsThisException extends LeagleEyeException { }

class InvalidJsonException extends LeagleEyeException { }

class UnsanitizedInputException extends LeagleEyeException {}

class ApiKeyNotPrivilegedException extends LeagleEyeException {}


class MySQLiNotConnectedException extends LeagleEyeException { }
class MySQLiStatementNotPreparedException extends LeagleEyeException { }

class MySQLiSelectQueryFailedException extends LeagleEyeException { }
class MySQLiInsertQueryFailedException extends LeagleEyeException { }
class MySQLiUpdateQueryFailedException extends LeagleEyeException { }
class MySQLiDeleteQueryFailedException extends LeagleEyeException { }

class MySQLiRowNotInsertedException extends LeagleEyeException { }
class MySQLiNothingSelectedException extends LeagleEyeException { }

?>