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

    /**
     * The Edit form used a multipart PUT, which PHP can't read files from,
     * so photos were silently dropped. It now POSTs with _method=put — this
     * mirrors that request exactly.
     */
    public function test_editing_equipment_saves_a_phone_size_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $equipment = \App\Models\Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker (250 mL)', 'condition' => 'good', 'is_available' => true]);

        $photo = UploadedFile::fake()->create('IMG_2045.jpg', 5 * 1024, 'image/jpeg'); // 5 MB

        $this->actingAs($admin)->post("/admin/equipment/{$equipment->id}", [
            '_method' => 'put',
            'name' => 'Beaker (250 mL)',
            'condition' => 'good',
            'image' => $photo,
        ])->assertSessionDoesntHaveErrors();

        $path = $equipment->fresh()->image_path;
        $this->assertNotNull($path, 'The photo is saved on the equipment.');
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_photo_over_10mb_is_rejected_with_a_message(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $equipment = \App\Models\Equipment::create(['equipment_code' => 'EQP-1', 'name' => 'Beaker', 'condition' => 'good', 'is_available' => true]);

        $this->actingAs($admin)->post("/admin/equipment/{$equipment->id}", [
            '_method' => 'put', 'name' => 'Beaker', 'condition' => 'good',
            'image' => UploadedFile::fake()->create('huge.jpg', 10241, 'image/jpeg'),
        ])->assertSessionHasErrors('image');

        $this->assertNull($equipment->fresh()->image_path);
    }

    public function test_creating_equipment_accepts_a_phone_size_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/equipment', $this->validPayload([
            'image' => UploadedFile::fake()->create('IMG_2046.jpg', 5 * 1024, 'image/jpeg'),
        ]))->assertSessionDoesntHaveErrors();

        $this->assertNotNull(\App\Models\Equipment::where('name', 'Test Equipment')->value('image_path'));
    }
}
