<?php

namespace Arris\Presenter;

use Psr\Log\LoggerInterface;

interface BreadcrumbsInterface
{

    public function __construct(LoggerInterface $logger = null);

    public function addTitle(string $title):self;

    public function addPath(array $part):self;

    public function addCrumb($url, $title):self;

    public function implodeCrumbs(string $mask = '<a href="%s">%s</a>', bool $use_crlf = false): string;

    public function getCrumbs(bool $reverse = true):array;

    public function getTitles(bool $reverse = true):array;

    public function crumbsToTitles():array;
}

#-eof-#