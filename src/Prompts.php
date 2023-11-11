<?php

namespace Ferranfg\MidjourneyPhp;

class Prompts
{
    private string $imagePrompts;

    private string $textPrompt;

    private string $parameters;

    public function __construct(?string $imagePrompts, string $textPrompt, ?string $parameters)
    {
        $this->imagePrompts = $imagePrompts ? trim($imagePrompts) : "";
        $this->textPrompt = trim($textPrompt);
        $this->parameters = $parameters ? trim($parameters) : "";
    }

    public function toString(): string
    {
        return trim("{$this->imagePrompts} {$this->textPrompt} {$this->parameters}");
    }

    public function withoutImagePrompts(): string
    {
        return trim("{$this->textPrompt} {$this->parameters}");
    }
}
