<?php

require_once __DIR__ . '/Person.php';
require_once __DIR__ . '/Program.php';

/**
 * The system's main entity. Extends Person for name handling (inheritance),
 * and holds a Program — a related entity — for whichever program they're
 * enrolled in.
 */
class Student extends Person
{
    private string $studentId;
    private string $email;
    private string $contactNumber;
    private ?string $address;
    private Program $program;
    private string $yearLevel;
    private string $status;
    private string $dateRegistered;

    public function __construct(
        string $studentId,
        string $firstName,
        string $middleName,
        string $lastName,
        string $suffix,
        string $email,
        string $contactNumber,
        ?string $address,
        Program $program,
        string $yearLevel,
        string $status,
        string $dateRegistered
    ) {
        parent::__construct($firstName, $middleName, $lastName, $suffix);

        $this->studentId = $studentId;
        $this->email = $email;
        $this->contactNumber = $contactNumber;
        $this->address = $address;
        $this->program = $program;
        $this->yearLevel = $yearLevel;
        $this->status = $status;
        $this->dateRegistered = $dateRegistered;
    }

    public function getStudentId(): string
    {
        return $this->studentId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getContactNumber(): string
    {
        return $this->contactNumber;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getProgram(): Program
    {
        return $this->program;
    }

    public function getYearLevel(): string
    {
        return $this->yearLevel;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDateRegistered(): string
    {
        return $this->dateRegistered;
    }

    /** Shapes this student the same way the API has always responded — the JSON output doesn't change. */
    public function toArray(): array
    {
        return [
            'id' => $this->studentId,
            'name' => $this->getFullName(),
            'firstName' => $this->getFirstName(),
            'middleName' => $this->getMiddleName(),
            'lastName' => $this->getLastName(),
            'suffix' => $this->getSuffix(),
            'email' => $this->email,
            'contact' => $this->contactNumber,
            'address' => $this->address,
            'program' => $this->program->getName(),
            'year' => $this->yearLevel,
            'status' => $this->status,
            'date' => $this->dateRegistered,
        ];
    }
}
