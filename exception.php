<?php

class BadRequestException extends Exception { }

class MySQLiNotConnectedError extends Exception { }

class MySQLiStatementNotPreparedException extends Exception { }

class InvalidJsonException extends Exception { }

class MySQLiInsertQueryFailureException extends Exception { }

class MySQLiRowNotInsertedException extends Exception { }

class MySQLiSelectQueryFailedException extends Exception { }

class MySQLiNothingSelectedException extends Exception { }

class UnsanitizedInputException extends Exception {}

class ApiKeyNotPrivilegedException extends Exception {}

?>