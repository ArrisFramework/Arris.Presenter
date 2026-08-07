<?php

namespace Arris\Presenter;

/**
 * Мета-данные веб-страницы: стандартные теги, OpenGraph и Twitter Cards.
 *
 * Автономный класс: `render()` отдает готовую строку `<title>` + `<meta>`,
 * `toArray()` - структурированный массив для передачи в шаблон
 * (`$template->assign('meta', $template->meta->toArray())`).
 *
 * Fallback: og:title <- title, og:description <- description, og:url <- canonical,
 * twitter:title/description <- те же значения, twitter:image <- og:image.
 * Рендер экранирует значения через htmlspecialchars(ENT_QUOTES).
 */
class Meta implements MetaInterface
{
    /* Стандартные мета-теги */

    private string $title       = '';
    private string $description = '';
    private string $keywords    = '';
    private string $robots      = '';
    private string $canonical   = '';
    private string $author      = '';

    /* OpenGraph */

    private string $og_type        = 'website';
    private string $og_title       = '';
    private string $og_description = '';
    private string $og_image       = '';
    private string $og_url         = '';
    private string $og_site_name   = '';
    private string $og_locale      = 'ru_RU';
    private array $og_locale_alternates = [];

    /* Twitter Cards */

    private string $twitter_card  = 'summary';
    private string $twitter_image = '';

    /* Произвольные name/content теги */

    private array $custom = [];

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setKeywords(string $keywords): static
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function setRobots(string $robots): static
    {
        $this->robots = $robots;
        return $this;
    }

    public function setCanonical(string $canonical): static
    {
        $this->canonical = $canonical;
        return $this;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function setOgType(string $type): static
    {
        $this->og_type = $type;
        return $this;
    }

    public function setOgTitle(string $title): static
    {
        $this->og_title = $title;
        return $this;
    }

    public function setOgDescription(string $description): static
    {
        $this->og_description = $description;
        return $this;
    }

    public function setOgImage(string $image): static
    {
        $this->og_image = $image;
        return $this;
    }

    public function setOgUrl(string $url): static
    {
        $this->og_url = $url;
        return $this;
    }

    public function setOgSiteName(string $name): static
    {
        $this->og_site_name = $name;
        return $this;
    }

    public function setOgLocale(string $locale): static
    {
        $this->og_locale = $locale;
        return $this;
    }

    public function addOgLocaleAlternate(string $locale): static
    {
        $this->og_locale_alternates[] = $locale;
        return $this;
    }

    public function setTwitterCard(string $card): static
    {
        $this->twitter_card = $card;
        return $this;
    }

    public function setTwitterImage(string $image): static
    {
        $this->twitter_image = $image;
        return $this;
    }

    public function addCustom(string $name, string $content): static
    {
        $this->custom[] = [ 'name' => $name, 'content' => $content ];
        return $this;
    }

    public function clear(): void
    {
        $this->title       = '';
        $this->description = '';
        $this->keywords    = '';
        $this->robots      = '';
        $this->canonical   = '';
        $this->author      = '';

        $this->og_type        = 'website';
        $this->og_title       = '';
        $this->og_description = '';
        $this->og_image       = '';
        $this->og_url         = '';
        $this->og_site_name   = '';
        $this->og_locale      = 'ru_RU';
        $this->og_locale_alternates = [];

        $this->twitter_card  = 'summary';
        $this->twitter_image = '';

        $this->custom = [];
    }

    /**
     * Структурированный массив мета-данных (с применёнными fallback)
     *
     * @return array
     */
    public function toArray(): array
    {
        $og_title       = $this->og_title       !== '' ? $this->og_title       : $this->title;
        $og_description = $this->og_description !== '' ? $this->og_description : $this->description;
        $og_url         = $this->og_url         !== '' ? $this->og_url         : $this->canonical;

        return [
            'title'       => $this->title,
            'description' => $this->description,
            'keywords'    => $this->keywords,
            'robots'      => $this->robots,
            'canonical'   => $this->canonical,
            'author'      => $this->author,

            'og' => [
                'type'        => $this->og_type,
                'title'       => $og_title,
                'description' => $og_description,
                'image'       => $this->og_image,
                'url'         => $og_url,
                'site_name'   => $this->og_site_name,
                'locale'      => $this->og_locale,
                'locale_alternates' => $this->og_locale_alternates,
            ],

            'twitter' => [
                'card'        => $this->twitter_card,
                'title'       => $og_title,
                'description' => $og_description,
                'image'       => $this->twitter_image !== '' ? $this->twitter_image : $this->og_image,
            ],

            'custom' => $this->custom,
        ];
    }

    /**
     * Готовый HTML для <head>: <title> + <meta> + <link rel="canonical">
     *
     * Теги выводятся по одному на строку. Первая строка без отступа (её
     * позиционирует сам шаблон), каждая последующая получает префикс из
     * $indent символов $char - так блок выравнивается по отступу шаблона.
     *
     * @param int $indent количество символов отступа для строк после первой
     * @param string $char символ отступа
     * @return string
     */
    public function render(int $indent = 4, string $char = ' '): string
    {
        $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $data = $this->toArray();
        $out = [];

        if ($data['title'] !== '') {
            $out[] = '<title>' . $e($data['title']) . '</title>';
        }

        foreach ([ 'description', 'keywords', 'robots', 'author' ] as $name) {
            if ($data[$name] !== '') {
                $out[] = '<meta name="' . $name . '" content="' . $e($data[$name]) . '">';
            }
        }

        if ($data['canonical'] !== '') {
            $out[] = '<link rel="canonical" href="' . $e($data['canonical']) . '">';
        }

        foreach ($data['og'] as $prop => $value) {
            if ($prop === 'locale_alternates') {
                foreach ($value as $locale) {
                    if ($locale !== '') {
                        $out[] = '<meta property="og:locale:alternate" content="' . $e($locale) . '">';
                    }
                }
                continue;
            }

            if ($value !== '') {
                $out[] = '<meta property="og:' . $prop . '" content="' . $e($value) . '">';
            }
        }

        foreach ($data['twitter'] as $prop => $value) {
            if ($value !== '') {
                $out[] = '<meta name="twitter:' . $prop . '" content="' . $e($value) . '">';
            }
        }

        foreach ($data['custom'] as $tag) {
            $out[] = '<meta name="' . $e($tag['name']) . '" content="' . $e($tag['content']) . '">';
        }

        $first = \array_shift($out);
        $prefix = \str_repeat($char, $indent);

        if ($out === []) {
            return $first;
        }

        return $first . "\n" . \implode("\n", \array_map(
                static fn(string $line): string => $prefix . $line,
                $out
            ));
    }
}
