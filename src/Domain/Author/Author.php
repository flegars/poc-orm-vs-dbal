<?php

namespace App\Domain\Author;

class Author
{
    public function __construct(
        private int $id,
        private string $name,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
