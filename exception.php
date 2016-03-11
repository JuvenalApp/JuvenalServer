<?php

class BadRequestException extends Exception { }

class InvalidJsonException extends Exception { }

class UnsanitizedInputException extends Exception {}

class ApiKeyNotPrivilegedException extends Exception {}


class MySQLiNotConnectedError extends Exception { }
class MySQLiStatementNotPreparedException extends Exception { }

class MySQLiSelectQueryFailedException extends Exception { }
class MySQLiInsertQueryFailureException extends Exception { }
class MySQLiUpdateQueryFailureException extends Exception { }
class MySQLiDeleteQueryFailureException extends Exception { }

class MySQLiRowNotInsertedException extends Exception { }
class MySQLiNothingSelectedException extends Exception { }

?>