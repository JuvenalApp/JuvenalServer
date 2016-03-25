<?php

class BadRequestException extends Exception { }

class MySQLiNotConnectedError extends Exception { }

class MySQLiStatementNotPreparedException extends Exception { }

class InvalidJsonException extends Exception { }

class MySQLiInsertQueryFailureException extends Exception { }

class MySQLiRowNotInsertedException extends Exception { }

class UnsanitizedInputException extends Exception {}

?>