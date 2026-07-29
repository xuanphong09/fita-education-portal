<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\LecturerSlugService;

class LecturerSlugServiceTest extends TestCase
{
    protected LecturerSlugService $slugService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slugService = new LecturerSlugService();
    }

    public function test_generate_slug_simple_name_three_parts()
    {
        // Ngô Công Thắng → ncthang
        $slug = $this->slugService->generateSlug('Ngô Công Thắng');
        $this->assertEquals('ncthang', $slug);
    }

    public function test_generate_slug_simple_name_two_parts()
    {
        // Trần Anh → trananh
        $slug = $this->slugService->generateSlug('Trần Anh');
        $this->assertEquals('trananh', $slug);
    }

    public function test_generate_slug_with_extra_spaces()
    {
        // Ngô  Công  Thắng (with extra spaces) → ncthang
        $slug = $this->slugService->generateSlug('Ngô  Công  Thắng');
        $this->assertEquals('ncthang', $slug);
    }

    public function test_generate_slug_multiple_middle_names()
    {
        // Ngô Công Minh Thắng → ncmthang
        $slug = $this->slugService->generateSlug('Ngô Công Minh Thắng');
        $this->assertEquals('ncmthang', $slug);
    }

    public function test_generate_slug_removes_diacritics()
    {
        // Verify Vietnamese diacritics are removed
        $slug = $this->slugService->generateSlug('Ngô Công Thắng');
        $this->assertStringNotContainsString('ô', $slug);
        $this->assertStringNotContainsString('ắ', $slug);
    }

    public function test_generate_slug_is_lowercase()
    {
        $slug = $this->slugService->generateSlug('NGÔ CÔNG THẮNG');
        $this->assertEquals($slug, strtolower($slug));
    }

    public function test_generate_slug_with_accents()
    {
        // Trần Minh Hồng → tmh
        $slug = $this->slugService->generateSlug('Trần Minh Hồng');
        $this->assertEquals('tmh', $slug);
    }

    public function test_generate_slug_full_surname_with_accents()
    {
        // Lê Thị Nhuận → lthuan (l=Lê, t=Thị, nuan=Nhuận)
        $slug = $this->slugService->generateSlug('Lê Thị Nhuận');
        $this->assertEquals('lthnuan', $slug);
    }
}

