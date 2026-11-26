<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Requests;

use App\Modules\Friendship\Enums\FriendshipListSort;
use App\Modules\Friendship\Enums\FriendshipListTab;
use App\Support\Translation\TranslatedText;
use Illuminate\Foundation\Http\FormRequest;
use SanderMuller\FluentValidation\Contracts\FluentRuleContract;
use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class FriendshipIndexRequest extends FormRequest
{
    use HasFluentRules;

    /**
     * The longest name filter accepted, the same as the people search.
     */
    public const int TERM_MAX_LENGTH = FriendshipSearchRequest::TERM_MAX_LENGTH;

    /**
     * Any signed-in user may list their own friendships; the route's middleware already requires it.
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
            'tab' => FluentRule::string()
                ->nullable()
                ->enum(FriendshipListTab::class, message: TranslatedText::of('friendship/friends.validation.tab_invalid')),
            'q' => FluentRule::string()
                ->nullable()
                ->max(self::TERM_MAX_LENGTH, message: TranslatedText::of('friendship/friends.validation.q_max')),
            'sort' => FluentRule::string()
                ->nullable()
                ->enum(FriendshipListSort::class, message: TranslatedText::of('friendship/friends.validation.sort_invalid')),
        ];
    }

    /**
     * The list to show: the validated `tab`, or the friends when it is missing.
     */
    public function tab(): FriendshipListTab
    {
        return $this->enum('tab', FriendshipListTab::class) ?? FriendshipListTab::Friends;
    }

    /**
     * The order of the list: the validated `sort`, or the most recent first when it is missing.
     */
    public function sort(): FriendshipListSort
    {
        return $this->enum('sort', FriendshipListSort::class) ?? FriendshipListSort::Recent;
    }

    /**
     * The name filter without surrounding spaces; an empty string lists everyone.
     */
    public function term(): string
    {
        return $this->string('q')->trim()->toString();
    }
}
