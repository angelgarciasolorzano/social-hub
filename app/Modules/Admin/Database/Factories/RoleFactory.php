<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Factories;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Role>
     */
    #[Override]
    protected $model = Role::class;

    /**
     * Define the model's default state: a custom role of the `admin` guard.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<Role>, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->jobTitle();

        return [
            'name' => Role::CUSTOM_NAME_PREFIX.Str::slug($label),
            'guard_name' => Role::GUARD_NAME,
            'is_system' => false,
            'label' => $label,
            'description' => fake()->sentence(),
        ];
    }

    /**
     * A role of the catalog, which reads its name and description from `lang/`.
     */
    public function system(AdminRole $adminRole): static
    {
        return $this->state([
            'name' => $adminRole->value,
            'is_system' => true,
            'label' => null,
            'description' => null,
        ]);
    }

    /**
     * A custom role saved without a label, shown by its stored name.
     */
    public function withoutLabel(): static
    {
        return $this->state([
            'label' => null,
        ]);
    }
}
