<?php

namespace App\Services\AI\Content;

use App\Models\Person;

class PersonContentBuilder
{
    public function build(Person $person): string
    {
        $person->loadMissing(['parent', 'mother', 'location', 'ownedStories', 'articles']);

        $parts = [
            'شخص: '.$person->full_name,
            'الاسم الأول: '.$person->first_name,
            'الجنس: '.($person->gender === 'female' ? 'أنثى' : 'ذكر'),
        ];

        if ($person->birth_date && $person->gender !== 'female') {
            $parts[] = 'تاريخ الميلاد: '.$person->birth_date->format('Y-m-d');
        }
        if ($person->birth_place) {
            $parts[] = 'مكان الميلاد: '.$person->birth_place;
        }
        if ($person->death_date) {
            $parts[] = 'تاريخ الوفاة: '.$person->death_date->format('Y-m-d');
        }
        if ($person->death_place) {
            $parts[] = 'مكان الوفاة: '.$person->death_place;
        }
        if ($person->occupation) {
            $parts[] = 'المهنة: '.$person->occupation;
        }
        if ($person->location) {
            $parts[] = 'المكان: '.$person->location->name;
        }
        if ($person->parent) {
            $parts[] = 'الأب: '.$person->parent->full_name;
        }
        if ($person->mother) {
            $parts[] = 'الأم: '.$person->mother->full_name;
        }
        if (filled($person->biography)) {
            $parts[] = 'السيرة: '.strip_tags($person->biography);
        }

        $storyTitles = $person->ownedStories->pluck('title')->filter()->take(10)->implode('، ');
        if ($storyTitles !== '') {
            $parts[] = 'قصص مرتبطة: '.$storyTitles;
        }

        return implode("\n", $parts);
    }
}
