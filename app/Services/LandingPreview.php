<?php

namespace App\Services;

class LandingPreview
{
    public const PAGES = ['home'=>'Beranda', 'about'=>'Tentang Kami', 'program'=>'Program',
        'facilities'=>'Fasilitas', 'teachers'=>'Guru & Staf', 'gallery'=>'Berita & Galeri', 'contact'=>'Kontak'];

    public static function url(string $page, mixed $parameters = null): string
    {
        // Public actions are deliberately inert inside the authenticated preview.
        if (!isset(self::PAGES[$page]) && !in_array($page, ['privacy','terms'], true)) return '#';
        return '/admin/website/preview/frame?'.http_build_query(['page'=>$page, 'edit'=>request()->boolean('edit') ? 1 : 0, 'state'=>request('state') === 'published' ? 'published' : 'draft']);
    }

    public static function asset(string $path): string
    {
        $safe = WebsiteContent::image($path);
        return str_starts_with($safe, '/assets/') ? '/landing-preview'.$safe : $safe;
    }

    public static function render(array $content, string $page, bool $editing = false)
    {
        abort_unless(isset(self::PAGES[$page]) || in_array($page, ['privacy','terms'], true), 404);
        $site = self::images($content);
        $section = in_array($page, ['privacy','terms'], true) ? 'settings' : $page;
        $blocks = [];
        if ($editing) {
            foreach (WebsiteEditor::blocks($section, $content[$section] ?? []) as $id => $block) {
                $blocks[] = ['section'=>$section, 'block'=>$id, 'label'=>$block['label'], 'selector'=>$block['selector']];
            }
            if ($page === 'home') foreach (WebsiteEditor::blocks('settings', $content['settings'] ?? []) as $id => $block) {
                if ($id !== 'legal') $blocks[] = ['section'=>'settings', 'block'=>$id, 'label'=>$block['label'], 'selector'=>$block['selector']];
            }
        }
        return view('website.site.pages.'.$page, ['site'=>$site, 'page'=>$page, 'previewBlocks'=>$blocks]);
    }

    private static function images(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) $value = self::images($value);
            elseif (is_string($value) && WebsiteContent::image($value) !== '') $value = self::asset($value);
        }
        return $data;
    }
}
