<?php

namespace Tests\Unit;

use App\Domain\Model\LicenseType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicenseTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_type_constants(): void
    {
        $this->assertSame('MONTHLY', LicenseType::MONTHLY);
        $this->assertSame('BIYEARLY', LicenseType::BIYEARLY);
        $this->assertSame('YEARLY', LicenseType::YEARLY);
        $this->assertSame('TRIAL', LicenseType::TRIAL);
    }

    public function test_can_create_monthly_license_type(): void
    {
        $id = Str::uuid()->toString();
        $licenseType = LicenseType::create([
            'id' => $id,
            'name' => LicenseType::MONTHLY,
            'number_of_days' => 30,
            'price' => 29.99,
            'active' => true,
        ]);

        $this->assertInstanceOf(LicenseType::class, $licenseType);
        $this->assertSame($id, $licenseType->id);
        $this->assertSame(LicenseType::MONTHLY, $licenseType->name);
        $this->assertSame(30, (int) $licenseType->number_of_days);
        $this->assertSame(29.99, (float) $licenseType->price);
        $this->assertTrue((bool) $licenseType->active);
        $this->assertFalse($licenseType->incrementing);
        $this->assertSame('string', $licenseType->getKeyType());
    }

    public function test_can_create_yearly_license_type(): void
    {
        $id = Str::uuid()->toString();
        $licenseType = LicenseType::create([
            'id' => $id,
            'name' => LicenseType::YEARLY,
            'number_of_days' => 365,
            'price' => 299.99,
            'active' => true,
        ]);

        $this->assertDatabaseHas('license_types', [
            'id' => $id,
            'name' => 'YEARLY',
            'number_of_days' => 365,
        ]);
    }

    public function test_can_create_trial_license_type(): void
    {
        $id = Str::uuid()->toString();
        $licenseType = LicenseType::create([
            'id' => $id,
            'name' => LicenseType::TRIAL,
            'number_of_days' => 14,
            'price' => 0.0,
            'active' => true,
        ]);

        $this->assertSame(0.0, (float) $licenseType->price);
        $this->assertSame(14, (int) $licenseType->number_of_days);
    }
}
