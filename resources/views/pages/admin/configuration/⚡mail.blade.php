<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public bool $is_active = true;

    public string $mailer = 'smtp';
    public ?string $host = 'smtp.gmail.com';
    public ?int $port = 587;
    public ?string $username = null;
    public ?string $password = null;

    /*
     * Laravel mới dùng MAIL_SCHEME.
     * Với Gmail port 587: để rỗng để Laravel/Symfony tự dùng STARTTLS.
     * Không dùng giá trị "tls" ở đây.
     */
    public ?string $scheme = '';

    public bool $fromSameAsUsername = true;

    public ?string $from_address = null;
    public ?string $from_name = null;

    public ?string $test_email = null;

    public array $mailerOptions = [
        ['id' => 'smtp', 'name' => 'SMTP'],
    ];

    public array $schemeOptions = [
        ['id' => '', 'name' => 'Tự động / STARTTLS - Gmail 587'],
        ['id' => 'smtps', 'name' => 'SMTPS / SSL - Port 465'],
    ];

    public function mount(): void
    {
        $this->loadSetting();
    }

    public function loadSetting(): void
    {
        $this->is_active = Setting::get('MAIL_IS_ACTIVE', '1') === '1';

        $this->mailer = Setting::get(
            'MAIL_MAILER',
            config('mail.default', 'smtp')
        ) ?: 'smtp';

        $this->host = Setting::get(
            'MAIL_HOST',
            config('mail.mailers.smtp.host', 'smtp.gmail.com')
        ) ?: 'smtp.gmail.com';

        $this->port = (int) Setting::get(
            'MAIL_PORT',
            config('mail.mailers.smtp.port', 587)
        );

        $this->username = Setting::get(
            'MAIL_USERNAME',
            config('mail.mailers.smtp.username')
        );

        $this->scheme = Setting::get(
            'MAIL_SCHEME',
            config('mail.mailers.smtp.scheme')
        );

        if ($this->scheme === null) {
            $this->scheme = '';
        }

        $this->from_address = Setting::get(
            'MAIL_FROM_ADDRESS',
            config('mail.from.address')
        );

        $this->from_name = Setting::get(
            'MAIL_FROM_NAME',
            config('mail.from.name', config('app.name'))
        ) ?: config('app.name');

        $this->fromSameAsUsername = blank($this->from_address)
            || $this->from_address === $this->username;

        if ($this->fromSameAsUsername) {
            $this->from_address = $this->username;
        }

        // Không hiển thị password thật ra giao diện
        $this->password = null;
    }

    public function updatedUsername(): void
    {
        if ($this->fromSameAsUsername) {
            $this->from_address = $this->username;
        }
    }

    public function updatedFromSameAsUsername($value): void
    {
        if ($value) {
            $this->from_address = $this->username;
        }
    }

    private function normalizeMailConfig(): void
    {
        $this->mailer = filled($this->mailer)
            ? trim($this->mailer)
            : 'smtp';

        $this->host = filled($this->host)
            ? trim($this->host)
            : null;

        $this->username = filled($this->username)
            ? trim($this->username)
            : null;

        $this->scheme = filled($this->scheme)
            ? trim($this->scheme)
            : '';

        $this->from_name = filled($this->from_name)
            ? trim($this->from_name)
            : config('app.name');

        if ($this->fromSameAsUsername) {
            $this->from_address = $this->username;
        } else {
            $this->from_address = filled($this->from_address)
                ? trim($this->from_address)
                : null;
        }
    }

    private function hasSavedPassword(): bool
    {
        return filled(Setting::get('MAIL_PASSWORD'));
    }

    private function passwordIsRequired(): bool
    {
        return ! $this->hasSavedPassword()
            && blank(config('mail.mailers.smtp.password'))
            && blank($this->password);
    }

    protected function rules(): array
    {
        return [
            'is_active' => ['boolean'],

            'mailer' => ['required', 'string', 'max:50'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],

            'username' => ['required', 'email', 'max:255'],

            'password' => [
                $this->passwordIsRequired() ? 'required' : 'nullable',
                'string',
                'max:255',
            ],

            /*
             * Không có tls ở đây.
             * Gmail 587: dùng rỗng.
             * Gmail 465: dùng smtps.
             */
            'scheme' => ['nullable', 'in:,smtp,smtps'],

            'fromSameAsUsername' => ['boolean'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'is_active' => 'trạng thái kích hoạt',
            'mailer' => 'mailer',
            'host' => 'máy chủ SMTP',
            'port' => 'cổng SMTP',
            'username' => 'tài khoản đăng nhập SMTP',
            'password' => 'mật khẩu ứng dụng',
            'scheme' => 'phương thức kết nối',
            'fromSameAsUsername' => 'dùng email đăng nhập làm email gửi đi',
            'from_address' => 'email gửi đi',
            'from_name' => 'tên người gửi',
        ];
    }

    private function saveMailSettings(): void
    {
        Setting::set('MAIL_IS_ACTIVE', $this->is_active ? '1' : '0');

        Setting::set('MAIL_MAILER', $this->mailer);
        Setting::set('MAIL_HOST', $this->host);
        Setting::set('MAIL_PORT', (string) $this->port);
        Setting::set('MAIL_USERNAME', $this->username);

        /*
         * Chỉ lưu MAIL_SCHEME.
         * Với Gmail 587 thì giá trị này nên là null.
         */
        Setting::set('MAIL_SCHEME', $this->scheme ?: null);

        /*
         * Không dùng MAIL_ENCRYPTION nữa.
         * Nếu DB đã có key này thì set null để tránh Laravel đọc nhầm.
         */
        Setting::set('MAIL_ENCRYPTION', null);

        Setting::set('MAIL_FROM_ADDRESS', $this->from_address);
        Setting::set('MAIL_FROM_NAME', $this->from_name);

        /*
         * Chỉ lưu password khi người dùng nhập mới.
         * Model Setting của bạn sẽ tự mã hóa vì key có chữ PASSWORD.
         */
        if (filled($this->password)) {
            Setting::set('MAIL_PASSWORD', $this->password);
        }
    }

    private function applyMailConfig(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $password = filled($this->password)
            ? $this->password
            : Setting::get('MAIL_PASSWORD', config('mail.mailers.smtp.password'));

        config([
            'mail.default' => $this->mailer,

            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $this->host,
            'mail.mailers.smtp.port' => (int) $this->port,
            'mail.mailers.smtp.username' => $this->username,
            'mail.mailers.smtp.password' => $password,

            /*
             * Gmail port 587:
             * scheme = null
             * encryption = null
             *
             * Gmail port 465:
             * scheme = smtps
             * encryption = null
             */
            'mail.mailers.smtp.scheme' => $this->scheme ?: null,
            'mail.mailers.smtp.encryption' => null,

            'mail.from.address' => $this->from_address,
            'mail.from.name' => $this->from_name,
        ]);

        /*
         * Xóa mailer cũ trong request hiện tại,
         * tránh Laravel dùng lại config cũ.
         */
        try {
            $manager = app('mail.manager');

            if (method_exists($manager, 'purge')) {
                $manager->purge($this->mailer);
            }

            if (method_exists($manager, 'forgetMailers')) {
                $manager->forgetMailers();
            }
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }

    public function save(): void
    {
        $this->normalizeMailConfig();

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin cấu hình email.');
            throw $e;
        }

        $this->saveMailSettings();
        $this->applyMailConfig();

        $this->password = null;

        $this->success('Đã lưu cấu hình email thành công!');
        Artisan::call('queue:restart');
    }

    public function reloadSetting(): void
    {
        $this->loadSetting();

        $this->success('Đã tải lại cấu hình email.');
    }

    public function sendTestMail(): void
    {
        $this->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ], attributes: [
            'test_email' => 'email nhận thử',
        ]);

        $this->normalizeMailConfig();

        try {
            $this->validate();

            if (! $this->applyMailConfig()) {
                $this->error('Cấu hình email chưa được kích hoạt.');
                return;
            }

            Mail::raw('Đây là email kiểm tra cấu hình SMTP từ hệ thống.', function ($message) {
                $message
                    ->to($this->test_email)
                    ->subject('Kiểm tra cấu hình email');
            });

            $this->success('Gửi email kiểm tra thành công!');
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin cấu hình email.');
            throw $e;
        } catch (Throwable $e) {
            report($e);

            /*
             * Trong lúc test nên hiện lỗi thật để dễ debug.
             * Sau khi chạy ổn, bạn có thể đổi lại thông báo chung chung.
             */
            $this->error('Gửi email kiểm tra thất bại: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-slot:title>{{ __('Cấu hình email') }}</x-slot:title>

    <x-slot:breadcrumb>
        <span>{{ __('Cấu hình email') }}</span>
    </x-slot:breadcrumb>

    <x-header
        title="{{ __('Cấu hình email') }}"
        class="pb-3 mb-5! border-b border-gray-300"
    />

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-5 custom-form-admin text-[14px]!">

        {{-- FORM CẤU HÌNH --}}
        <x-card class="p-3! min-w-0" shadow separator>
            <div class="space-y-1">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <x-select
                        label="Mailer"
                        wire:model.live="mailer"
                        :options="$mailerOptions"
                        required
                    />

                    <x-input
                        label="Host"
                        wire:model.live.debounce.500ms="host"
                        placeholder="smtp.gmail.com"
                        required
                    />

                    <x-input
                        label="Port"
                        type="number"
                        wire:model.live.debounce.500ms="port"
                        placeholder="587"
                        required
                    />
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
{{--                    <x-select--}}
{{--                        label="Kết nối"--}}
{{--                        wire:model.live="scheme"--}}
{{--                        :options="$schemeOptions"--}}
{{--                        placeholder-value=""--}}
{{--                    />--}}

                    <x-input
                        label="Tài khoản"
                        wire:model.live.debounce.500ms="username"
                        placeholder="labmanagement304@gmail.com"
                        required
                    />

                    <x-password
                        label="Mật khẩu / Mật khẩu ứng dụng"
                        wire:model.live.debounce.500ms="password"
                        hint="Để trống nếu không muốn đổi mật khẩu"
                        right
                    />
                </div>

                <div class="grid grid-cols-1 gap-4">
{{--                    <div class="space-y-3">--}}
{{--                        <x-toggle--}}
{{--                            label="Dùng tài khoản đăng nhập SMTP làm email gửi đi"--}}
{{--                            wire:model.live="fromSameAsUsername"--}}
{{--                        />--}}

{{--                        <x-input--}}
{{--                            label="Email gửi đi"--}}
{{--                            wire:model.live.debounce.500ms="from_address"--}}
{{--                            placeholder="labmanagement304@gmail.com"--}}
{{--                            :disabled="$fromSameAsUsername"--}}
{{--                            required--}}
{{--                        />--}}
{{--                    </div>--}}
                    <x-input
                        label="Tên người gửi"
                        wire:model.live.debounce.500ms="from_name"
                        placeholder="{{ config('app.name') }}"
                        required
                    />
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mt-4 text-amber-900">
                    <div class="flex items-start gap-3">
                        <x-icon name="o-shield-check" class="w-6 h-6 shrink-0 mt-0.5" />

                        <div class="text-sm leading-6">
                            Với Gmail, bạn cần dùng <strong>Mật khẩu ứng dụng</strong>, không dùng mật khẩu đăng nhập Google thông thường.
                            Nếu Mật khẩu ứng dụng từng bị lộ, nên thu hồi mật khẩu cũ và tạo mật khẩu mới.
                        </div>
                    </div>
                </div>

            </div>
        </x-card>

        {{-- ACTION --}}
        <x-card
            class="bg-white p-3! xl:sticky xl:top-22 self-start"
            title="{{ __('Hành động') }}"
            shadow
            separator
            progress-indicator="save"
        >
            <div class="space-y-4">

                <div class="rounded-xl border border-gray-200 p-3">
                    <x-toggle
                        label="Kích hoạt cấu hình"
                        wire:model.live="is_active"
                        class="toggle-primary toggle-md"
                    />

                    <p class="mt-2 text-xs text-gray-500 leading-5">
                        Nếu không kích hoạt thì hệ thống sẽ dùng email mặc định.
                    </p>
                </div>

                <div class="space-y-2">
                    <x-button
                        label="Lưu cấu hình"
                        icon="o-check"
                        class="bg-primary text-white w-full"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        spinner="save"
                    />

                    <x-button
                        label="Tải lại"
                        icon="o-arrow-path"
                        class="bg-warning text-white w-full"
                        wire:click="reloadSetting"
                        wire:loading.attr="disabled"
                        wire:target="reloadSetting"
                        spinner="reloadSetting"
                    />
                </div>

                <div class="border-t border-gray-200 pt-4 space-y-3">
                    <x-input
                        label="Email nhận thử"
                        wire:model.live.debounce.500ms="test_email"
                        hint="Nên lưu cấu hình trước khi gửi thử."
                        placeholder="email@example.com"
                    />

                    <x-button
                        label="Gửi thử"
                        icon="o-paper-airplane"
                        class="bg-info text-white w-full"
                        wire:click="sendTestMail"
                        wire:loading.attr="disabled"
                        wire:target="sendTestMail"
                        spinner="sendTestMail"
                    />
                </div>

            </div>
        </x-card>
    </div>
</div>
