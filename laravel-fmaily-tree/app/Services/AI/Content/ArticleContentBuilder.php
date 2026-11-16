<?php

namespace App\Services\AI\Content;

use App\Models\Article;

class ArticleContentBuilder
{
    public function build(Article $article): string
    {
        $article->loadMissing(['person', 'category']);

        $parts = [
            'مقال: '.$article->title,
        ];

        if ($article->person) {
            $parts[] = 'الشخص: '.$article->person->full_name;
        }
        if ($article->category) {
            $parts[] = 'التصنيف: '.$article->category->name;
        }
        if (filled($article->content)) {
            $parts[] = 'المحتوى: '.mb_substr(strip_tags($article->content), 0, 4000);
        }

        return implode("\n", $parts);
    }
}
