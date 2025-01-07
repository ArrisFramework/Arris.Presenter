<?php

namespace Arris\Presenter;

use Arris\Presenter\Core\Stack;
use Psr\Log\LoggerInterface;

class Breadcrumbs implements BreadcrumbsInterface
{
    private Stack $crumbs;
    private Stack $titles;

    /**
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->titles = new Stack();
        $this->crumbs = new Stack();
    }

    /**
     * Добавляет элемент в стэк Titles
     *
     * @param string $title
     * @return $this
     */
    public function addTitle(string $title):self
    {
        $this->titles->push($title);
        return $this;
    }

    /**
     * Добавляет хлебную крошку вида `url => ..., title => ...`
     *
     * @param array $part
     * @return $this
     */
    public function addPath(array $part):self
    {
        $this->crumbs->push($part);
        return $this;
    }

    /**
     * Добавляет хлебную крошку через URL+TITLE
     *
     * @param $url
     * @param $title
     * @return $this
     */
    public function addCrumb($url, $title):self
    {
        $this->crumbs->push([
            'url'   =>  $url,
            'title' =>  $title
        ]);
        return $this;
    }

    /**
     * Формирует строку хлебных крошек по маске
     *
     * @param string $mask - Маска, содержит два управляющих символа %s, %s
     * @param bool $use_crlf
     * @return string
     */
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

    /**
     * Возвращает массив хлебных крошек
     *
     * @param true $reverse
     * @return array
     */
    public function getCrumbs(bool $reverse = true):array
    {
        return $reverse
            ? $this->crumbs->get()
            : $this->crumbs->getReversed();
    }

    /**
     * Возвращает массив тайтлов
     *
     * @param bool $reverse
     * @return array
     */
    public function getTitles(bool $reverse = true):array
    {
        return $reverse
            ? $this->titles->get()
            : $this->titles->getReversed();
    }

    /**
     * Конвертирует хлебные крошки в массив тайтлов и возвращает их массивом
     *
     * @return array
     */
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