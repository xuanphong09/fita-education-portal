<?php

use App\Models\ContactMessage;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $newCount = 0;

    public function mount(): void
    {
        $this->newCount = ContactMessage::query()
            ->where('status', ContactMessage::STATUS_NEW)
            ->count();
    }

    #[On('contact-message:new-count-changed')]
    public function applyDelta(?int $delta = null): void
    {
        if ($delta === null || $delta === 0) {
            return;
        }

        $this->newCount = max(0, $this->newCount + $delta);
    }
};
?>

<x-menu-item
    title="Danh sách liên hệ"
    icon="o-chat-bubble-bottom-center-text"
    :link="route('admin.contact-message.index')"
    :active="request()->routeIs('admin.contact-message.*')"
    :badge="$this->newCount"
    badge-color="bg-red-500 text-white"
    badgeClasses="bg-red-500 text-white rounded-full px-2 py-0 text-md absolute top-3 right-2 border-transparent"
/>

