<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EquipmentImageUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        $campus = Campus::create(['campus_name' => 'Main Campus']);
        $location = Location::create(['campus_id' => $campus->id, 'location_name' => 'Lab']);

        return array_merge([
            'name' => 'Test Equipment',
            'condition' => 'good',
            'inventory' => [[
                'campus_id' => $campus->id,
                'location_id' => $location->id,
                'total_quantity' => 1,
                'reorder_level' => 1,
            ]],
        ], $overrides);
    }

    public function test_svg_upload_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // A real-shaped malicious SVG payload with an embedded script.
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.cookie)"><script>alert(1)</script></svg>';
        $file = UploadedFile::fake()->createWithContent('malicious.svg', $svgContent);

        $response = $this->actingAs($admin)->post('/admin/equipment', $this->validPayload(['image' => $file]));

        $response->assertSessionHasErrors('image');
        Storage::disk('public')->assertMissing('equipment/malicious.svg');
    }

    public function test_legitimate_png_upload_still_succeeds(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Avoids UploadedFile::fake()->image(), which needs the GD extension
        // just to generate pixel data — irrelevant to what this test checks
        // (that a real raster mime type passes validation). fake()->create()
        // is Laravel's documented way to test mime-based rules without it.
        $file = UploadedFile::fake()->create('photo.png', 100, 'image/png');

        $response = $this->actingAs($admin)->post('/admin/equipment', $this->validPayload(['image' => $file]));
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('equipment', ['name' => 'Test Equipment']);
    }
}
