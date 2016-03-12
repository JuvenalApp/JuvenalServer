<?php

class LeagleEyeException extends Exception
{
    private $thisException;

    public function __construct($exception = ['error' => 'Unspecified Error'], $previous = null) {
        $thisException = $exception;

        if (!is_null($previous)) {
            $thisException['previous'] = $previous;
        }
        //parent::__construct($message['error'], $code, $previous);
    }

    public function __toString() {
        return __CLASS__ . "\n" . print_r($thisException,true) . "\n";
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