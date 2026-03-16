<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Seo extends Component
{
    public ?string $title;
    public ?string $description;
    public ?string $image;
    public ?string $type;
    public ?string $url;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        ?string $type = 'website',
        ?string $url = null
    ) {
        $this->title = $title ?? config('app.name');
        $this->description = $description ?? 'Khoa Công nghệ Thông tin - Học viện Nông nghiệp Việt Nam';
        $this->image = $image;
        $this->type = $type;
        $this->url = $url ?? url()->current();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.seo');
    }
}

