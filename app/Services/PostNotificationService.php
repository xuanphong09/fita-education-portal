<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PostNotificationService
{
    public function notifySubmitted(Post $post, string $actorName): void
    {
        $this->dispatch(function () use ($post, $actorName) {
            $this->reviewerRecipientsFor($post)->each(function (User $recipient) use ($post, $actorName) {
                $this->queueMail($recipient, $post, 'submitted', $actorName);
            });
        });
    }

    public function notifyApproved(Post $post, string $actorName): void
    {
        $this->dispatch(function () use ($post, $actorName) {
            $author = $post->user()->whereNotNull('email')->first();

            if (! $author instanceof User) {
                return;
            }

            $this->queueMail(
                $author,
                $post,
                'approved',
                $actorName,
                null,
                $post->published_at?->toDateTimeString()
            );
        });
    }

    public function notifyRejected(Post $post, string $actorName, string $reason): void
    {
        $this->dispatch(function () use ($post, $actorName, $reason) {
            $author = $post->user()->whereNotNull('email')->first();

            if (! $author instanceof User) {
                return;
            }

            $this->queueMail($author, $post, 'rejected', $actorName, $reason);
        });
    }

    public function notifyRevertedToPending(Post $post, string $actorName, ?int $excludeUserId = null): void
    {
        $this->dispatch(function () use ($post, $actorName, $excludeUserId) {
            $author = $post->user()->whereNotNull('email')->first();

            if ($author instanceof User) {
                $this->queueMail($author, $post, 'reverted_to_pending_author', $actorName);
            }

            $this->reviewerRecipientsFor($post, $excludeUserId)->each(function (User $recipient) use ($post, $actorName) {
                $this->queueMail($recipient, $post, 'reverted_to_pending', $actorName);
            });
        });
    }

    private function queueMail(
        User $recipient,
        Post $post,
        string $action,
        string $actorName,
        ?string $note = null,
        ?string $scheduledPublishAt = null
    ): void {
        if (blank($recipient->email)) {
            return;
        }

        $mailableClass = 'App\\Mail\\PostStatusNotificationMail';

        /** @var \Illuminate\Mail\Mailable $mail */
        $mail = new $mailableClass(
            recipientName: $recipient->name ?: $recipient->email,
            recipientEmail: $recipient->email,
            postTitle: $this->postTitle($post),
            categoryNames: $this->categoryNames($post),
            action: $action,
            actorName: $actorName,
            postUrl: $post->client_url,
            editUrl: route('admin.post.edit', ['id' => $post->id]),
            reviewUrl: route('admin.posts.review', ['id' => $post->id]),
            note: $note,
            scheduledPublishAt: $scheduledPublishAt,
        );

        Mail::to($recipient->email)->queue($mail);
    }

    private function reviewerRecipientsFor(Post $post, ?int $excludeUserId = null): Collection
    {
        $post->loadMissing(['categories', 'user']);
        $postCategoryIds = $this->postCategoryIds($post);

        return User::query()
            ->whereNotNull('email')
//            ->where('is_active', true)
            ->get()
            ->filter(function (User $user) use ($post, $postCategoryIds, $excludeUserId) {
                if ((int) $user->id === (int) $post->user_id) {
                    return false;
                }

                if ($excludeUserId !== null && (int) $user->id === (int) $excludeUserId) {
                    return false;
                }

                if ($user->can('quan_ly_bai_viet') || $user->can('duyet_bai_viet')) {
                    return true;
                }

                if ($postCategoryIds === []) {
                    return false;
                }

                $scopedCategoryIds = $user->scopedPostCategoryIds('duyet_bai_viet');

                return $scopedCategoryIds !== [] && array_intersect($postCategoryIds, $scopedCategoryIds) !== [];
            })
            ->unique(fn (User $user) => Str::lower((string) $user->email))
            ->values();
    }

    private function postCategoryIds(Post $post): array
    {
        $categoryIds = $post->categories()->pluck('categories.id')->map(fn ($categoryId) => (int) $categoryId)->toArray();

        if (empty($categoryIds) && $post->category_id) {
            $categoryIds = [(int) $post->category_id];
        }

        return array_values(array_unique(array_filter($categoryIds, fn ($categoryId) => $categoryId > 0)));
    }

    private function postCategoryNames(Post $post): array
    {
        $post->loadMissing(['categories']);

        return $post->categories
            ->map(fn ($category) => trim((string) $category->getTranslatedName()))
            ->filter()
            ->values()
            ->all();
    }

    private function categoryNames(Post $post): array
    {
        return $this->postCategoryNames($post);
    }

    private function postTitle(Post $post): string
    {
        $titleVi = trim((string) $post->getTranslation('title', 'vi', false));
        $titleEn = trim((string) $post->getTranslation('title', 'en', false));

        return $titleVi !== '' ? $titleVi : ($titleEn !== '' ? $titleEn : ('Bài viết #' . $post->id));
    }

    private function dispatch(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}


