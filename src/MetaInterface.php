<?php

namespace Arris\Presenter;

interface MetaInterface
{
    public function setTitle(string $title): static;

    public function setDescription(string $description): static;

    public function setKeywords(string $keywords): static;

    public function setRobots(string $robots): static;

    public function setCanonical(string $canonical): static;

    public function setAuthor(string $author): static;

    public function setOgType(string $type): static;

    public function setOgTitle(string $title): static;

    public function setOgDescription(string $description): static;

    public function setOgImage(string $image): static;

    public function setOgUrl(string $url): static;

    public function setOgSiteName(string $name): static;

    public function setOgLocale(string $locale): static;

    public function addOgLocaleAlternate(string $locale): static;

    public function setTwitterCard(string $card): static;

    public function setTwitterImage(string $image): static;

    public function addCustom(string $name, string $content): static;

    public function clear(): void;

    public function toArray(): array;

    public function render(int $indent = 4, string $char = ' '): string;
}
