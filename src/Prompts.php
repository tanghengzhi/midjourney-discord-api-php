<?php

namespace Ferranfg\MidjourneyPhp;

class Prompts
{
    private string $imagePrompts;

    private string $textPrompt;

    private string $parameters;

    public function __construct($imagePrompts, $textPrompt, $parameters)
    {
        $this->imagePrompts = $imagePrompts ?? "";
        $this->textPrompt = $textPrompt;
        $this->parameters = $parameters ?? "";
    }

    public function toString(): string
    {
        return "{$this->imagePrompts} {$this->textPrompt} {$this->parameters}";
    }

    public function withoutImagePrompts()
    {
        return "{$this->textPrompt} {$this->parameters}";
    }
}
