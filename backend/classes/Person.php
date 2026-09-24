<?php

/**
 * A person's name — kept separate from Student so the inheritance
 * relationship (Student extends Person) is real, not just a formality.
 * Matches the Person -> Student pattern from the OOP PHP slides.
 */
class Person
{
    private string $firstName;
    private string $middleName;
    private string $lastName;
    private string $suffix;

    public function __construct(string $firstName, string $middleName, string $lastName, string $suffix)
    {
        $this->firstName = $firstName;
        $this->middleName = $middleName;
        $this->lastName = $lastName;
        $this->suffix = $suffix;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getMiddleName(): string
    {
        return $this->middleName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /** Combines the name parts into one display string, skipping any that are blank. */
    public function getFullName(): string
    {
        $parts = array_filter([
            $this->firstName,
            $this->middleName,
            $this->lastName,
            $this->suffix,
        ]);

        return trim(implode(' ', $parts));
    }
}
