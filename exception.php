<?php

class BadRequestException extends Exception { }

class InvalidJsonException extends Exception { }

class UnsanitizedInputException extends Exception {}

class ApiKeyNotPrivilegedException extends Exception {}


class MySQLiNotConnectedError extends Exception { }
class MySQLiStatementNotPreparedException extends Exception { }

class MySQLiSelectQueryFailedException extends Exception { }
class MySQLiInsertQueryFailedException extends Exception { }
class MySQLiUpdateQueryFailedException extends Exception { }
class MySQLiDeleteQueryFailedException extends Exception { }

class MySQLiRowNotInsertedException extends Exception { }
class MySQLiNothingSelectedException extends Exception { }

?>