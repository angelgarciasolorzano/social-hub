<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Database\Seeders;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Announcement\Database\Factories\AnnouncementFactory;
use App\Modules\Announcement\Enums\AnnouncementAudience;
use App\Modules\Announcement\Enums\AnnouncementDisplay;
use App\Modules\Announcement\Enums\AnnouncementType;
use App\Modules\Announcement\Models\Announcement;
use App\Support\Seeding\WeightedRandom;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Sample announcements with a history over the last days: five fixed ones that cover every case of the banner,
 * then one per sample text with a random schedule (running, scheduled, ended or unpublished), some of them edited,
 * and a few duplicates made the way AnnouncementService makes them. It writes through the factory, never through
 * the service, so it records no activity; ActivityLogSeeder derives the audit from these rows.
 */
class AnnouncementSeeder extends Seeder
{
    /**
     * The title of the fixed announcement for users without two-factor authentication.
     */
    public const string TWO_FACTOR_TITLE = 'Activa la verificación en dos pasos';

    private const string ADMIN_GUARD = 'admin';

    private const int HISTORY_DAYS = 55;

    private const int DUPLICATE_COUNT = 3;

    private const int EDITED_SHARE_PERCENT = 30;

    /**
     * How often each schedule is drawn for a sample announcement.
     */
    private const array SCHEDULE_WEIGHTS = [
        'running' => 30,
        'scheduled' => 20,
        'ended' => 35,
        'unpublished' => 15,
    ];

    /**
     * One entry per sample announcement: title, body, type, audience and an optional button.
     *
     * @var list<array{title: string, body: string, type: string, audience: string, button?: array{label: string, url: string}}>
     */
    private const array SAMPLE_ANNOUNCEMENTS = [
        ['title' => 'Revisa tus dispositivos de confianza', 'body' => '<p>Te recomendamos revisar los dispositivos que recuerdan tu sesión y revocar los que ya no uses.</p>', 'type' => 'info', 'audience' => 'authenticated', 'button' => ['label' => 'Ver dispositivos', 'url' => '/setting/trusted-devices']],
        ['title' => 'Actualiza tu perfil', 'body' => '<p>Agrega una biografía y una foto para que tus amigos te reconozcan.</p>', 'type' => 'info', 'audience' => 'authenticated', 'button' => ['label' => 'Editar perfil', 'url' => '/setting/profile']],
        ['title' => 'Nuevo modo oscuro', 'body' => '<p>Ya puedes elegir el <strong>modo oscuro</strong> desde las preferencias de apariencia.</p>', 'type' => 'info', 'audience' => 'all', 'button' => ['label' => 'Probarlo', 'url' => '/setting/appearance']],
        ['title' => 'Lentitud al cargar imágenes', 'body' => '<p>Algunas imágenes tardan más de lo normal en cargar. Estamos trabajando en ello.</p>', 'type' => 'warning', 'audience' => 'all'],
        ['title' => 'Intentos de phishing', 'body' => '<p>Nunca te pediremos tu contraseña por correo. Si recibes un mensaje así, <em>no respondas</em>.</p>', 'type' => 'critical', 'audience' => 'all'],
        ['title' => 'Cambios en la política de privacidad', 'body' => '<p>Actualizamos la política de privacidad para explicar mejor qué datos guardamos y por cuánto tiempo.</p>', 'type' => 'info', 'audience' => 'all', 'button' => ['label' => 'Leer la política', 'url' => 'https://example.com/privacidad']],
        ['title' => 'Comentarios temporalmente desactivados', 'body' => '<p>Durante la actualización no se podrán publicar comentarios nuevos.</p>', 'type' => 'warning', 'audience' => 'authenticated'],
        ['title' => 'Bienvenida a la nueva versión', 'body' => '<p>Renovamos el inicio para que encuentres antes las publicaciones de tus amigos.</p>', 'type' => 'info', 'audience' => 'all'],
        ['title' => 'Protege tu cuenta', 'body' => '<p>Las cuentas sin verificación en dos pasos son las más expuestas a accesos no autorizados.</p>', 'type' => 'warning', 'audience' => 'without_two_factor', 'button' => ['label' => 'Configurar', 'url' => '/setting/two-factor-authentication']],
        ['title' => 'Mantenimiento de la base de datos', 'body' => '<p>El domingo de madrugada el sitio estará en modo de solo lectura durante una hora.</p>', 'type' => 'warning', 'audience' => 'all'],
        ['title' => 'Incidente de seguridad resuelto', 'body' => '<p>Detectamos y corregimos un fallo que afectaba a los enlaces de recuperación de contraseña.</p>', 'type' => 'critical', 'audience' => 'authenticated'],
        ['title' => 'Encuesta de satisfacción', 'body' => '<p>Cuéntanos qué te gustaría mejorar. Solo te tomará dos minutos.</p>', 'type' => 'info', 'audience' => 'authenticated', 'button' => ['label' => 'Responder', 'url' => 'https://example.com/encuesta']],
        ['title' => 'Límite de publicaciones por día', 'body' => '<p>Para frenar el spam, cada cuenta podrá publicar como máximo <strong>50 veces al día</strong>.</p>', 'type' => 'warning', 'audience' => 'authenticated'],
        ['title' => 'Navegadores antiguos', 'body' => '<p>Dejaremos de dar soporte a navegadores de más de dos años. Actualiza el tuyo para seguir usando el sitio.</p>', 'type' => 'info', 'audience' => 'all'],
        ['title' => 'Corte de servicio programado', 'body' => '<p>Nuestro proveedor hará trabajos en la red. Puede haber cortes breves durante la madrugada.</p>', 'type' => 'critical', 'audience' => 'all'],
    ];

    /**
     * Seed the announcements once; a database that already has announcements is left alone.
     */
    public function run(): void
    {
        if (Announcement::query()->exists()) {
            return;
        }

        $administrators = $this->administrators();

        $this->seedFixedAnnouncements($administrators);

        $sampleAnnouncements = [];

        foreach (collect(self::SAMPLE_ANNOUNCEMENTS)->shuffle() as $sampleAnnouncement) {
            $sampleAnnouncements[] = $this->seedSampleAnnouncement($sampleAnnouncement, $administrators);
        }

        $this->seedDuplicates($sampleAnnouncements, $administrators);
    }

    /**
     * Five fixed announcements, so the banner always has a running warning for everyone, a critical one for users
     * without two-factor authentication with a button, one that cannot be dismissed, a scheduled one and an expired one.
     *
     * @param  Collection<int, User>  $administrators
     */
    private function seedFixedAnnouncements(Collection $administrators): void
    {
        $now = CarbonImmutable::now();

        $this->announcementFactory($administrators)
            ->ofType(AnnouncementType::Warning)
            ->forAudience(AnnouncementAudience::All)
            ->createOne([
                'title' => 'Mantenimiento programado del sitio',
                'body' => '<p>El <strong>sábado a las 22:00</strong> haremos un mantenimiento de dos horas. Guarda tus cambios antes de esa hora.</p>',
                ...$this->dates($now->subDays(2), $now->subDay(), $now->addDays(4)),
            ]);

        $this->announcementFactory($administrators)
            ->ofType(AnnouncementType::Critical)
            ->forAudience(AnnouncementAudience::WithoutTwoFactor)
            ->asDialog()
            ->createOne([
                'title' => self::TWO_FACTOR_TITLE,
                'body' => '<p>Protege tu cuenta con un segundo factor: aunque alguien conozca tu contraseña, no podrá entrar sin tu teléfono.</p>',
                'button_label' => 'Activar ahora',
                'button_url' => '/setting/two-factor-authentication',
                ...$this->dates($now->subDays(4), $now->subDays(3), null),
            ]);

        $this->announcementFactory($administrators)
            ->ofType(AnnouncementType::Info)
            ->forAudience(AnnouncementAudience::Authenticated)
            ->notDismissible()
            ->createOne([
                'title' => 'Nuevas reglas de la comunidad',
                'body' => '<p>Actualizamos las reglas de la comunidad. Los cambios principales son:</p><ul><li>Más claridad sobre el contenido permitido.</li><li>Nuevos motivos de suspensión.</li></ul>',
                ...$this->dates($now->subDay(), $now->subHours(6), $now->addWeek()),
            ]);

        $this->announcementFactory($administrators)
            ->ofType(AnnouncementType::Info)
            ->forAudience(AnnouncementAudience::All)
            ->createOne([
                'title' => 'Llegan las publicaciones con varias imágenes',
                'body' => '<p>Muy pronto podrás adjuntar <em>varias imágenes</em> en una misma publicación.</p>',
                ...$this->dates($now->subDay(), $now->addDays(2), $now->addDays(9)),
            ]);

        $this->announcementFactory($administrators)
            ->ofType(AnnouncementType::Warning)
            ->forAudience(AnnouncementAudience::All)
            ->createOne([
                'title' => 'Interrupción del servicio de correo',
                'body' => '<p>Los correos de verificación tardaron más de lo normal. El problema ya está resuelto.</p>',
                ...$this->dates($now->subDays(11), $now->subDays(10), $now->subDays(8)),
            ]);
    }

    /**
     * One sample announcement with a random schedule, shown as a dialog when it is critical; an unpublished one keeps
     * `ends_at` equal to `updated_at`, as AnnouncementService::unpublish() leaves it, and some others were edited.
     *
     * @param  array{title: string, body: string, type: string, audience: string, button?: array{label: string, url: string}}  $sampleAnnouncement
     * @param  Collection<int, User>  $administrators
     */
    private function seedSampleAnnouncement(array $sampleAnnouncement, Collection $administrators): Announcement
    {
        $now = CarbonImmutable::now();
        $schedule = WeightedRandom::pick(self::SCHEDULE_WEIGHTS);

        [$createdAt, $startsAt, $endsAt] = match ($schedule) {
            'running' => $this->runningDates($now),
            'scheduled' => $this->scheduledDates($now),
            default => $this->endedDates($now),
        };

        $updatedAt = $createdAt;
        $announcementType = AnnouncementType::from($sampleAnnouncement['type']);

        if ($schedule === 'unpublished') {
            $endsAt = $startsAt->addHours(random_int(12, 5 * 24));
            $updatedAt = $endsAt;
        } elseif (WeightedRandom::chance(self::EDITED_SHARE_PERCENT)) {
            $updatedAt = $this->momentBetween($createdAt, $endsAt instanceof CarbonImmutable && $endsAt->isBefore($now) ? $endsAt->subHour() : $now);
        }

        return $this->announcementFactory($administrators)
            ->ofType($announcementType)
            ->forAudience(AnnouncementAudience::from($sampleAnnouncement['audience']))
            ->createOne([
                'display' => $announcementType === AnnouncementType::Critical ? AnnouncementDisplay::Dialog : AnnouncementDisplay::Toast,
                'title' => $sampleAnnouncement['title'],
                'body' => $sampleAnnouncement['body'],
                'button_label' => $sampleAnnouncement['button']['label'] ?? null,
                'button_url' => $sampleAnnouncement['button']['url'] ?? null,
                'is_dismissible' => WeightedRandom::chance(85),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
    }

    /**
     * Copy a few announcements as AnnouncementService::duplicate() does: the same content, starting the day after
     * the copy was made and without end date.
     *
     * @param  list<Announcement>  $sampleAnnouncements
     * @param  Collection<int, User>  $administrators
     */
    private function seedDuplicates(array $sampleAnnouncements, Collection $administrators): void
    {
        $now = CarbonImmutable::now();
        $sources = collect($sampleAnnouncements)
            ->filter(fn (Announcement $announcement): bool => $announcement->created_at?->isBefore($now->subDays(3)) ?? false)
            ->shuffle()
            ->take(self::DUPLICATE_COUNT);

        foreach ($sources as $source) {
            $copiedAt = $this->momentBetween($source->created_at ?? $now, $now->subDay());

            $this->announcementFactory($administrators)->createOne([
                ...$source->only(['title', 'body', 'type', 'audience', 'display', 'button_label', 'button_url', 'is_dismissible']),
                'starts_at' => $copiedAt->addDay(),
                'ends_at' => null,
                'created_at' => $copiedAt,
                'updated_at' => $copiedAt,
            ]);
        }
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable|null}
     */
    private function runningDates(CarbonImmutable $now): array
    {
        $createdAt = $now->subHours(random_int(2, 20 * 24));
        $startsAt = $this->momentBetween($createdAt, $now->subHour());

        return [$createdAt, $startsAt, WeightedRandom::chance(30) ? null : $now->addDays(random_int(2, 14))];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable|null}
     */
    private function scheduledDates(CarbonImmutable $now): array
    {
        $startsAt = $now->addDays(random_int(1, 14));

        return [$now->subHours(random_int(1, 5 * 24)), $startsAt, WeightedRandom::chance(20) ? null : $startsAt->addDays(random_int(2, 10))];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable}
     */
    private function endedDates(CarbonImmutable $now): array
    {
        $createdAt = $now->subDays(random_int(20, self::HISTORY_DAYS));
        $startsAt = $createdAt->addHours(random_int(1, 3 * 24));

        return [$createdAt, $startsAt, $startsAt->addDays(random_int(2, 10))];
    }

    /**
     * The `starts_at`, `ends_at`, `created_at` and `updated_at` of an announcement that was never edited.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, created_at: CarbonImmutable, updated_at: CarbonImmutable}
     */
    private function dates(CarbonImmutable $createdAt, CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): array
    {
        return ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'created_at' => $createdAt, 'updated_at' => $createdAt];
    }

    /**
     * A random moment between the two dates, or the first one when they are in the wrong order.
     */
    private function momentBetween(CarbonImmutable $earliest, CarbonImmutable $latest): CarbonImmutable
    {
        $minutes = (int) $earliest->diffInMinutes($latest, absolute: false);

        return $minutes <= 1 ? $earliest : $earliest->addMinutes(random_int(1, $minutes));
    }

    /**
     * The seeded administrators (none when the authorization catalog was not seeded).
     *
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        $adminRoleExists = Role::query()
            ->where('name', AdminRole::Admin->value)
            ->where('guard_name', self::ADMIN_GUARD)
            ->exists();

        if (! $adminRoleExists) {
            return new Collection;
        }

        return User::query()->role(AdminRole::Admin->value, self::ADMIN_GUARD)->get();
    }

    /**
     * The announcement factory credited to Angel when he is an administrator, otherwise to a random administrator,
     * otherwise to nobody.
     *
     * @param  Collection<int, User>  $administrators
     */
    private function announcementFactory(Collection $administrators): AnnouncementFactory
    {
        /** @var AnnouncementFactory $factory */
        $factory = Announcement::factory();

        $author = WeightedRandom::chance(50)
            ? $administrators->firstWhere('email', UserSeeder::TEST_USER_EMAIL)
            : null;
        $author ??= $administrators->isEmpty() ? null : $administrators->random();

        return $author instanceof User ? $factory->createdBy($author) : $factory->state(['created_by' => null]);
    }
}
