<?php

namespace Tests\Unit;

use App\Services\NameNormalizationService;
use Tests\TestCase;

class NameNormalizationServiceTest extends TestCase
{
    private NameNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NameNormalizationService();
    }

    public function test_normalizes_single_abd_names(): void
    {
        $this->assertSame('عبدالله', $this->service->normalizeCompoundAbdName('عبد الله'));
        $this->assertSame('عبدالرحمن', $this->service->normalizeCompoundAbdName('عبد الرحمن'));
        $this->assertSame('عبدالعزيز', $this->service->normalizeCompoundAbdName('عبد العزيز'));
        $this->assertSame('عبدالحميد', $this->service->normalizeCompoundAbdName('عبد الحميد'));
        $this->assertSame('عبدالمجيد', $this->service->normalizeCompoundAbdName('عبد المجيد'));
        $this->assertSame('عبدالإله', $this->service->normalizeCompoundAbdName('عبد الإله'));
        $this->assertSame('عبدالاله', $this->service->normalizeCompoundAbdName('عبد الاله'));
        $this->assertSame('عبدالرب', $this->service->normalizeCompoundAbdName('عبد الرب'));
        $this->assertSame('عبدالوهاب', $this->service->normalizeCompoundAbdName('عبد الوهاب'));
    }

    public function test_preserves_spaces_between_regular_lineage_names(): void
    {
        // Lineage with "بن" / "بنت"
        $this->assertSame(
            'لولوة بنت عبدالرحمن بن عقيل',
            $this->service->normalizeCompoundAbdName('لولوة بنت عبد الرحمن بن عقيل')
        );

        // Multiple compound names in lineage
        $this->assertSame(
            'عبدالله محمد عبدالله البصري',
            $this->service->normalizeCompoundAbdName('عبد الله محمد عبد الله البصري')
        );

        $this->assertSame(
            'عبدالعزيز عبدالرحمن عبدالعزيز المنصور',
            $this->service->normalizeCompoundAbdName('عبد العزيز عبد الرحمن عبدالعزيز المنصور')
        );

        // Regular lineage without Abd names must remain completely untouched
        $regularName = 'يوسف حمد يوسف مروان';
        $this->assertSame($regularName, $this->service->normalizeCompoundAbdName($regularName));
        $this->assertFalse($this->service->hasCompoundAbdNameSpacing($regularName));

        $anotherName = 'محمد إبراهيم ناصر السريع';
        $this->assertSame($anotherName, $this->service->normalizeCompoundAbdName($anotherName));
        $this->assertFalse($this->service->hasCompoundAbdNameSpacing($anotherName));
    }

    public function test_handles_null_and_empty_strings(): void
    {
        $this->assertNull($this->service->normalizeCompoundAbdName(null));
        $this->assertSame('', $this->service->normalizeCompoundAbdName(''));
        $this->assertSame('   ', $this->service->normalizeCompoundAbdName('   '));
        $this->assertFalse($this->service->hasCompoundAbdNameSpacing(null));
        $this->assertFalse($this->service->hasCompoundAbdNameSpacing(''));
    }

    public function test_already_normalized_names_remain_unchanged(): void
    {
        $alreadyNormalized = 'عبدالله بن عبدالعزيز بن عبدالرحمن';
        $this->assertSame($alreadyNormalized, $this->service->normalizeCompoundAbdName($alreadyNormalized));
        $this->assertFalse($this->service->hasCompoundAbdNameSpacing($alreadyNormalized));
    }
}
