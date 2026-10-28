<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Database\Seeders;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AnnouncementSeeder extends Seeder
{
    private const string ADMIN_GUARD = 'admin';

    /**
     * Seed the announcements once; a database that already has announcements is left alone.
     */
    public function run(): void
    {
        if (Announcement::query()->exists()) {
            return;
        }

        $author = $this->author();

        $this->announcementFactory($author)
            ->ofType(AnnouncementType::Warning)
            ->forAudience(AnnouncementAudience::All)
            ->createOne([
                'title' => 'Mantenimiento programado del sitio',
                'body' => '<p>El <strong>sábado a las 22:00</strong> haremos un mantenimiento de dos horas. Guarda tus cambios antes de esa hora.</p>',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(4),
            ]);

        $this->announcementFactory($author)
            ->ofType(AnnouncementType::Critical)
            ->forAudience(AnnouncementAudience::WithoutTwoFactor)
            ->createOne([
                'title' => 'Activa la verificación en dos pasos',
                'body' => '<p>Protege tu cuenta con un segundo factor: aunque alguien conozca tu contraseña, no podrá entrar sin tu teléfono.</p>',
                'starts_at' => now()->subDays(3),
                'ends_at' => null,
                'button_label' => 'Activar ahora',
                'button_url' => '/setting/two-factor-authentication',
            ]);

        $this->announcementFactory($author)
            ->ofType(AnnouncementType::Info)
            ->forAudience(AnnouncementAudience::Authenticated)
            ->notDismissible()
            ->createOne([
                'title' => 'Nuevas reglas de la comunidad',
                'body' => '<p>Actualizamos las reglas de la comunidad. Los cambios principales son:</p><ul><li>Más claridad sobre el contenido permitido.</li><li>Nuevos motivos de suspensión.</li></ul>',
                'starts_at' => now()->subHours(6),
                'ends_at' => now()->addWeek(),
            ]);

        $this->announcementFactory($author)
            ->ofType(AnnouncementType::Info)
            ->forAudience(AnnouncementAudience::All)
            ->createOne([
                'title' => 'Llegan las publicaciones con varias imágenes',
                'body' => '<p>Muy pronto podrás adjuntar <em>varias imágenes</em> en una misma publicación.</p>',
                'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(9),
            ]);

        $this->announcementFactory($author)
            ->ofType(AnnouncementType::Warning)
            ->forAudience(AnnouncementAudience::All)
            ->createOne([
                'title' => 'Interrupción del servicio de correo',
                'body' => '<p>Los correos de verificación tardaron más de lo normal. El problema ya está resuelto.</p>',
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->subDays(8),
            ]);
    }

    /**
     * The seeded test user when it is an administrator, otherwise any administrator, otherwise nobody
     * (also when the authorization catalog was not seeded).
     */
    private function author(): ?User
    {
        $adminRoleExists = Role::query()
            ->where('name', AdminRole::Admin->value)
            ->where('guard_name', self::ADMIN_GUARD)
            ->exists();

        if (! $adminRoleExists) {
            return null;
        }

        $administrators = User::query()->role(AdminRole::Admin->value, self::ADMIN_GUARD);

        return (clone $administrators)->where('email', UserSeeder::TEST_USER_EMAIL)->first()
            ?? $administrators->first();
    }

    /**
     * The announcement factory for the given author, or without author when no administrator exists.
     */
    private function announcementFactory(?User $user): AnnouncementFactory
    {
        /** @var AnnouncementFactory $factory */
        $factory = Announcement::factory();

        return $user instanceof User ? $factory->createdBy($user) : $factory->state(['created_by' => null]);
    }
}
