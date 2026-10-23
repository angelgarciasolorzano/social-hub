<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Database\Seeders;

use App\Auth\Models\TrustedDeviceEvent;
use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAdminEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogAuthEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogContentEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogLikeEvent;
use App\Modules\ActivityLog\Enums\Events\ActivityLogTrustedDeviceEvent;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\Support\Seeding\SkewedRandom;
use App\Support\Seeding\WeightedRandom;
use App\User\Database\Seeders\UserSeeder;
use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Builds activity from the data the other seeders created, so every record points to
 * something real. Dates are spread over the last days because the content seeders
 * create everything at seeding time. It does nothing when activity already exists.
 */
class ActivityLogSeeder extends Seeder
{
    private const int SPREAD_DAYS = 60;

    private const int ROWS_PER_INSERT = 1000;

    private const int RECORDS_PER_CHUNK = 500;

    private const int MAX_LOGINS_PER_USER = 20;

    private const float LOGINS_SKEW = 2.0;

    private const int USER_AGENT_MAX_LENGTH = 512;

    /**
     * @var list<array<string, string|int|null>>
     */
    private array $pendingRows = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (ActivityLog::query()->exists()) {
            return;
        }

        $this->seedPostActivity();
        $this->seedCommentActivity();
        $this->seedLikeActivity();
        $this->seedTrustedDeviceActivity();
        $this->seedAuthActivity();
        $this->seedAdminActivity();
        $this->seedAccountStatusActivity();

        $this->flush();
    }

    private function seedPostActivity(): void
    {
        Post::query()->select(['id', 'user_id'])->chunkById(self::RECORDS_PER_CHUNK, function (Collection $posts): void {
            foreach ($posts as $post) {
                $this->queue(ActivityLogContentEvent::PostCreated, $post->user_id, Post::MORPH_NAME, $post->id, $this->randomPastDate(), web: true);
            }
        });
    }

    /**
     * A comment on a post or a reply, then its edit and its deletion when the seeded comment has them.
     */
    private function seedCommentActivity(): void
    {
        Comment::withTrashed()
            ->select(['id', 'user_id', 'commentable_type', 'commentable_id', 'edited_at', 'deleted_at'])
            ->chunkById(self::RECORDS_PER_CHUNK, function (Collection $comments): void {
                foreach ($comments as $comment) {
                    $createdAt = $this->randomPastDate();
                    $isReply = $comment->commentable_type === CommentType::COMMENT;

                    $this->queue(
                        $isReply ? ActivityLogContentEvent::CommentReplied : ActivityLogContentEvent::CommentCreated,
                        $comment->user_id,
                        Comment::MORPH_NAME,
                        $comment->id,
                        $createdAt,
                        $isReply
                            ? ['thread_comment_id' => $comment->commentable_id, 'replied_to_comment_id' => $comment->commentable_id]
                            : ['post_id' => $comment->commentable_id],
                        web: true,
                    );

                    if ($comment->edited_at !== null) {
                        $this->queue(ActivityLogContentEvent::CommentUpdated, $comment->user_id, Comment::MORPH_NAME, $comment->id, $this->dateAfter($createdAt), web: true);
                    }

                    if ($comment->trashed()) {
                        $this->queue(ActivityLogContentEvent::CommentDeleted, $comment->user_id, Comment::MORPH_NAME, $comment->id, $this->dateAfter($createdAt), ['permanently' => false], web: true);
                    }
                }
            });
    }

    private function seedLikeActivity(): void
    {
        Like::query()->select(['id', 'user_id', 'likeable_type', 'likeable_id'])->chunkById(self::RECORDS_PER_CHUNK, function (Collection $likes): void {
            foreach ($likes as $like) {
                $this->queue(ActivityLogLikeEvent::Added, $like->user_id, $like->likeable_type, $like->likeable_id, $this->randomPastDate(), web: true);
            }
        });
    }

    /**
     * Mirror every seeded trusted device event with its own date, IP and user agent.
     */
    private function seedTrustedDeviceActivity(): void
    {
        TrustedDeviceEvent::query()->whereNotNull('user_id')->chunkById(self::RECORDS_PER_CHUNK, function (Collection $trustedDeviceEvents): void {
            foreach ($trustedDeviceEvents as $trustedDeviceEvent) {
                $this->pendingRows[] = $this->row(
                    ActivityLogTrustedDeviceEvent::from('trusted_devices.'.$trustedDeviceEvent->action->value),
                    $trustedDeviceEvent->user_id,
                    TrustedDeviceEvent::MORPH_NAME,
                    $trustedDeviceEvent->id,
                    $trustedDeviceEvent->created_at ?? $this->randomPastDate(),
                    [
                        'ip' => $trustedDeviceEvent->ip,
                        'user_agent' => $trustedDeviceEvent->user_agent === null ? null : mb_substr($trustedDeviceEvent->user_agent, 0, self::USER_AGENT_MAX_LENGTH),
                    ],
                );

                $this->flushWhenFull();
            }
        });
    }

    /**
     * Logins and logouts, a few failed attempts, occasional password changes and the
     * confirmation of two-factor authentication for the users who have it.
     */
    private function seedAuthActivity(): void
    {
        User::query()->select(['id', 'two_factor_confirmed_at'])->chunkById(self::RECORDS_PER_CHUNK, function (Collection $users): void {
            foreach ($users as $user) {
                $loginCount = SkewedRandom::count(0, self::MAX_LOGINS_PER_USER, self::LOGINS_SKEW);

                for ($i = 0; $i < $loginCount; $i++) {
                    $loggedInAt = $this->randomPastDate();
                    $this->queue(ActivityLogAuthEvent::UserLoggedIn, $user->id, User::MORPH_NAME, $user->id, $loggedInAt, ['guard' => 'web'], web: true);

                    if (WeightedRandom::chance(60)) {
                        $this->queue(ActivityLogAuthEvent::UserLoggedOut, $user->id, User::MORPH_NAME, $user->id, $this->dateAfter($loggedInAt), ['guard' => 'web'], web: true);
                    }
                }

                if (WeightedRandom::chance(15)) {
                    foreach (range(1, random_int(1, 3)) as $attempt) {
                        $this->queue(ActivityLogAuthEvent::LoginFailed, null, User::MORPH_NAME, $user->id, $this->randomPastDate(), ['guard' => 'web'], web: true);
                    }
                }

                if (WeightedRandom::chance(10)) {
                    $this->queue(ActivityLogAuthEvent::PasswordUpdated, $user->id, User::MORPH_NAME, $user->id, $this->randomPastDate(), web: true);
                }

                if ($user->two_factor_confirmed_at !== null) {
                    $this->queue(ActivityLogAuthEvent::TwoFactorConfirmed, $user->id, User::MORPH_NAME, $user->id, $this->randomPastDate(), web: true);
                }
            }
        });
    }

    /**
     * Angel bootstraps the panel and then grants every other administrator their roles from the console.
     */
    private function seedAdminActivity(): void
    {
        $bootstrapAdmin = User::query()->where('email', UserSeeder::TEST_USER_EMAIL)->first();

        if (! $bootstrapAdmin instanceof User || ! $bootstrapAdmin->hasRole(AdminRole::Admin->value, 'admin')) {
            return;
        }

        $bootstrappedAt = CarbonImmutable::now()->subDays(self::SPREAD_DAYS);

        $this->queue(ActivityLogAdminEvent::AdminBootstrapped, $bootstrapAdmin->id, User::MORPH_NAME, $bootstrapAdmin->id, $bootstrappedAt, [
            'roles' => $this->roleNames($bootstrapAdmin),
        ]);

        User::query()
            ->role(AdminRole::Admin->value, 'admin')
            ->whereKeyNot($bootstrapAdmin->id)
            ->with('roles')
            ->each(function (User $user) use ($bootstrapAdmin, $bootstrappedAt): void {
                foreach ($this->roleNames($user) as $roleName) {
                    $this->queue(ActivityLogAdminEvent::RoleGranted, $bootstrapAdmin->id, User::MORPH_NAME, $user->id, $this->dateAfter($bootstrappedAt), ['role' => $roleName]);
                }
            });
    }

    /**
     * Mirror every seeded suspension and reactivation with its own date, as made from the
     * admin panel. Only the target user's id is stored, never the reason.
     */
    private function seedAccountStatusActivity(): void
    {
        AccountStatusChange::query()->chunkById(self::RECORDS_PER_CHUNK, function (Collection $accountStatusChanges): void {
            foreach ($accountStatusChanges as $accountStatusChange) {
                $this->queue(
                    $accountStatusChange->status === AccountStatus::Suspended
                        ? ActivityLogAdminEvent::AccountSuspended
                        : ActivityLogAdminEvent::AccountReactivated,
                    $accountStatusChange->changed_by,
                    AccountStatusChange::MORPH_NAME,
                    $accountStatusChange->id,
                    $accountStatusChange->created_at ?? $this->randomPastDate(),
                    ['target_user_id' => $accountStatusChange->user_id],
                    web: true,
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function roleNames(User $user): array
    {
        return array_values(array_filter($user->getRoleNames()->all(), \is_string(...)));
    }

    /**
     * Queue one activity, adding a fake IP and user agent when it came from the web.
     *
     * @param  array<string, string|int|bool|list<string>|null>  $properties
     */
    private function queue(
        ActivityLogEvent $activityLogEvent,
        ?int $causerId,
        ?string $subjectType,
        ?int $subjectId,
        CarbonImmutable $createdAt,
        array $properties = [],
        bool $web = false,
    ): void {
        if ($web) {
            $properties += ['ip' => fake()->ipv4(), 'user_agent' => fake()->userAgent()];
        }

        $this->pendingRows[] = $this->row($activityLogEvent, $causerId, $subjectType, $subjectId, $createdAt, $properties);

        $this->flushWhenFull();
    }

    /**
     * @param  array<string, string|int|bool|list<string>|null>  $properties
     * @return array<string, string|int|null>
     */
    private function row(
        ActivityLogEvent $activityLogEvent,
        ?int $causerId,
        ?string $subjectType,
        ?int $subjectId,
        CarbonImmutable $createdAt,
        array $properties,
    ): array {
        return [
            'log_name' => $activityLogEvent->logName()->value,
            'description' => $activityLogEvent->eventName(),
            'event' => $activityLogEvent->eventName(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => $causerId === null ? null : User::MORPH_NAME,
            'causer_id' => $causerId,
            'attribute_changes' => null,
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
        ];
    }

    private function flushWhenFull(): void
    {
        if (\count($this->pendingRows) >= self::ROWS_PER_INSERT) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->pendingRows === []) {
            return;
        }

        ActivityLog::query()->insert($this->pendingRows);

        $this->pendingRows = [];
    }

    private function randomPastDate(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMinutes(random_int(0, self::SPREAD_DAYS * 24 * 60));
    }

    /**
     * A moment after the given date that is never in the future.
     */
    private function dateAfter(CarbonImmutable $date): CarbonImmutable
    {
        $latest = CarbonImmutable::now();
        $candidate = $date->addMinutes(random_int(1, 3 * 24 * 60));

        return $candidate->greaterThan($latest) ? $latest : $candidate;
    }
}
