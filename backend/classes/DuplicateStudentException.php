<?php

/**
 * Thrown when trying to register a student under an ID that's already taken.
 * A custom exception, created by extending PHP's built-in Exception class.
 */
class DuplicateStudentException extends Exception
{
}
