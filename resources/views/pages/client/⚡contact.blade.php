<?php

use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

new
#[Layout('layouts.client')]
class extends Component {
    use Toast;

    public string $full_name = '';
    public string $email = '';
    public string $phone = '';
    public string $subject = '';
    public string $message = '';
    public string $recaptcha_token = '';
    public string $form_error = '';
    public bool $sent = false;
    public array $pageData = [];

    protected function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:10', 'regex:/^0[0-9]{9}$/'],
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'recaptcha_token' => 'required|string',
        ];
    }

    public function mount(): void
    {
        $page = Page::where('slug', 'chan-trang')->first();

        if ($page && $page->content_data) {
            $locale = app()->getLocale();
            $translation = $page->getTranslation('content_data', $locale, false);
            $this->pageData = $translation ?: [];
        }
    }

    protected function t(string $vi, string $en): string
    {
        return app()->getLocale() === 'en' ? $en : $vi;
    }

    protected function validationMessages(): array
    {
        return [
            'full_name.required' => $this->t('Vui lòng nhập họ và tên.', 'Please enter your full name.'),
            'email.required' => $this->t('Vui lòng nhập email.', 'Please enter your email.'),
            'email.email' => $this->t('Email không đúng định dạng.', 'Email format is invalid.'),
            'phone.required' => $this->t('Vui lòng nhập số điện thoại.', 'Please enter your phone number.'),
            'phone.regex' => $this->t('Số điện thoại không hợp lệ.', 'Phone number is invalid.'),
            'subject.required' => $this->t('Vui lòng nhập tiêu đề.', 'Please enter a subject.'),
            'message.required' => $this->t('Vui lòng nhập nội dung.', 'Please enter your message.'),
            'recaptcha_token.required' => $this->t('Vui lòng xác nhận reCAPTCHA.', 'Please verify reCAPTCHA.'),
            'phone.max' => $this->t('Số điện thoại không được vượt quá 10 ký tự.', 'Phone number must not exceed 10 characters.'),
            'full_name.max' => $this->t('Họ và tên không được vượt quá 255 ký tự.', 'Full name must not exceed 255 characters.'),
            'email.max' => $this->t('Email không được vượt quá 255 ký tự.', 'Email must not exceed 255 characters.'),
            'subject.max' => $this->t('Tiêu đề không được vượt quá 255 ký tự.', 'Subject must not exceed 255 characters.'),
            'message.max' => $this->t('Nội dung không được vượt quá 2000 ký tự.', 'Message must not exceed 2000 characters.'),
            'recaptcha_token.string' => $this->t('Token reCAPTCHA không hợp lệ.', 'reCAPTCHA token is invalid.'),
        ];
    }

    public function submit(): void
    {
        $this->form_error = '';
        $this->validate($this->rules(), $this->validationMessages());

        $secretKey = (string) config('services.recaptcha.secret_key');

        if ($secretKey === '') {
            $this->form_error = $this->t(
                'reCAPTCHA chưa được cấu hình. Vui lòng liên hệ quản trị viên.',
                'reCAPTCHA is not configured. Please contact the administrator.'
            );
            $this->error($this->form_error);
            return;
        }

        $verify = Http::asForm()->timeout(10)->post('https://www.recaptcha.net/recaptcha/api/siteverify', [
            'secret' => $secretKey,
            'response' => $this->recaptcha_token,
            'remoteip' => request()->ip(),
        ]);

        $payload = $verify->json();
        $success = (bool) data_get($payload, 'success', false);

        if (!$success) {
            $this->form_error = $this->t(
                'Xác thực reCAPTCHA không thành công. Vui lòng thử lại.',
                'reCAPTCHA verification failed. Please try again.'
            );
            $this->addError('recaptcha_token', $this->form_error);
            $this->dispatch('contact-recaptcha-reset');
            $this->error($this->form_error);
            return;
        }

        ContactMessage::create([
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'locale' => app()->getLocale(),
            'status' => ContactMessage::STATUS_NEW,
            'recaptcha_score' => null,
            'recaptcha_action' => null,
            'sent_at' => now(),
        ]);

        $this->reset(['full_name', 'email', 'phone', 'subject', 'message', 'recaptcha_token']);
        $this->sent = true;
        $this->dispatch('contact-recaptcha-reset');
        $this->success($this->t(
            'Gửi tin nhắn thành công. Chúng tôi sẽ liên hệ với bạn sớm nhất!',
            'Your message has been sent successfully. We will contact you as soon as possible!'
        ));
    }
};
?>

<div class="w-full max-w-330 mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
    <x-slot:title>
        {{ __('Contact') }}
    </x-slot:title>

    <x-slot:breadcrumb>
        <span>{{ __('Contact') }}</span>
    </x-slot:breadcrumb>

    <x-slot:titleBreadcrumb>
        {{ __('Contact us') }}
    </x-slot:titleBreadcrumb>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-10">
        <div>
            <p class="text-[16px]/[20px] text-slate-600 mb-6">
                {{ __('Thank you for your interest in us! If you have any questions, feedback, or need assistance, please fill out the form below and click “Send Message”. We will get back to you as soon as possible. Thank you!') }}
            </p>

            <div class="space-y-4">
                @if(!empty($pageData['contact']['address']))
                    <div class="flex items-start gap-4 bg-white shadow-sm hover:shadow-md hover:scale-105 rounded-xl px-4 py-5">
                        <div class="w-11 h-11 rounded-lg bg-blue-100 text-fita flex items-center justify-center">
                            <x-icon name="o-map-pin" class="w-6 h-6" />
                        </div>
                        <div>
                            <p class="font-bold text-[18px]/[24px]">{{ __('Address') }}</p>
                            <p class="text-[16px]/[20px] text-slate-600">{{ $pageData['contact']['address'] }}</p>
                        </div>
                    </div>
                @endif

                @if(!empty($pageData['contact']['phone']))
                    <div class="flex items-start gap-4 bg-white shadow-sm hover:shadow-md hover:scale-105 rounded-xl px-4 py-5">
                        <div class="w-11 h-11 rounded-lg bg-blue-100 text-fita flex items-center justify-center">
                            <x-icon name="o-phone" class="w-6 h-6" />
                        </div>
                        <div>
                            <p class="font-bold text-[18px]/[24px]">{{ __('Phone') }}</p>
                            <p class="text-[16px]/[20px] text-slate-600">{{ $pageData['contact']['phone'] }}</p>
                        </div>
                    </div>
                @endif

                @if(!empty($pageData['contact']['email']))
                    <div class="flex items-start gap-4 bg-white shadow-sm hover:shadow-md hover:scale-105 rounded-xl px-4 py-5">
                        <div class="w-11 h-11 rounded-lg bg-blue-100 text-fita flex items-center justify-center">
                            <x-icon name="o-envelope" class="w-6 h-6" />
                        </div>
                        <div>
                            <p class="font-bold text-[18px]/[24px]">Email</p>
                            <p class="text-[16px]/[20px] text-slate-600">{{ $pageData['contact']['email'] }}</p>
                        </div>
                    </div>
                @endif
                <div class="flex items-start gap-4 bg-white shadow-sm hover:shadow-md hover:scale-105 rounded-xl px-4 py-5">
                    <div class="w-11 h-11 rounded-lg bg-blue-100 text-fita flex items-center justify-center">
                        <x-icon name="o-clock" class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="font-bold text-[18px]/[24px]">{{ __('Working hours') }}</p>
                        <p class="text-[16px]/[20px] text-slate-600">Thứ 2 - Thứ 6: 7:30 - 17:00</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="border border-slate-300 rounded-xl px-5 py-3 bg-white">
            <h2 class="font-barlow font-bold text-fita text-[20px]/[24px] text-center">{{ __('Send message') }}</h2>

            @if($sent)
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700 text-[16px]/[20px]">
                    {{ __('Your message has been sent successfully. We will contact you soon!') }}
                </div>
            @endif

            @if($form_error)
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700 text-[16px]/[20px]">
                    {{ $form_error }}
                </div>
            @endif

            <form wire:submit.prevent="submit" class="space-y-0">
                <x-input
                    label="{{ __('Full name') }}"
                    required
                    wire:model.defer="full_name"
                    placeholder="{{ __('Enter your full name') }}"
                />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Email"
                        required
                        type="email"
                        wire:model.defer="email"
                        placeholder="name@example.com"
                    />

                    <x-input
                        label="{{ __('Phone number') }}"
                        required
                        wire:model.defer="phone"
                        placeholder="09xx xxx xxx"
                    />
                </div>

                <x-input
                    label="{{ __('Subject') }}"
                    required
                    wire:model.defer="subject"
                    placeholder="{{ __('Message subject') }}"
                />

                <x-textarea
                    label="{{ __('Message') }}"
                    required
                    wire:model.defer="message"
                    rows="5"
                    placeholder="{{ __('Enter your message content') }}"
                />

                @if(config('services.recaptcha.site_key'))
                    <div class="my-3">
                        <div wire:ignore>
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" data-callback="onContactRecaptchaSuccess" data-expired-callback="onContactRecaptchaExpired"></div>
                        </div>
                        <input type="hidden" id="recaptcha_token" wire:model.defer="recaptcha_token">
                        @error('recaptcha_token')
                            <p class="text-error text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-700 text-sm">
                        {{ app()->getLocale() === 'en'
                            ? 'reCAPTCHA is not configured. Please add RECAPTCHA_SITE_KEY and RECAPTCHA_SECRET_KEY to your .env file.'
                            : 'reCAPTCHA chưa được cấu hình. Vui lòng thêm RECAPTCHA_SITE_KEY và RECAPTCHA_SECRET_KEY trong file .env.' }}
                    </div>
                @endif

                <x-button
                    type="submit"
                    label="{{ __('Send message') }}"
                    class="bg-fita text-white w-full py-3.5 text-[18px]/[20px] font-semibold hover:bg-fita2"
                    spinner="submit"
                />
            </form>
        </div>
    </div>

    @if(config('services.recaptcha.site_key'))
        @php
            $recaptchaLang = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'vi';
        @endphp
        <script src="https://www.recaptcha.net/recaptcha/api.js?hl={{ $recaptchaLang }}" async defer></script>
        <script>
            window.onContactRecaptchaSuccess = function (token) {
                const input = document.getElementById('recaptcha_token');
                if (!input) return;
                input.value = token;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            window.onContactRecaptchaExpired = function () {
                const input = document.getElementById('recaptcha_token');
                if (!input) return;
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            };

            document.addEventListener('livewire:init', () => {
                Livewire.on('contact-recaptcha-reset', () => {
                    if (window.grecaptcha) {
                        window.grecaptcha.reset();
                    }

                    const input = document.getElementById('recaptcha_token');
                    if (input) {
                        input.value = '';
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            });
        </script>
    @endif
</div>

