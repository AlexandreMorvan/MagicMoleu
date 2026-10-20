<?php

namespace App\Deck;

final readonly class DeckValidation
{
    /**
     * @param list<string> $errors messages en français, prêts à afficher
     */
    public function __construct(public array $errors)
    {
    }

    public function isValid(): bool
    {
        return !$this->errors;
    }
}
