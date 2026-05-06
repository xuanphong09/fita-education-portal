    <?php

    use Livewire\Attributes\Layout;
    use Livewire\Attributes\Title;
    use Livewire\Component;
    use App\Models\Post;
    use App\Models\User;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    new
    #[Layout('layouts.app')]
    class extends Component {
        // Thống kê tổng quát
        public int $totalUsers = 0;
        public int $totalPosts = 0;
        public int $pendingPosts = 0;
        public int $approvedPosts = 0;

        // Phân quyền đặc thù
        public int $totalStudents = 0;
        public int $totalLecturers = 0;

        // Dữ liệu biểu đồ
        public array $usersDailyLabels = [];
        public array $usersDailyData = [];
        public array $usersMonthLabels = [];
        public array $usersMonthData = [];
        public array $usersMonthlyLabels = [];
        public array $usersMonthlyData = [];
        public array $postsDailyLabels = [];
        public array $postsDailyData = [];
        public array $postsMonthLabels = [];
        public array $postsMonthData = [];
        public array $postsMonthlyLabels = [];
        public array $postsMonthlyData = [];

        // Dữ liệu bảng
        public $recentPosts;
        public $newUsers;

        public bool $canViewUserStats = false;
        public bool $canViewPostStats = false;

        public function mount()
        {
            $user = auth()->user();
            $this->canViewUserStats = (bool) ($user?->can('quan_ly_nguoi_dung') ?? false);
            $this->canViewPostStats = (bool) ($user?->canAccessPostModule() ?? false);

            // 1. Thống kê cơ bản
            if ($this->canViewUserStats) {
                $this->totalUsers = User::count();
            }

            if ($this->canViewPostStats) {
                $this->totalPosts = Post::count();
                $this->pendingPosts = Post::where('status', 'pending_review')->count();
                $this->approvedPosts = Post::where('status', 'published')->count();
            }

            // 2. Thống kê đặc thù (Sinh viên / Giảng viên)
            if ($this->canViewUserStats) {
                $studentClass = '\\App\\Models\\Student';
                $lecturerClass = '\\App\\Models\\Lecturer';
                $this->totalStudents = class_exists($studentClass) ? $studentClass::count() : 0;
                $this->totalLecturers = class_exists($lecturerClass) ? $lecturerClass::count() : 0;
            }

            // 3. Dữ liệu biểu đồ theo thời gian: 7 ngày, 30 ngày và 6 tháng gần nhất
            if ($this->canViewUserStats) {
                for ($i = 6; $i >= 0; $i--) {
                    $day = Carbon::today()->subDays($i);
                    $this->usersDailyLabels[] = $day->locale('vi')->isoFormat('DD/MM');
                    $this->usersDailyData[] = User::whereDate('created_at', $day)->count();
                }

                for ($i = 29; $i >= 0; $i--) {
                    $day = Carbon::today()->subDays($i);
                    $this->usersMonthLabels[] = $day->locale('vi')->isoFormat('DD/MM');
                    $this->usersMonthData[] = User::whereDate('created_at', $day)->count();
                }

                for ($i = 5; $i >= 0; $i--) {
                    $month = Carbon::now()->subMonths($i)->startOfMonth();
                    $this->usersMonthlyLabels[] = $month->locale('vi')->isoFormat('[Tháng] MM/YYYY');
                    $this->usersMonthlyData[] = User::whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count();
                }
            }

            if ($this->canViewPostStats) {
                for ($i = 6; $i >= 0; $i--) {
                    $day = Carbon::today()->subDays($i);
                    $this->postsDailyLabels[] = $day->locale('vi')->isoFormat('DD/MM');
                    $this->postsDailyData[] = Post::whereDate('created_at', $day)->count();
                }

                for ($i = 29; $i >= 0; $i--) {
                    $day = Carbon::today()->subDays($i);
                    $this->postsMonthLabels[] = $day->locale('vi')->isoFormat('DD/MM');
                    $this->postsMonthData[] = Post::whereDate('created_at', $day)->count();
                }

                for ($i = 5; $i >= 0; $i--) {
                    $month = Carbon::now()->subMonths($i)->startOfMonth();
                    $this->postsMonthlyLabels[] = $month->locale('vi')->isoFormat('[Tháng] MM/YYYY');
                    $this->postsMonthlyData[] = Post::whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count();
                }
            }

            // 4. Dữ liệu bảng danh sách
            $this->recentPosts = $this->canViewPostStats
                ? Post::where('status', 'published')->with('user')->latest()->limit(5)->get()
                : collect();
            $this->newUsers = $this->canViewUserStats
                ? User::latest()->limit(5)->get()
                : collect();
        }
    };
    ?>

    <div>
        {{-- Page Title --}}
        <x-slot:title>{{ __('Dashboard') }}</x-slot:title>

        {{-- Breadcrumb --}}
        <x-slot:breadcrumb>{{ __('Dashboard') }}</x-slot:breadcrumb>

        {{-- Header --}}
{{--        <x-header title="{{ __('Dashboard') }}" subtitle="{{ __('Xin chào,') }} {{ auth()->user()->name }}!" />--}}

        {{-- Main Grid Layout --}}
        @php
            $hour = now()->hour;
            $greeting = $hour < 11 ? __('Chào buổi sáng') : ($hour < 14 ? __('Chào buổi trưa') : ($hour < 18 ? __('Chào buổi chiều') : __('Chào buổi tối')));
        @endphp

        <div x-data="dashboardData" x-init="initCharts()" class="space-y-8">
            {{-- ===== HERO BANNER ===== --}}
            <div class="rounded-3xl border border-sky-100 bg-linear-to-r from-sky-50 via-white to-cyan-50 shadow-lg shadow-sky-100/60 p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-6 ring-1 ring-white/70">
                <div class="space-y-2">
                    <div class="text-2xl md:text-3xl font-bold text-gray-900">{{ $greeting }}</div>
                    <div class="text-gray-600 text-base md:text-lg">{{ __('Chúc bạn làm việc năng suất và theo dõi hệ thống thật hiệu quả.') }}</div>
{{--                    <div class="inline-flex items-center gap-2 rounded-full bg-white/80 px-4 py-2 text-sm text-gray-600 shadow-sm border border-white">--}}
{{--                        <x-icon name="o-calendar-days" class="w-4 h-4 text-sky-500" />--}}
{{--                        <span>{{ __('Hôm nay là') }} {{ now()->format('d/m/Y') }}</span>--}}
{{--                    </div>--}}
                </div>
                <div class="shrink-0 rounded-2xl bg-white/90 border border-sky-100 shadow-sm px-5 py-4 text-center min-w-32 backdrop-blur-sm">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-amber-100 flex items-center justify-center mb-3">
                        <x-icon name="o-beaker" class="w-7 h-7 text-amber-500" />
                    </div>
{{--                    <div class="text-sm text-gray-500">{{ __('Cập nhật') }}</div>--}}
                    <div class="text-lg font-semibold text-gray-900">{{ now()->format('d/m/Y') }}</div>
                </div>
            </div>

            {{-- ===== CHỈ SỐ ===== --}}
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ __('Chỉ số') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Các số liệu tổng quan nhanh trong hệ thống.') }}</p>
                    </div>
                    <div class="hidden md:block text-sm text-gray-500">
                        {{ __('Cập nhật mới nhất') }}: {{ now()->format('H:i') }}
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    @can('quan_ly_nguoi_dung')
                        <x-card class="border-0 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="h-1 bg-linear-to-r from-sky-400 to-blue-500"></div>
                            <div class="px-4 pt-4 flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-gray-500 text-sm font-medium">{{ __('Tổng người dùng') }}</div>
                                    <div class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($totalUsers) }}</div>
{{--                                    <div class="text-sm text-sky-600 mt-2">{{ __('Theo quyền quản lý người dùng') }}</div>--}}
                                </div>
                                <div class="w-14 h-14 rounded-2xl bg-sky-100 flex items-center justify-center">
                                    <x-icon name="o-users" class="w-7 h-7 text-sky-600" />
                                </div>
                            </div>
                        </x-card>
                    @endcan

                    @if($canViewPostStats)
                        <x-card class="border-0 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="h-1 bg-linear-to-r from-emerald-400 to-green-500"></div>
                            <div class="px-4 pt-4 flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-gray-500 text-sm font-medium">{{ __('Tổng bài viết') }}</div>
                                    <div class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($totalPosts) }}</div>
                                    <div class="text-sm text-emerald-600 mt-2">{{ __('Bài viết đã xuất bản và chờ duyệt') }}</div>
                                </div>
                                <div class="w-14 md:w-20 h-14 rounded-2xl bg-emerald-100 flex items-center justify-center">
                                    <x-icon name="o-document-text" class="w-7 h-7 text-emerald-600" />
                                </div>
                            </div>
                        </x-card>

                        <x-card class="border-0 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="h-1 bg-linear-to-r from-amber-400 to-orange-500"></div>
                            <div class="px-4 pt-4 flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-gray-500 text-sm font-medium">{{ __('Chờ duyệt') }}</div>
                                    <div class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($pendingPosts) }}</div>
                                    <div class="text-sm text-orange-600 mt-2">{{ $pendingPosts > 0 ? __('Cần xem lại') : __('Không có bài chờ duyệt') }}</div>
                                </div>
                                <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center">
                                    <x-icon name="o-clock" class="w-7 h-7 text-orange-600" />
                                </div>
                            </div>
                        </x-card>

                        <x-card class="border-0 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                            <div class="h-1 bg-linear-to-r from-indigo-400 to-violet-500"></div>
                            <div class="px-4 pt-4 flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-gray-500 text-sm font-medium">{{ __('Đã duyệt') }}</div>
                                    <div class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($approvedPosts) }}</div>
                                    <div class="text-sm text-indigo-600 mt-2">{{ round(($approvedPosts / max($totalPosts, 1)) * 100) }}% {{ __('tổng bài viết') }}</div>
                                </div>
                                <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center">
                                    <x-icon name="o-check-circle" class="w-7 h-7 text-indigo-600" />
                                </div>
                            </div>
                        </x-card>
                    @endif

                    @can('quan_ly_nguoi_dung')
{{--                        <x-card class="border-0 shadow-sm hover:shadow-md transition-shadow overflow-hidden">--}}
{{--                            <div class="h-1 bg-linear-to-r from-purple-400 to-fuchsia-500"></div>--}}
{{--                            <div class="px-4 pt-4 flex items-center justify-between gap-4">--}}
{{--                                <div>--}}
{{--                                    <div class="text-gray-500 text-sm font-medium">{{ __('Quyền hạn') }}</div>--}}
{{--                                    <div class="text-3xl font-bold text-gray-900 mt-2">{{ count(auth()->user()->getAllPermissions() ?? []) }}</div>--}}
{{--                                    <div class="text-sm text-purple-600 mt-2">{{ __('Tổng số quyền đang có') }}</div>--}}
{{--                                </div>--}}
{{--                                <div class="w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center">--}}
{{--                                    <x-icon name="o-cog-6-tooth" class="w-7 h-7 text-purple-600" />--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </x-card>--}}
                    @endcan
                </div>
            </section>

            {{-- ===== BÀI VIẾT ===== --}}
            @if($canViewPostStats)
                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ __('Bài viết') }}</h2>
                            <p class="text-sm text-gray-500">{{ __('Theo dõi xu hướng bài viết theo mốc thời gian.') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <x-card class="shadow-sm xl:col-span-2" title="{{ __('Thống kê bài viết theo thời gian') }}">
                            <x-slot:menu>
                            <div class="flex items-center justify-between gap-3">
    {{--                            <div class="text-sm text-gray-500">{{ __('Chọn mốc thời gian để xem nhanh') }}</div>--}}
                                <div class="inline-flex rounded-xl bg-gray-100 p-1 text-sm font-medium">
                                    <button type="button" @click="setPostPeriod('daily')" :class="postPeriod === 'daily' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">{{ __('7 ngày') }}</button>
                                    <button type="button" @click="setPostPeriod('monthly')" :class="postPeriod === 'monthly' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">{{ __('30 ngày') }}</button>
                                    <button type="button" @click="setPostPeriod('semester')" :class="postPeriod === 'semester' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">{{ __('6 tháng') }}</button>
                                </div>
                            </div>
                            </x-slot:menu>
                            <div class="h-full relative w-full">
                                <canvas id="postsTrendChart"></canvas>
                            </div>
                        </x-card>

                        <div class="space-y-6">
                            <x-card title="{{ __('Bài viết gần đây') }}" class="shadow-sm">
                                @if($recentPosts->isEmpty())
                                    <div class="text-center py-8 text-gray-500">{{ __('Không có bài viết nào.') }}</div>
                                @else
                                    <div class="space-y-0.5">
                                        @foreach($recentPosts as $post)
                                            <div class="flex items-start justify-between gap-3 px-3 py-2 rounded-2xl hover:bg-gray-50 transition">
                                                <div class="min-w-0">
                                                    <a href="{{ $post->client_url }}" target="_blank" class="font-semibold text-gray-900 hover:text-sky-600 block truncate">
                                                        {{ Str::limit($post->getTranslation('title', 'vi', false) ?: $post->title, 60) }}
                                                    </a>
                                                    <div class="text-sm text-gray-500 mt-1">{{ $post->user->name ?? '-' }} • {{ $post->created_at->locale('vi')->diffForHumans() }}</div>
                                                </div>
    {{--                                            <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $post->status === 'published' ? 'bg-emerald-100 text-emerald-700' : ($post->status === 'pending_review' ? 'bg-amber-100 text-amber-700' : ($post->status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-700')) }}">--}}
    {{--                                                {{ $post->status === 'published' ? __('Đã đăng') : ($post->status === 'pending_review' ? __('Chờ duyệt') : ($post->status === 'rejected' ? __('Từ chối') : ucfirst($post->status))) }}--}}
    {{--                                            </span>--}}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </x-card>

                            <x-card title="{{ __('Hành động nhanh') }}" class="shadow-sm">
                                <div class="space-y-2">
                                    <a href="{{ route('admin.post.create') }}" class="flex items-center justify-between px-4 py-3 bg-linear-to-r from-sky-500 to-cyan-600 text-white rounded-xl hover:from-sky-600 hover:to-cyan-700 transition">
                                        <span>{{ __('Tạo bài viết') }}</span>
                                        <x-icon name="o-plus" class="w-5 h-5" />
                                    </a>
                                    <a href="{{ route('admin.post.index') }}" class="flex items-center justify-between px-4 py-3 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition">
                                        <span>{{ __('Quản lý bài viết') }}</span>
                                        <x-icon name="o-arrow-right" class="w-5 h-5" />
                                    </a>
                                </div>
                            </x-card>
                        </div>
                    </div>
                </section>
            @endif

            {{-- ===== NGƯỜI DÙNG ===== --}}
            @can('quan_ly_nguoi_dung')
                <section class="space-y-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ __('Người dùng') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Biểu đồ người dùng mới và danh sách đăng ký gần đây.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <x-card class="shadow-sm xl:col-span-2" title="{{ __('Người dùng mới theo thời gian') }}">
                            <x-slot:menu>
                            <div class="flex items-center justify-between gap-3">
{{--                                <div class="text-sm text-gray-500">{{ __('Theo dõi theo 7 ngày, 30 ngày hoặc 6 tháng gần nhất') }}</div>--}}
                                <div class="inline-flex rounded-xl bg-gray-100 p-1 text-sm font-medium">
                                    <button type="button" @click="setUserPeriod('daily')" :class="userPeriod === 'daily' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">{{ __('7 ngày') }}</button>
                                    <button type="button" @click="setUserPeriod('monthly')" :class="userPeriod === 'monthly' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">{{ __('30 ngày') }}</button>
                                    <button type="button" @click="setUserPeriod('semester')" :class="userPeriod === 'semester' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1.5 rounded-lg transition">{{ __('6 tháng') }}</button>
                                </div>
                            </div>
                            </x-slot:menu>
                            <div class="h-96 relative w-full">
                                <canvas id="usersTrendChart"></canvas>
                            </div>
                        </x-card>

                        <x-card title="{{ __('Người dùng mới') }}" class="shadow-sm">
                            @if($newUsers->isEmpty())
                                <div class="text-center py-8 text-gray-500 text-sm">{{ __('Không có người dùng mới.') }}</div>
                            @else
                                <div class="space-y-3">
                                    @foreach($newUsers as $user)
                                        <div class="flex items-center gap-3 p-2 rounded-2xl hover:bg-gray-50 transition">
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random&size=44" alt="{{ $user->name }}" class="w-11 h-11 rounded-full">
                                            <div class="min-w-0 flex-1">
                                                <div class="font-medium text-gray-900 text-sm truncate">{{ $user->name }}</div>
                                                <div class="text-xs text-gray-500 truncate">{{ $user->email }}</div>
                                            </div>
                                            <div class="text-[11px] text-gray-400 whitespace-nowrap">{{ $user->created_at->locale('vi')->diffForHumans() }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-card>
                    </div>
                </section>
            @endcan

            {{-- ===== QUẢN TRỊ HỆ THỐNG ===== --}}
            <section class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('Quản trị hệ thống') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Thông tin kỹ thuật và quyền hạn hiện tại.') }}</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    @can('quan_ly_nguoi_dung')
                        <x-card title="{{ __('Phân quyền tổng quan') }}" class="shadow-sm lg:col-span-2">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="text-center p-4 bg-linear-to-br from-sky-50 to-sky-100 rounded-2xl">
                                    <x-icon name="o-users" class="w-8 h-8 text-sky-600 mx-auto mb-2" />
                                    <div class="text-2xl font-bold text-sky-900">{{ number_format($totalStudents) }}</div>
                                    <div class="text-sm text-sky-700">{{ __('Sinh viên') }}</div>
                                </div>
                                <div class="text-center p-4 bg-linear-to-br from-violet-50 to-violet-100 rounded-2xl">
                                    <x-icon name="o-academic-cap" class="w-8 h-8 text-violet-600 mx-auto mb-2" />
                                    <div class="text-2xl font-bold text-violet-900">{{ number_format($totalLecturers) }}</div>
                                    <div class="text-sm text-violet-700">{{ __('Giảng viên') }}</div>
                                </div>
                                <div class="text-center p-4 bg-linear-to-br from-emerald-50 to-emerald-100 rounded-2xl">
                                    <x-icon name="o-cog-6-tooth" class="w-8 h-8 text-emerald-600 mx-auto mb-2" />
                                    <div class="text-2xl font-bold text-emerald-900">{{ count(auth()->user()->getAllPermissions() ?? []) }}</div>
                                    <div class="text-sm text-emerald-700">{{ __('Quyền hạn') }}</div>
                                </div>
                            </div>
                        </x-card>
                    @endcan

                    <x-card title="{{ __('Thông tin hệ thống') }}" class="shadow-sm">
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-600">{{ __('Phiên bản Laravel') }}</span>
                                <span class="font-medium text-gray-900">{{ app()->version() }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-600">{{ __('Múi giờ') }}</span>
                                <span class="font-medium text-gray-900">{{ config('app.timezone') }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-600">{{ __('Ngôn ngữ') }}</span>
                                <span class="font-medium text-gray-900 uppercase">{{ app()->getLocale() }}</span>
                            </div>
                            <hr class="my-2">
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-600">{{ __('Truy cập cuối') }}</span>
                                <span class="font-medium text-gray-900 text-xs text-right">{{ auth()->user()->last_login_at ? auth()->user()->last_login_at->locale('vi')->format('d/m/Y H:i') : 'N/A' }}</span>
                            </div>
                        </div>
                    </x-card>
                </div>
            </section>
        </div>
        {{-- Sử dụng @script của Livewire thay vì thẻ <script> thông thường --}}
        @script
        <script>
            Alpine.data('dashboardData', () => ({
                userPeriod: 'daily',
                postPeriod: 'daily',
                usersChart: null,
                postsChart: null,
                chartData: {
                    users: {
                        daily: {
                            labels: $wire.usersDailyLabels,
                            data: $wire.usersDailyData,
                        },
                        monthly: {
                            labels: $wire.usersMonthLabels,
                            data: $wire.usersMonthData,
                        },
                        semester: {
                            labels: $wire.usersMonthlyLabels,
                            data: $wire.usersMonthlyData,
                        }
                    },
                    posts: {
                        daily: {
                            labels: $wire.postsDailyLabels,
                            data: $wire.postsDailyData,
                        },
                        monthly: {
                            labels: $wire.postsMonthLabels,
                            data: $wire.postsMonthData,
                        },
                        semester: {
                            labels: $wire.postsMonthlyLabels,
                            data: $wire.postsMonthlyData,
                        }
                    }
                },

                initCharts() {
                    this.renderUsersChart();
                    this.renderPostsChart();
                },

                setUserPeriod(period) {
                    this.userPeriod = period;
                    this.renderUsersChart();
                },

                setPostPeriod(period) {
                    this.postPeriod = period;
                    this.renderPostsChart();
                },

                renderUsersChart() {
                    const ctx = document.getElementById('usersTrendChart')?.getContext('2d');
                    if (!ctx) return;

                    const series = this.chartData.users[this.userPeriod] || this.chartData.users.daily;
                    const maxValue = series.data.length ? Math.max(...series.data) + 5 : 5;

                    if (this.usersChart) this.usersChart.destroy();

                    this.usersChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: series.labels,
                            datasets: [{
                                label: 'Người dùng mới',
                                data: series.data,
                                borderColor: '#0ea5e9',
                                backgroundColor: 'rgba(14, 165, 233, 0.12)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#0ea5e9',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: { usePointStyle: true, padding: 15 }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    borderRadius: 8,
                                    titleFont: { size: 14 },
                                    bodyFont: { size: 12 },
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + ' người';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: maxValue,
                                    grid: { color: 'rgba(0, 0, 0, 0.05)' },
                                    ticks: { font: { size: 12 } }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 12 } }
                                }
                            }
                        }
                    });
                },

                renderPostsChart() {
                    const ctx = document.getElementById('postsTrendChart')?.getContext('2d');
                    if (!ctx) return;

                    const series = this.chartData.posts[this.postPeriod] || this.chartData.posts.daily;

                    if (this.postsChart) this.postsChart.destroy();

                    this.postsChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: series.labels,
                            datasets: [{
                                label: 'Bài viết mới',
                                data: series.data,
                                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                                borderRadius: 10,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: { usePointStyle: true, padding: 15 }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    borderRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + ' bài viết';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    suggestedMax: series.data.length ? Math.max(...series.data) + 5 : 5,
                                    grid: { color: 'rgba(0, 0, 0, 0.05)' }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            }));
        </script>
        @endscript
    </div>





