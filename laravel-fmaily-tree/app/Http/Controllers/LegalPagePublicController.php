<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LegalPagePublicController extends Controller
{
    /**
     * Display the public Privacy Policy page.
     */
    public function privacyPolicy(): View
    {
        $defaults = config('legal_pages.privacy');

        return $this->renderLegalPage(
            type: 'privacy',
            enabledKey: 'page_privacy_enabled',
            titleKey: 'page_privacy_title_ar',
            contentKey: 'page_privacy_content_ar',
            defaultTitle: $defaults['title'],
            defaultContent: $defaults['content'],
            lastUpdated: SystemSetting::get('page_privacy_last_updated', $defaults['last_updated']),
        );
    }

    /**
     * Display the public Terms of Service page.
     */
    public function termsOfService(): View
    {
        return $this->renderLegalPage(
            type: 'terms',
            enabledKey: 'page_terms_enabled',
            titleKey: 'page_terms_title_ar',
            contentKey: 'page_terms_content_ar',
            defaultTitle: 'الشروط والأحكام',
            defaultContent: 'يخضع استخدام المنصة للشروط والأحكام الأسرية.',
        );
    }

    /**
     * Display the public About Us page.
     */
    public function aboutUs(): View
    {
        return $this->renderLegalPage(
            type: 'about',
            enabledKey: 'page_about_enabled',
            titleKey: 'page_about_title_ar',
            contentKey: 'page_about_content_ar',
            defaultTitle: 'من نحن',
            defaultContent: 'بوابة وتطبيق عائلة السريّع.',
        );
    }

    /**
     * Build the shared legal page view data.
     */
    private function renderLegalPage(
        string $type,
        string $enabledKey,
        string $titleKey,
        string $contentKey,
        string $defaultTitle,
        string $defaultContent,
        ?string $lastUpdated = null,
    ): View {
        $content = trim((string) SystemSetting::get($contentKey, $defaultContent));

        if ($content === '') {
            $content = $defaultContent;
        }

        [$contentHtml, $sections] = $this->prepareContent($content);

        return view('web-site.pages.legal.show', [
            'type' => $type,
            'enabled' => (bool) SystemSetting::get($enabledKey, true),
            'title' => SystemSetting::get($titleKey, $defaultTitle),
            'content' => $content,
            'contentHtml' => $contentHtml,
            'sections' => $sections,
            'lastUpdated' => $lastUpdated,
            'siteName' => SystemSetting::get('site_name_ar', 'عائلة السريّع'),
            'supportEmail' => SystemSetting::get('support_email') ?: 'mohammed.hamad1391969@gmail.com',
        ]);
    }

    /**
     * Convert the stored Markdown into safe HTML and collect its main sections.
     *
     * @return array{0: string, 1: array<int, array{id: string, title: string}>}
     */
    private function prepareContent(string $content): array
    {
        $html = Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $sections = [];

        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/u', function (array $matches) use (&$sections): string {
            $id = 'section-' . (count($sections) + 1);

            $sections[] = [
                'id' => $id,
                'title' => trim(strip_tags($matches[1])),
            ];

            return '<h2 id="' . $id . '">' . $matches[1] . '</h2>';
        }, $html) ?? $html;

        return [$html, $sections];
    }
}
