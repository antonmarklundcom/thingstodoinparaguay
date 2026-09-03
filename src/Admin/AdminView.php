<?php
declare(strict_types=1);

namespace Ttp\Admin;

use Ttp\Str;

/**
 * Renders templates/admin/*.php inside templates/admin/layout.php.
 *
 * Deliberately separate from Ttp\View: the panel has its own shell, its own CSS
 * and no SEO head, and phase S3 re-skins the public templates without touching
 * anything here (plan §5.2, "Admin CSS may be simple but must be usable").
 */
final class AdminView
{
    public static function dir(): string
    {
        return ttp_root() . '/templates/admin';
    }

    /** @param array<string,mixed> $vars */
    public static function render(string $template, array $vars = []): string
    {
        $vars += [
            'title'    => 'Admin',
            'user'     => Auth::user(),
            'flash'    => Flash::take(),
            'csrf'     => Csrf::token(),
            'nav'      => self::nav($template),
            'template' => $template,
        ];
        $vars['content'] = self::capture($template, $vars);
        return self::capture('layout', $vars);
    }

    /** @param array<string,mixed> $vars */
    public static function partial(string $name, array $vars = []): string
    {
        return self::capture('partials/' . $name, $vars);
    }

    /** @param array<string,mixed> $vars */
    private static function capture(string $template, array $vars): string
    {
        $file = self::dir() . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("admin template not found: {$template}");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /** @return array<int,array{label:string,path:string,current:bool}> */
    private static function nav(string $template): array
    {
        $groups = [
            'dashboard'  => ['Dashboard',   '/admin/'],
            'content'    => ['Content',     '/admin/content/'],
            'media'      => ['Media',       '/admin/media/'],
            'categories' => ['Categories',  '/admin/categories/'],
            'tags'       => ['Tags',        '/admin/tags/'],
            'redirects'  => ['Redirects',   '/admin/redirects/'],
            'leads'      => ['Leads',       '/admin/leads/'],
            'subscribers'=> ['Subscribers', '/admin/subscribers/'],
            'settings'   => ['Settings',    '/admin/settings/'],
        ];
        $active = explode('/', $template)[0];
        if ($active === 'editor') {
            $active = 'content';
        }

        $out = [];
        foreach ($groups as $key => [$label, $path]) {
            $out[] = ['label' => $label, 'path' => $path, 'current' => $key === $active];
        }
        return $out;
    }

    public static function e(?string $value): string
    {
        return Str::e($value);
    }

    /** "3 September 2026, 14:05" — the panel always shows UTC, and says so. */
    public static function dateTime(?string $iso): string
    {
        if ($iso === null || trim($iso) === '') {
            return '—';
        }
        $ts = strtotime($iso);
        return $ts === false ? '—' : gmdate('j M Y, H:i', $ts);
    }

    /** The value for an <input type="datetime-local">. */
    public static function dateTimeLocal(?string $iso): string
    {
        if ($iso === null || trim($iso) === '') {
            return '';
        }
        $ts = strtotime($iso);
        return $ts === false ? '' : gmdate('Y-m-d\TH:i', $ts);
    }
}
