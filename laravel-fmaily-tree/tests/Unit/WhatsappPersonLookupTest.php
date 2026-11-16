<?php

namespace Tests\Unit;

use App\Services\Auth\WhatsappPersonLookup;
use PHPUnit\Framework\TestCase;

class WhatsappPersonLookupTest extends TestCase
{
    private WhatsappPersonLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lookup = new WhatsappPersonLookup();
    }

    public function test_normalizes_saudi_phone_numbers(): void
    {
        $this->assertSame('966556641234', $this->lookup->canonicalMobile('556641234'));
        $this->assertSame('966556641234', $this->lookup->canonicalMobile('0556641234'));
        $this->assertSame('966556641234', $this->lookup->canonicalMobile('+966556641234'));
        $this->assertSame('966556641234', $this->lookup->canonicalMobile('+966 055 664 1234'));
        $this->assertSame('966556641234', $this->lookup->canonicalMobile('٠٠٩٦٦٥٥٦٦٤١٢٣٤'));
    }

    public function test_normalizes_egyptian_phone_numbers_including_015_prefix(): void
    {
        $this->assertSame('201012345678', $this->lookup->canonicalMobile('+201012345678'));
        $this->assertSame('201012345678', $this->lookup->canonicalMobile('+2001012345678'));
        $this->assertSame('201012345678', $this->lookup->canonicalMobile('01012345678'));
        // 015 numbers have last 9 digits starting with 5 — must remain Egyptian (20...) not Saudi (966...)
        $this->assertSame('201551234567', $this->lookup->canonicalMobile('+201551234567'));
        $this->assertSame('201551234567', $this->lookup->canonicalMobile('01551234567'));
    }

    public function test_normalizes_uae_and_international_numbers_starting_with_5(): void
    {
        // UAE mobile numbers start with 5 after 971 — must remain 9715...
        $this->assertSame('971501234567', $this->lookup->canonicalMobile('+971501234567'));
        $this->assertSame('971501234567', $this->lookup->canonicalMobile('+9710501234567'));

        // Palestine mobile numbers start with 59 after 970 — must remain 97059...
        $this->assertSame('970591234567', $this->lookup->canonicalMobile('+970591234567'));

        // Kuwait & Jordan
        $this->assertSame('96591234567', $this->lookup->canonicalMobile('+96591234567'));
        $this->assertSame('962791234567', $this->lookup->canonicalMobile('+9620791234567'));
    }
}
