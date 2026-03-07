<?php

use Livewire\Component;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public function changeLanguage($lang)
    {
        if (in_array($lang, ['vi', 'en'])) {
            Session::put('locale', $lang);
            App::setLocale($lang);
        }

        return $this->redirect(request()->header('Referer'), navigate: true);
    }
};
?>

<div class="z-50">
    <div class="dropdown dropdown-hover dropdown-end">

        <div tabindex="0" role="button" class="btn btn-ghost btn-sm px-2 border-none bg-transparent hover:bg-white/10 after:content-[''] after:inline-block after:align-[0.255em] after:border-t-5 after:border-r-5 after:border-r-transparent after:border-b-0 after:border-l-5 after:border-l-transparent hover:after:border-t-white">
            @if(app()->getLocale() == 'vi')
                <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7">
                    <path fill="#DA251D" d="M32 5H4a4 4 0 0 0-4 4v18a4 4 0 0 0 4 4h28a4 4 0 0 0 4-4V9a4 4 0 0 0-4-4z"></path>
                    <path fill="#FF0" d="M19.753 16.037L18 10.642l-1.753 5.395h-5.672l4.589 3.333l-1.753 5.395L18 21.431l4.589 3.334l-1.753-5.395l4.589-3.333z"></path>
                </svg>
            @else
                <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7">
                    <path fill="#00247D" d="M0 9.059V13h5.628zM4.664 31H13v-5.837zM23 25.164V31h8.335zM0 23v3.941L5.63 23zM31.337 5H23v5.837zM36 26.942V23h-5.631zM36 13V9.059L30.371 13zM13 5H4.664L13 10.837z"></path>
                    <path fill="#CF1B2B" d="M25.14 23l9.712 6.801a3.977 3.977 0 0 0 .99-1.749L28.627 23H25.14zM13 23h-2.141l-9.711 6.8c.521.53 1.189.909 1.938 1.085L13 23.943V23zm10-10h2.141l9.711-6.8a3.988 3.988 0 0 0-1.937-1.085L23 12.057V13zm-12.141 0L1.148 6.2a3.994 3.994 0 0 0-.991 1.749L7.372 13h3.487z"></path>
                    <path fill="#EEE" d="M36 21H21v10h2v-5.836L31.335 31H32a3.99 3.99 0 0 0 2.852-1.199L25.14 23h3.487l7.215 5.052c.093-.337.158-.686.158-1.052v-.058L30.369 23H36v-2zM0 21v2h5.63L0 26.941V27c0 1.091.439 2.078 1.148 2.8l9.711-6.8H13v.943l-9.914 6.941c.294.07.598.116.914.116h.664L13 25.163V31h2V21H0zM36 9a3.983 3.983 0 0 0-1.148-2.8L25.141 13H23v-.943l9.915-6.942A4.001 4.001 0 0 0 32 5h-.663L23 10.837V5h-2v10h15v-2h-5.629L36 9.059V9zM13 5v5.837L4.664 5H4a3.985 3.985 0 0 0-2.852 1.2l9.711 6.8H7.372L.157 7.949A3.968 3.968 0 0 0 0 9v.059L5.628 13H0v2h15V5h-2z"></path>
                    <path fill="#CF1B2B" d="M21 15V5h-6v10H0v6h15v10h6V21h15v-6z"></path>
                </svg>
            @endif
        </div>

        <ul tabindex="0" class="text-black dropdown-content z-1 menu drop-shadow-lg bg-base-100 w-35 p-0 border border-gray-300">
            <li>
                <button type="button" wire:click="changeLanguage('vi')" class="text-[15px] py-2.5 ps-4 pe-2 rounded-none  font-normal active:bg-fita active:text-white">
                    🇻🇳 Tiếng Việt
                </button>
            </li>
            <li>
                <button type="button" wire:click="changeLanguage('en')" class="text-[15px] py-2.5 ps-4 pe-2 rounded-none  font-normal active:bg-fita active:text-white">
                    🇬🇧 English
                </button>
            </li>
        </ul>
    </div>
</div>
