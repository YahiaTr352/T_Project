<?php

namespace Tests\Unit;

use App\Support\Arabic\TextNormalizer;
use PHPUnit\Framework\TestCase;

class ArabicTextNormalizerTest extends TestCase
{
    public function test_it_unifies_alef_forms(): void
    {
        $this->assertSame('احمد', TextNormalizer::normalize('أحمد'));
        $this->assertSame('احمد', TextNormalizer::normalize('إحمد'));
        $this->assertSame('احمد', TextNormalizer::normalize('آحمد'));
    }

    public function test_it_removes_tashkeel_and_tatweel(): void
    {
        $this->assertSame('محمد', TextNormalizer::normalize('مُحَمَّد'));
        $this->assertSame('لغه', TextNormalizer::normalize('لـغــة'));
    }

    public function test_it_normalizes_yaa_and_taa_marbuta(): void
    {
        $this->assertSame('علي', TextNormalizer::normalize('علي'));
        $this->assertSame('فاطمه', TextNormalizer::normalize('فاطمة'));
    }

    public function test_it_converts_arabic_indic_digits(): void
    {
        $this->assertSame('2026', TextNormalizer::normalize('٢٠٢٦'));
    }

    public function test_it_collapses_whitespace(): void
    {
        $this->assertSame('السلام عليكم', TextNormalizer::normalize('  السلام    عليكم  '));
    }

    public function test_it_handles_null_and_empty(): void
    {
        $this->assertSame('', TextNormalizer::normalize(null));
        $this->assertSame('', TextNormalizer::normalize(''));
    }

    public function test_compact_removes_separators_and_spaces(): void
    {
        $this->assertSame('ابج', TextNormalizer::compact('أ ب  ج'));
        $this->assertSame('ابج', TextNormalizer::compact('أ-ب-ج'));
    }

    public function test_contains_detects_substring_and_word_variants(): void
    {
        $this->assertTrue(TextNormalizer::contains('كلمة سيئة جدا', 'سيئة'));
        $this->assertFalse(TextNormalizer::contains('كلمة جيدة جدا', 'سيئة'));
    }

    public function test_contains_word_respects_word_boundaries_and_prefixes(): void
    {
        $this->assertTrue(TextNormalizer::containsWord('هذه كلمة سيئة', 'سيئة'));
        $this->assertTrue(TextNormalizer::containsWord('هذه كلمة سيئة', 'كلمة'));
        $this->assertTrue(TextNormalizer::containsWord('هذه والكلمة سيئة', 'كلمة'));
        $this->assertFalse(TextNormalizer::containsWord('هذه كلماتي', 'كلمة'));
    }

    public function test_words_splits_and_normalizes(): void
    {
        $this->assertSame(
            ['السلام', 'عليكم'],
            TextNormalizer::words('السَّلامُ عَلَيْكُمْ')
        );
    }
}
