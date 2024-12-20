<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Stack;

class Breadcrumbs implements BreadcrumbsInterface
{
    private Stack $crumbs;
    private Stack $titles;

    /**
     *
     */
    public function __construct()
    {
        $this->titles = new Stack();
        $this->crumbs = new Stack();
    }

    public function addTitle(string $title):self
    {
        $this->titles->push($title);
        return $this;
    }

    public function addPath(array $part):self
    {
        $this->crumbs->push($part);
        return $this;
    }

    public function addCrumb($url, $title):self
    {
        $this->crumbs->push([
            'url'   =>  $url,
            'title' =>  $title
        ]);
        return $this;
    }

    public function implodeCrumbs(string $mask = '<a href="%s">%s</a>', bool $use_crlf = false): string
    {
        $crlf = $use_crlf ? "\n" : "";
        $b = clone $this->crumbs;
        $b->reverse();
        $out = [];
        do {
            $el = $b->pop();
            $out[] = \vsprintf($mask, $el);
        } while (!$b->isEmpty());

        return \implode($crlf, $out);
    }

    public function getCrumbs(bool $reverse = true):array
    {
        return $reverse
            ? $this->crumbs->get()
            : $this->crumbs->getReversed();
    }

    public function getTitles(bool $reverse = true):array
    {
        return $reverse
            ? $this->titles->get()
            : $this->titles->getReversed();
    }

    public function crumbsToTitles():array
    {
        $b = clone $this->crumbs;
        $this->titles->clear();

        do {
            $el = $b->pop();
            $this->titles->push($el['title']);
        } while (!$b->isEmpty());

        return $this->titles->getReversed();
    }

}

#-eof-#