<?php
declare(strict_types=1);

namespace Ttp;

use Ttp\Repo\ContentRepo;

/** /feed.xml — RSS 2.0 of the 20 most recent published posts. */
final class Feed
{
    public const LIMIT = 20;

    public static function xml(): string
    {
        $c     = ttp_config();
        $posts = ContentRepo::published('post', self::LIMIT);

        $built = $posts === [] ? gmdate('r') : self::rfc822((string) $posts[0]['published_at']);

        $out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"'
              . ' xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $out .= "  <channel>\n";
        $out .= '    <title>' . Str::e($c['site_name']) . "</title>\n";
        $out .= '    <link>' . Str::e(Seo::url('/')) . "</link>\n";
        $out .= '    <description>' . Str::e($c['tagline']) . "</description>\n";
        $out .= "    <language>en</language>\n";
        $out .= '    <lastBuildDate>' . $built . "</lastBuildDate>\n";
        $out .= '    <atom:link href="' . Str::e(Seo::url('/feed.xml'))
              . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($posts as $post) {
            $url         = Seo::url((string) $post['path']);
            $description = (string) $post['excerpt'];
            if (trim($description) === '') {
                $description = Markdown::truncate(Markdown::toText((string) $post['body_md']), 300);
            }

            $out .= "    <item>\n";
            $out .= '      <title>' . Str::e((string) $post['title']) . "</title>\n";
            $out .= '      <link>' . Str::e($url) . "</link>\n";
            $out .= '      <guid isPermaLink="true">' . Str::e($url) . "</guid>\n";
            $out .= '      <pubDate>' . self::rfc822((string) $post['published_at']) . "</pubDate>\n";
            $out .= '      <description>' . Str::e($description) . "</description>\n";
            $out .= '      <content:encoded><![CDATA[' . self::cdataSafe((string) $post['body_html'])
                  . "]]></content:encoded>\n";
            $out .= "    </item>\n";
        }

        return $out . "  </channel>\n</rss>\n";
    }

    private static function rfc822(string $iso): string
    {
        $ts = strtotime($iso);
        return gmdate('r', $ts === false ? time() : $ts);
    }

    private static function cdataSafe(string $html): string
    {
        return str_replace(']]>', ']]&gt;', $html);
    }
}
