<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class AnnouncementDismissalSyncRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * The most announcements a single sync may send; a guest rarely closes more than a few.
     */
    public const int MAX_ANNOUNCEMENT_IDS = 20;

    /**
     * Each announcement is authorized by AnnouncementPolicy::dismiss in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, FluentRuleContract>
     */
    public function rules(): array
    {
        return [
            'announcement_ids' => FluentRule::array()
                ->required()
                ->list()
                ->between(1, self::MAX_ANNOUNCEMENT_IDS)
                ->each(FluentRule::integer()->min(1)->distinct()),
        ];
    }

    /**
     * The validated announcement ids.
     *
     * @return list<int>
     */
    public function announcementIds(): array
    {
        $announcementIds = [];

        foreach ($this->array('announcement_ids') as $announcementId) {
            if (is_numeric($announcementId)) {
                $announcementIds[] = (int) $announcementId;
            }
        }

        return $announcementIds;
    }
}
