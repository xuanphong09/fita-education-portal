<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostApprovalHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
	use RefreshDatabase;

	private function givePermission(User $user, string $permission): void
	{
		Permission::findOrCreate($permission, 'web');
		$user->givePermissionTo($permission);
	}

	private function createCategory(): Category
	{
		return Category::create([
			'name' => ['vi' => 'Tin tuc', 'en' => 'News'],
			'slug' => 'tin-tuc',
			'is_active' => true,
		]);
	}

	public function test_writer_can_only_submit_post_for_review(): void
	{
		$writer = User::factory()->create();
		$this->givePermission($writer, 'viet_bai_viet');

		$category = $this->createCategory();

		$this->actingAs($writer);

		Livewire::test('pages::admin.post.create')
			->set('title_vi', 'Bai viet thu nghiem')
			->set('content_vi', '<p>Noi dung bai viet</p>')
			->set('slug', 'bai-viet-thu-nghiem')
			->set('category_ids', [$category->id])
			->call('saveAndSubmitForReview')
			->assertHasNoErrors();

		$post = Post::query()->where('slug', 'bai-viet-thu-nghiem')->firstOrFail();

		$this->assertSame(Post::APPROVAL_PENDING, $post->status);
		$this->assertSame($writer->id, $post->user_id);
		$this->assertDatabaseHas('post_approval_histories', [
			'post_id' => $post->id,
			'action' => 'submitted',
		]);
	}

	public function test_reviewer_can_approve_and_schedule_publish_time(): void
	{
		$reviewer = User::factory()->create();
		$author = User::factory()->create();

		$this->givePermission($reviewer, 'duyet_bai_viet');

		$post = Post::create([
			'title' => ['vi' => 'Bai viet cho duyet', 'en' => ''],
			'content' => ['vi' => '<p>Noi dung</p>', 'en' => ''],
			'slug' => 'bai-viet-cho-duyet',
			'status' => Post::APPROVAL_PENDING,
			'submitted_at' => now(),
			'user_id' => $author->id,
		]);

		$this->actingAs($reviewer);

		$scheduled = now()->addDay()->format('Y-m-d\\TH:i');

		Livewire::test('pages::admin.post.edit', ['id' => $post->id])
			->set('published_at', $scheduled)
			->call('approvePost')
			->assertHasNoErrors();

		$post->refresh();

		$this->assertSame('published', $post->status);
		$this->assertSame($reviewer->id, $post->reviewed_by);
		$this->assertNotNull($post->published_at);

		$this->assertDatabaseHas('post_approval_histories', [
			'post_id' => $post->id,
			'action' => 'approved',
			'reviewer_id' => $reviewer->id,
		]);
	}

	public function test_rejected_post_can_be_edited_and_resubmitted(): void
	{
		$reviewer = User::factory()->create();
		$writer = User::factory()->create();

		$this->givePermission($reviewer, 'duyet_bai_viet');
		$this->givePermission($writer, 'viet_bai_viet');

		$post = Post::create([
			'title' => ['vi' => 'Bai viet can sua', 'en' => ''],
			'content' => ['vi' => '<p>Noi dung</p>', 'en' => ''],
			'slug' => 'bai-viet-can-sua',
			'status' => Post::APPROVAL_PENDING,
			'submitted_at' => now(),
			'user_id' => $writer->id,
		]);

		$this->actingAs($reviewer);

		Livewire::test('pages::admin.post.edit', ['id' => $post->id])
			->set('reviewNote', 'Noi dung chua dat yeu cau.')
			->call('rejectPost')
			->assertHasNoErrors();

		$post->refresh();
		$this->assertSame(Post::APPROVAL_REJECTED, $post->status);

		$this->actingAs($writer);

		Livewire::test('pages::admin.post.edit', ['id' => $post->id])
			->set('content_vi', '<p>Noi dung da duoc cap nhat</p>')
			->call('submitForReview')
			->assertHasNoErrors();

		$post->refresh();

		$this->assertSame(Post::APPROVAL_PENDING, $post->status);
		$this->assertNull($post->rejection_reason);

		$this->assertTrue(
			PostApprovalHistory::query()
				->where('post_id', $post->id)
				->where('action', 'resubmitted')
				->exists()
		);
	}
}



