<?php

namespace Tests\Feature;

use App\Mail\PostStatusNotificationMail;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PostNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function givePermission(User $user, string $permission): void
    {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    private function createCategory(string $slug, string $nameVi, string $nameEn): Category
    {
        return Category::create([
            'name' => ['vi' => $nameVi, 'en' => $nameEn],
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    public function test_submitting_post_for_review_notifies_matching_reviewers_only(): void
    {
        Mail::fake();

        $writer = User::factory()->create();
        $reviewerScoped = User::factory()->create();
        $reviewerGlobal = User::factory()->create();
        $reviewerWrongCategory = User::factory()->create();

        $this->givePermission($writer, 'viet_bai_viet');

        $categoryNews = $this->createCategory('tin-tuc', 'Tin tức', 'News');
        $categoryEvents = $this->createCategory('su-kien', 'Sự kiện', 'Events');

        $this->givePermission($reviewerScoped, 'duyet_bai_viet:' . $categoryNews->id);
        $this->givePermission($reviewerGlobal, 'duyet_bai_viet');
        $this->givePermission($reviewerWrongCategory, 'duyet_bai_viet:' . $categoryEvents->id);

        $this->actingAs($writer);

        Livewire::test('pages::admin.post.create')
            ->set('title_vi', 'Bài viết thử nghiệm')
            ->set('content_vi', '<p>Nội dung bài viết</p>')
            ->set('slug', 'bai-viet-thu-nghiem')
            ->set('category_ids', [$categoryNews->id])
            ->call('saveAndSubmitForReview')
            ->assertHasNoErrors();

        $post = Post::query()->where('slug', 'bai-viet-thu-nghiem')->firstOrFail();

        Mail::assertQueuedCount(2);

        Mail::assertQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($reviewerScoped, $post) {
            return $mail->recipientEmail === $reviewerScoped->email
                && $mail->action === 'submitted'
                && $mail->reviewUrl === route('admin.posts.review', ['id' => $post->id]);
        });

        Mail::assertQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($reviewerGlobal) {
            return $mail->recipientEmail === $reviewerGlobal->email
                && $mail->action === 'submitted';
        });

        Mail::assertNotQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($reviewerWrongCategory) {
            return $mail->recipientEmail === $reviewerWrongCategory->email;
        });
    }

    public function test_approving_post_notifies_the_author(): void
    {
        Mail::fake();

        $reviewer = User::factory()->create();
        $author = User::factory()->create();
        $category = $this->createCategory('tin-tuc', 'Tin tức', 'News');

        $this->givePermission($reviewer, 'duyet_bai_viet');

        $post = Post::create([
            'title' => ['vi' => 'Bài viết chờ duyệt', 'en' => 'Pending post'],
            'content' => ['vi' => '<p>Nội dung</p>', 'en' => '<p>Content</p>'],
            'slug' => 'bai-viet-cho-duyet-mail',
            'status' => Post::APPROVAL_PENDING,
            'submitted_at' => now(),
            'user_id' => $author->id,
            'category_id' => $category->id,
        ]);
        $post->categories()->sync([$category->id]);

        $this->actingAs($reviewer);

        Livewire::test('pages::admin.post.review', ['id' => $post->id])
            ->call('approvePost')
            ->assertHasNoErrors();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($author, $post) {
            return $mail->recipientEmail === $author->email
                && $mail->action === 'approved'
                && $mail->postUrl === $post->client_url;
        });
    }

    public function test_rejecting_post_notifies_the_author_with_reason(): void
    {
        Mail::fake();

        $reviewer = User::factory()->create();
        $author = User::factory()->create();
        $category = $this->createCategory('tin-tuc', 'Tin tức', 'News');

        $this->givePermission($reviewer, 'duyet_bai_viet');

        $post = Post::create([
            'title' => ['vi' => 'Bài viết bị từ chối', 'en' => 'Rejected post'],
            'content' => ['vi' => '<p>Nội dung</p>', 'en' => '<p>Content</p>'],
            'slug' => 'bai-viet-bi-tu-choi-mail',
            'status' => Post::APPROVAL_PENDING,
            'submitted_at' => now(),
            'user_id' => $author->id,
            'category_id' => $category->id,
        ]);
        $post->categories()->sync([$category->id]);

        $this->actingAs($reviewer);

        Livewire::test('pages::admin.post.review', ['id' => $post->id])
            ->set('reviewNote', 'Nội dung chưa đạt yêu cầu.')
            ->call('rejectPost')
            ->assertHasNoErrors();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($author) {
            return $mail->recipientEmail === $author->email
                && $mail->action === 'rejected'
                && $mail->note === 'Nội dung chưa đạt yêu cầu.';
        });
    }

    public function test_revert_to_pending_notifies_author_and_other_reviewers(): void
    {
        Mail::fake();

        $adminReviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $author = User::factory()->create();
        $category = $this->createCategory('tin-tuc', 'Tin tức', 'News');

        $this->givePermission($adminReviewer, 'quan_ly_bai_viet');
        $this->givePermission($otherReviewer, 'duyet_bai_viet:' . $category->id);

        $post = Post::create([
            'title' => ['vi' => 'Bài viết đã đăng', 'en' => 'Published post'],
            'content' => ['vi' => '<p>Nội dung</p>', 'en' => '<p>Content</p>'],
            'slug' => 'bai-viet-da-dang-mail',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'reviewed_by' => $adminReviewer->id,
            'reviewed_at' => now()->subHour(),
            'user_id' => $author->id,
            'category_id' => $category->id,
        ]);
        $post->categories()->sync([$category->id]);

        $this->actingAs($adminReviewer);

        Livewire::test('pages::admin.post.review', ['id' => $post->id])
            ->call('confirmedRevertToPending')
            ->assertHasNoErrors();

        Mail::assertQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($otherReviewer) {
            return $mail->recipientEmail === $otherReviewer->email
                && $mail->action === 'reverted_to_pending';
        });

        Mail::assertQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($author) {
            return $mail->recipientEmail === $author->email
                && $mail->action === 'reverted_to_pending_author';
        });

        Mail::assertNotQueued(PostStatusNotificationMail::class, function (PostStatusNotificationMail $mail) use ($adminReviewer) {
            return $mail->recipientEmail === $adminReviewer->email
                && $mail->action === 'reverted_to_pending';
        });
    }
}


