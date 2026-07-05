<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kết quả đồng bộ điểm</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800">
<div class="max-w-7xl mx-auto p-6">
    @php
        $show = fn ($array, $key, $default = '-') => filled(data_get($array, $key))
            ? data_get($array, $key)
            : $default;
    @endphp
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Kết quả đồng bộ điểm</h1>
            <p class="text-sm text-slate-500 mt-1">
                Tổng số môn: {{ $rows->count() }}
            </p>
        </div>

        <a href="/vnua-sync-test"
           class="px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-700">
            Đồng bộ lại
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-5 mb-6">
        <h2 class="text-lg font-semibold mb-4">Thông tin sinh viên</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-slate-500">Mã sinh viên</div>
                <div class="font-semibold">{{ $student['userName'] ?? '' }}</div>
            </div>

            <div>
                <div class="text-slate-500">Họ tên</div>
                <div class="font-semibold">{{ $student['FullName'] ?? '' }}</div>
            </div>

            <div>
                <div class="text-slate-500">Email/tài khoản</div>
                <div class="font-semibold">{{ $student['principal'] ?? '' }}</div>
            </div>

            <div>
                <div class="text-slate-500">Vai trò</div>
                <div class="font-semibold">{{ $student['roles'] ?? '' }}</div>
            </div>
        </div>
    </div>

    @foreach($semesters as $semester)
        @php
            $subjects = collect($semester['ds_diem_mon_hoc'] ?? []);
        @endphp

        <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
            <div class="p-5 border-b bg-slate-50">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">
                            {{ $semester['ten_hoc_ky'] ?? 'Không rõ học kỳ' }}
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Mã học kỳ: {{ $semester['hoc_ky'] ?? '' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div class="bg-white rounded-lg border px-3 py-2">
                            <div class="text-slate-500">ĐTB HK hệ 10</div>
                            <div class="font-semibold">{{ $show($semester, 'dtb_hk_he10') }}</div>
                        </div>

                        <div class="bg-white rounded-lg border px-3 py-2">
                            <div class="text-slate-500">ĐTB HK hệ 4</div>
                            <div class="font-semibold">{{ $show($semester, 'dtb_hk_he4') }}</div>
                        </div>

                        <div class="bg-white rounded-lg border px-3 py-2">
                            <div class="text-slate-500">Tích lũy hệ 10</div>
                            <div class="font-semibold">{{ $show($semester, 'dtb_tich_luy_he_10') }}</div>
                        </div>

                        <div class="bg-white rounded-lg border px-3 py-2">
                            <div class="text-slate-500">Tích lũy hệ 4</div>
                            <div class="font-semibold">{{ $show($semester, 'dtb_tich_luy_he_4') }}</div>
                        </div>
                    </div>
                </div>

                @if(!empty($semester['xep_loai_tkb_hk']))
                    <div class="mt-3 text-sm">
                        Xếp loại học kỳ:
                        <span class="font-semibold text-emerald-700">
                            {{ $semester['xep_loai_tkb_hk'] }}
                        </span>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="px-3 py-3 text-left">#</th>
                        <th class="px-3 py-3 text-left">Mã môn</th>
                        <th class="px-3 py-3 text-left">Tên môn</th>
                        <th class="px-3 py-3 text-center">Nhóm</th>
                        <th class="px-3 py-3 text-center">TC</th>
                        <th class="px-3 py-3 text-center">Giữa kỳ</th>
                        <th class="px-3 py-3 text-center">Thi</th>
                        <th class="px-3 py-3 text-center">TK 10</th>
                        <th class="px-3 py-3 text-center">TK 4</th>
                        <th class="px-3 py-3 text-center">Chữ</th>
                        <th class="px-3 py-3 text-center">Kết quả</th>
                        <th class="px-3 py-3 text-left">Ghi chú</th>
                    </tr>
                    </thead>

                    <tbody class="divide-y">
                    @forelse($subjects as $index => $subject)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-3">{{ $index + 1 }}</td>

                            <td class="px-3 py-3 font-medium">
                                {{ $subject['ma_mon'] ?? '' }}
                            </td>

                            <td class="px-3 py-3">
                                <div class="font-medium">
                                    {{ $subject['ten_mon'] ?? '' }}
                                </div>

                                @if(!empty($subject['ten_mon_eg']))
                                    <div class="text-xs text-slate-500">
                                        {{ $subject['ten_mon_eg'] }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-center">
                                {{ $subject['nhom_to'] ?? '-' }}
                            </td>

                            <td class="px-3 py-3 text-center">
                                {{ $subject['so_tin_chi'] ?? '-' }}
                            </td>

                            <td class="px-3 py-3 text-center">
                                {{ $subject['diem_giua_ky'] ?: '-' }}
                            </td>

                            <td class="px-3 py-3 text-center">
                                {{ $subject['diem_thi'] ?: '-' }}
                            </td>

                            <td class="px-3 py-3 text-center font-semibold">
                                {{ $subject['diem_tk'] ?: '-' }}
                            </td>

                            <td class="px-3 py-3 text-center">
                                {{ $subject['diem_tk_so'] ?: '-' }}
                            </td>

                            <td class="px-3 py-3 text-center font-semibold">
                                {{ $subject['diem_tk_chu'] ?: '-' }}
                            </td>

                            <td class="px-3 py-3 text-center">
                                @if(($subject['ket_qua'] ?? 0) == 1)
                                    <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                                        Đạt
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">
                                        Chưa đạt
                                    </span>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-slate-600">
                                {{ $subject['ly_do_khong_tinh_diem_tbtl'] ?: '-' }}
                            </td>
                        </tr>

                        @if(!empty($subject['ds_diem_thanh_phan']))
                            <tr class="bg-slate-50">
                                <td></td>
                                <td colspan="11" class="px-3 py-2">
                                    <div class="flex flex-wrap gap-2 text-xs">
                                        @foreach($subject['ds_diem_thanh_phan'] as $component)
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white border">
                                                <strong>{{ $component['ten_thanh_phan'] ?? '' }}</strong>:
                                                {{ $component['diem_thanh_phan'] ?? '-' }}
                                                @if(!empty($component['trong_so']))
                                                    <span class="text-slate-500">
                                                        ({{ $component['trong_so'] }}%)
                                                    </span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="12" class="px-3 py-6 text-center text-slate-500">
                                Không có môn học trong học kỳ này.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
</body>
</html>
