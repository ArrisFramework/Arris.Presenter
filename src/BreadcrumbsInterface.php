<?php

namespace Arris\Presenter;

interface BreadcrumbsInterface
{
    /**
     *
     */
    public function __construct();

    /**
     * Добавляет элемент в стэк Titles
     *
     * @param string $title
     * @return $this
     */
    public function addTitle(string $title):self;

    /**
     * Добавляет хлебную крошку вида `url => ..., title => ...`
     *
     * @param array $part
     * @return $this
     */
    public function addPath(array $part):self;

    /**
     * Добавляет хлебную крошку через URL+TITLE
     *
     * @param $url
     * @param $title
     * @return $this
     */
    public function addCrumb($url, $title):self;

    /**
     * Формирует строку хлебных крошек по маске
     *
     * @param string $mask - Маска, содержит два управляющих символа %s, %s
     * @param bool $use_crlf
     * @return string
     */
    public function implodeCrumbs(string $mask = '<a href="%s">%s</a>', bool $use_crlf = false): string;

    /**
     * Возвращает массив хлебных крошек
     *
     * @param true $reverse
     * @return array
     */
    public function getCrumbs(bool $reverse = true):array;

    /**
     * Возвращает массив тайтлов
     *
     * @param bool $reverse
     * @return array
     */
    public function getTitles(bool $reverse = true):array;

    /**
     * Конвертирует хлебные крошки в массив тайтлов и возвращает их массивом
     *
     * @return array
     */
    public function crumbsToTitles():array;
}

#-eof-#