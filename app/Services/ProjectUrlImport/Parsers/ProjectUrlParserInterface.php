<?php

namespace App\Services\ProjectUrlImport\Parsers;

interface ProjectUrlParserInterface
{
    public function sourceKey(): string;

    public function parserVersion(): string;

    public function extract(string $url): array;
}
