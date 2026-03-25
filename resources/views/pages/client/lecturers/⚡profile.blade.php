<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.client')]
class extends Component {
    public string $slug;
};
?>

<div class="container mx-auto px-4 py-8">
    <x-slot:title>
        TS Phạm Quang Dũng
    </x-slot:title>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="col-span-1">
            <img src="{{ asset('assets/images/user/pqd.jpg') }}" alt="TS Phạm Quang Dũng" class="w-full h-125 rounded-lg object-cover">
        </div>
        <div class="col-span-2">
            <div class="mb-4">
                <h1 class="text-4xl font-bold text-fita font-barlow font-bold uppercase">Phạm Quang Dũng</h1>
                <div class="text-[18px] mt-2">TS - Phó Trưởng khoa phụ trách</div>
            </div>
            <div>
                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">Giới thiệu</h2>
                    <div class="my-4 text-[16px]/[24px]">
                        <p>TS Phạm Quang Dũng là một giảng viên có nhiều kinh nghiệm trong lĩnh vực công nghệ thông tin. Anh đã có hơn 10 năm giảng dạy và nghiên cứu trong các lĩnh vực như lập trình, trí tuệ nhân tạo và khoa học dữ liệu.</p>
                        <p>Anh Thắng đã từng làm việc tại nhiều công ty công nghệ lớn và đã tham gia vào nhiều dự án quan trọng. Ngoài ra, anh còn là tác giả của nhiều bài báo khoa học và sách chuyên ngành về công nghệ thông tin.</p>
                        <p>Với sự nhiệt huyết và kiến thức sâu rộng, TS Phạm Quang Dũng luôn cố gắng truyền đạt kiến thức một cách dễ hiểu và hấp dẫn cho sinh viên của mình.</p>
                    </div>
                </div>
                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">Liên hệ</h2>
                    <div class="my-4 text-[16px]/[24px]">
                        <p>Số điện thoại: 0974350605</p>
                        <p>Email: pqdung@vnua.edu.vn</p>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-3">
            <div>
                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">GIẢNG DẠY</h2>
                    <ul class="my-4 text-[16px]/[24px] list-disc list-inside">
                        <li>Kiến trúc máy tính</li>
                        <li>Xử lý ngôn ngữ tự nhiên</li>
                        <li>Mạng máy tính</li>
                        <li>Quản trị mạng </li>
                        <li>Cơ sở dữ liệu </li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-fita font-barlow font-semibold text-[20px]/[24px] lg:text-[24px]/[26px] uppercase">Liên hệ</h2>
                    <div class="my-4 text-[16px]/[24px]">
                        <p>Số điện thoại: 0974350605</p>
                        <p>Email: pqdung@vnua.edu.vn</p>

                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
