<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\SubjectSyllabusController;
use App\Http\Middleware\SetAdminLocale;
use App\Models\Post;
use App\Models\Student;
use App\Models\Subject;
use App\Services\VnuaTrainingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

Route::livewire('/', 'pages::client.home')->name('client.home');
Route::livewire('/trang-chu-v2', 'pages::client.home3')->name('client.home2');
Route::livewire('/gioi-thieu', 'pages::client.information')->name('client.information');
Route::livewire('/lien-he', 'pages::client.contact')->name('client.contact');
Route::livewire('/tim-kiem', 'pages::client.search')->name('client.search');
//Route::livewire('/dao-tao/chuong-trinh', 'pages::client.training-programs.index')->name('client.training-programs.index');
Route::livewire('/chuong-trinh-dao-tao', 'pages::client.training-programs.major')->name('client.training-programs.major');
Route::get('/chuong-trinh-dao-tao/de-cuong-mon-hoc/{subject}/stream', [SubjectSyllabusController::class, 'stream'])
    ->name('client.subject-syllabus.stream');
Route::get('/chuong-trinh-dao-tao/de-cuong-mon-hoc/{subject}', [SubjectSyllabusController::class, 'preview'])
    ->name('client.subject-syllabus.preview');

// Posts
Route::livewire('/bai-viet', 'pages::client.posts.index')->name('client.posts.index');

Route::livewire('/giang-vien', 'pages::client.lecturers.index')->name('client.lecturers.index');
Route::livewire('/giang-vien/{slug}', 'pages::client.lecturers.profile')->name('client.lecturers.profile');

Route::livewire('/thu-vien-anh', 'pages::client.album.gallery')->name('client.album.gallery');

// Auth
Route::livewire('/login', 'pages::client.auth.login')->name('login')->middleware('guest');
Route::livewire('/forgot-password', 'pages::client.auth.forgot-password')->name('password.request')->middleware('guest');
Route::get('/logout', [AuthenticateController::class, 'logout'])->name('handleLogout')->middleware('auth');
Route::get('auth/redirect', [AuthenticateController::class, 'redirectToSSO'])->name('sso.redirect')->middleware('guest');
Route::get('auth/callback', [AuthenticateController::class, 'handleSSOCallback'])->name('sso.callback');
Route::livewire('/setup-password/{token}', 'pages::client.auth.setup-password')->name('password.setup');


Route::middleware('auth')->group(function () {
    Route::livewire('/tai-khoan', 'pages::client.account')->middleware('auth')->name('client.account');
    Route::livewire('/doi-mat-khau', 'pages::client.account-password')->middleware('auth')->name('client.account.password');
});

Route::livewire('/{categorySlug}/{slug}', 'pages::client.posts.show')
    ->where('categorySlug', '^(?!admin$|gioi-thieu$|lien-he$|search$|chuong-trình-dao-tao$|giang-vien$|login$|forgot-password$|logout$|auth$|setup-password$|tai-khoan$|doi-mat-khau$|test-email$)[a-z0-9-]+$')
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1') // Giới hạn 60 requests/phút để chống bot spam
    ->name('client.posts.show');

// ============================================================
// ADMIN — middleware chung: auth + locale
// ============================================================
Route::prefix('admin')->middleware(['auth', SetAdminLocale::class])->group(function () {

    Route::middleware('permission:trang_quan_tri')->group(function () {
        // Dashboard — chỉ cần đăng nhập
        Route::livewire('', 'pages::admin.dashboard')->name('admin.dashboard');
    });

    // ---- Cấu hình giao diện ----
    Route::middleware('permission:cau_hinh_trang_gioi_thieu')->group(function () {
        Route::livewire('/configuration/introduction-page', 'pages::admin.configuration.introduction')->name('admin.configuration.introduction');
    });

    Route::middleware('permission:cau_hinh_trang_chu')->group(function () {
        Route::livewire('/configuration/home3', 'pages::admin.configuration.home3')->name('admin.configuration.home3');
    });

    Route::middleware('permission:cau_hinh_menu_tieu_de')->group(function () {
        Route::livewire('/configuration/header', 'pages::admin.configuration.header')->name('admin.configuration.header');
    });

    Route::middleware('permission:cau_hinh_chan_trang')->group(function () {
        Route::livewire('/configuration/footer', 'pages::admin.configuration.footer')->name('admin.configuration.footer');
    });

    Route::middleware('permission:quan_ly_banner')->group(function () {
        Route::livewire('/banner/index', 'pages::admin.banner.index')->name('admin.banner.index');
        Route::livewire('/banner/trash', 'pages::admin.banner.trash')->name('admin.banner.trash');
    });

    // ---- Cấu hình email (admin UI) ----
    Route::middleware('permission:cau_hinh_he_thong')->group(function () {
        Route::livewire('/configuration/email', 'pages::admin.configuration.mail')->name('admin.configuration.email');
        Route::livewire('/email-template/index', 'pages::admin.email-template.index')->name('admin.email-template.index');
        Route::livewire('/email-template/edit/{id}', 'pages::admin.email-template.edit')->name('admin.email-template.edit');
    });

//    anh
    Route::middleware('permission:quan_ly_anh')->group(function () {
        Route::livewire('/album/index', 'pages::admin.album.index')->name('admin.album.index');
        Route::livewire('/album/trash', 'pages::admin.album.trash')->name('admin.album.trash');
        Route::livewire('/album/{id}/images', 'pages::admin.album.show')->name('admin.album.show');
        Route::livewire('/album/gallery', 'pages::admin.album.gallery')->name('admin.gallery');
    });


    Route::middleware('permission:quan_ly_doi_tac')->group(function () {
        Route::livewire('/partner/index', 'pages::admin.partner.index')->name('admin.partner.index');
        Route::livewire('/partner/trash', 'pages::admin.partner.trash')->name('admin.partner.trash');
    });

    Route::middleware('role:giang_vien')->group(function () {
        Route::livewire('/lecturer/manager/{slug}', 'pages::admin.lecturer.manager')->name('admin.lecturer.manager');
    });

    // ---- Quản lý người dùng & vai trò ----
    Route::middleware('permission:quan_ly_nguoi_dung')->group(function () {
        Route::livewire('/user/user-list', 'pages::admin.user.user-list')->name('admin.user.user-list');
        Route::livewire('/user/create', 'pages::admin.user.create')->name('admin.user.create');
        Route::livewire('/user/edit/{id}', 'pages::admin.user.edit')->name('admin.user.edit');

        Route::livewire('/role/role-list', 'pages::admin.role.index')->name('admin.role.index');
        Route::livewire('/role/role-create', 'pages::admin.role.create')->name('admin.role.create');
        Route::livewire('/role/role-edit/{id}', 'pages::admin.role.edit')->name('admin.role.edit');
    });
// ---- Quản lý bài viết, duyệt bài viết, danh mục, ảnh mặc định ----
    Route::middleware('post.permission:access')->group(function () {
        Route::livewire('/post/index', 'pages::admin.post.index')->name('admin.post.index');
    });

    Route::middleware('post.permission:write')->group(function () {
        Route::livewire('/post/create', 'pages::admin.post.create')->name('admin.post.create');
        Route::livewire('/post/edit/{id}', 'pages::admin.post.edit')->name('admin.post.edit');
    });

    Route::middleware('post.permission:review')->group(function () {
        Route::livewire('/post/pending', 'pages::admin.post.index')->name('admin.posts.pending');
        Route::livewire('/post/review/{id}', 'pages::admin.post.review')->name('admin.posts.review');
    });

    Route::middleware('post.permission:write')->group(function () {
        Route::livewire('/post/trash', 'pages::admin.post.trash')->name('admin.post.trash');
    });

    Route::middleware('post.permission:manage')->group(function () {
        Route::livewire('/category/index', 'pages::admin.category.index')->name('admin.category.index');
        Route::livewire('/category/create', 'pages::admin.category.create')->name('admin.category.create');
        Route::livewire('/category/edit/{id}', 'pages::admin.category.edit')->name('admin.category.edit');
    });

    Route::livewire('/documents/{categorySlug}', 'pages::admin.documents.index')->name('admin.documents.index');

    /*
    |--------------------------------------------------------------------------
    | Ảnh mặc định bài viết
    |--------------------------------------------------------------------------
    | Quyền:
    | - quan_ly_bai_viet
    */
    Route::middleware('post.permission:manage')->group(function () {
        Route::livewire('/post-default-image/index', 'pages::admin.post-default-image.index')
            ->name('admin.post-default-image.index');
    });

    Route::middleware('permission:quan_ly_lien_he')->group(function () {
        Route::livewire('/contact-message/index', 'pages::admin.contact-message.index')->name('admin.contact-message.index');
        Route::livewire('/contact-message/trash', 'pages::admin.contact-message.trash')->name('admin.contact-message.trash');
    });

    // ---- Quản lý đào tạo ----
    Route::middleware('permission:quan_ly_dao_tao')->group(function () {
        Route::livewire('/training-program/index', 'pages::admin.training-program.index')->name('admin.training-program.index');
        Route::livewire('/training-program/trash', 'pages::admin.training-program.trash')->name('admin.training-program.trash');
        Route::livewire('/training-program/create', 'pages::admin.training-program.create')->name('admin.training-program.create');
        Route::livewire('/training-program/edit/{id}', 'pages::admin.training-program.edit')->name('admin.training-program.edit');
        Route::livewire('/training-program/{id}/semesters', 'pages::admin.training-program.semesters')->name('admin.training-program.semesters');

        Route::livewire('/group-subject/index', 'pages::admin.group-subject.index')->name('admin.group-subject.index');
        Route::livewire('/group-subject/create', 'pages::admin.group-subject.create')->name('admin.group-subject.create');
        Route::livewire('/group-subject/edit/{id}', 'pages::admin.group-subject.edit')->name('admin.group-subject.edit');

        // Majors (Chuyên ngành)
        Route::livewire('/major/index', 'pages::admin.major.index')->name('admin.major.index');
        // Program Majors (Ngành)
        Route::livewire('/program-major/index', 'pages::admin.program-major.index')->name('admin.program-major.index');
        // Department - Bộ môn
        Route::livewire('/department/index', 'pages::admin.department.index')->name('admin.department.index');
        // Intake - Khoa
        Route::livewire('/intake/index', 'pages::admin.intake.index')->name('admin.intake.index');

        Route::livewire('/subject/index', 'pages::admin.subject.index')->name('admin.subject.index');
        Route::livewire('/subject/trash', 'pages::admin.subject.trash')->name('admin.subject.trash');
        Route::livewire('/subject/create', 'pages::admin.subject.create')->name('admin.subject.create');
        Route::livewire('/subject/edit/{id}', 'pages::admin.subject.edit')->name('admin.subject.edit');
        Route::livewire('/subject-equivalent/index', 'pages::admin.subject-equivalent.index')->name('admin.subject-equivalent.index');
    });

    // ---- Preview (chỉ cần auth, không cần permission riêng) ----
    Route::livewire('/preview/introduction-page', 'pages::admin.preview.introduction-page')->name('admin.preview.introduction');
    Route::livewire('/preview/header-footer', 'pages::admin.preview.header-footer')->name('admin.preview.header-footer');
    Route::livewire('/preview/post/{id}', 'pages::admin.preview.post')->name('admin.preview.post');
    Route::livewire('/preview/post-new', 'pages::admin.preview.post-new')->name('admin.preview.post.new');
    Route::livewire('/preview/lecturer/{slug}', 'pages::admin.preview.lecturer')->name('admin.preview.lecturer');
});
use App\Models\User;
use App\Mail\FirstTimePasswordSetup;

//Route::get('/test-email', function () {
//    // Tạo một User giả lập hoặc lấy User đầu tiên trong DB
//    $user = User::first() ?? new User(['name' => 'Nguyễn Văn A', 'email' => 'nva@vnua.edu.vn']);
//
//    // Giả lập URL thiết lập mật khẩu
//    $fakeUrl = url('/thiet-lap-mat-khau?token=demo-token-123456');
//
//    // Return thẳng Mailable ra trình duyệt
//    return new FirstTimePasswordSetup($user, $fakeUrl);
//});

//Route::get('/fix-links', function () {
//
//    DB::statement("
//        UPDATE posts
//        SET content = REPLACE(
//            content,
//            'st-dse.vnua.edu.vn:6889',
//            'st-dse.vnua.edu.vn'
//        )
//    ");
//
//    return 'Đã sửa xong!';
//});

//Route::get('/phpinfo-test', function () {
//    phpinfo();
//});

Route::get('/sitemap.xml', function () {
    $urls = collect();

    // Trang tĩnh quan trọng
    $staticUrls = [
        route('client.home'),
        route('client.contact'),
        route('client.lecturers.index'),
        route('client.posts.index'),
    ];

    foreach ($staticUrls as $url) {
        $urls->push([
            'loc' => $url,
            'lastmod' => now()->toDateString(),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ]);
    }

    // Bài viết đã xuất bản
    Post::query()
        ->with(['categories', 'category'])
        ->where('status', 'published')
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now())
        ->latest('updated_at')
        ->chunk(200, function ($posts) use ($urls) {
            foreach ($posts as $post) {
                $categorySlug = $post->getPrimaryCategorySlug() ?: 'bai-viet';

                $urls->push([
                    'loc' => route('client.posts.show', [
                        'categorySlug' => $categorySlug,
                        'slug' => $post->slug,
                    ]),
                    'lastmod' => optional($post->updated_at)->toDateString() ?: now()->toDateString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ]);
            }
        });

    return response()
        ->view('sitemap', [
            'urls' => $urls->unique('loc')->values(),
        ])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');


Route::get('/test', function () {
    abort_unless(app()->isLocal(), 404);

    $token = 'eyJhbGciOiJBMTI4S1ciLCJlbmMiOiJBMTI4Q0JDLUhTMjU2IiwidHlwIjoiSldUIiwiY3R5IjoiSldUIn0.Ctzox3urdXpOpA9wDp-xHuc7pVLgwavNT7KzmIvG9kLMjYQCMQ_51A.o465kelNL3k8xLug_6pOiw.FwxGy_NL282cV45KffwWHuUY2ND8F29xwignas6kKqsAIjf-TIIpX1U9ZLiNgAPlUXAXZw9jum32HAAWMgB7tBM9QDrBlmZRKWTE9TIlBu1hC9bzjEhSImhCgPkcnvCUJcUSfQyTVUQr6e9Soj3erGTKp0F7rtMjNHF9OW6F4MrDJvWCXsG0it2TEFo0aPhaDPbtk6TjkVCsSq4km8qsZTTpAunNroueQEvHKjrafVFGpnurt0keZqSBY9D0GY13UKHllkpGkYliXAj4FfgvQabXxgjRmZ-pWKKdKdv56TRI-WzjBrzzzCjTKJeH1IrJLwyRzboHyikmhTHlOEp6-ZkB3BDpZMOeXZdZ3j3ZASjwfp02m9YV6GoWzBapzP_WqoJjY0c-agSzjFq5RykFJSmbaDc4FdBDvAAyj7D3Arscx5YuBBg735VuTVLfmPf75jFWdAjuLA83v1isEohM1J3_a3fybcfFZ3490U7absKCOUqzQ3vstCkmd_qSzcNqNILOiyRVc2DYrK_OsffFitnO9-sfOqAb3n2Xp7QcVxnXVnz4PRmta9k6aauFduUK3-ZRaGLkJn-i2foa3T0RBHSUme6sd_62q5DmDkwbU6N9k_M3kcKytA5p2UIBpqeCfiQtPAtGHzA0vjDgqnQqFIj2beQMQL8LyvTYmOax9Gktj7R1R03ex4u412KsCKfnj1s0t0zChFjOLTKx9JchdTiPiRhy2FqsP1_JjSgjae9rtLOu29wkcXGnBwchBK0xG5d1zH3N50g9yB3BQlCXApsHI0QTrGUACwXHJ0P9AvHwVb33RgO0-gn_KR_iKntcRyQc851nJj3bG9r2yA8bAW_ND6BsKLYqF_qb-3ZiXZocvB9lasrhXGHnTpeJlTzV9Vs6eWb6Vq9uOhEaayzPGDuF4gQw4QOOBUTBhysa6cubE2Kxf1e5ty0mvefiqWqyCQbny1ItlK006lSkdjEykV8S9usX1UYiJGE272RU2YsYh0TkTHm1kQV01Y5tNuPVuyr5cVEq9Yk0M3n17UU7YlTsK24enzA__W0Oc5nexUSJdODFSTmeuee21f7ubxdm7GxDq6zfxyOmdCKR448fFg.533b2ELyeAITVygvOF2rvw';
    $ua = 'OMKRPcO6CcO+dcOFf1Vfwpwww7gMw7J9w4AZPiXCilPDpnLCiQfCpA84PMOsSsKMZsKDCcKzAyk=';

    $response = Http::withoutVerifying()
        ->timeout(20)
        ->withHeaders([
            'Accept' => 'application/json, text/plain, */*',
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'https://daotao.vnua.edu.vn',
            'Referer' => 'https://daotao.vnua.edu.vn/manage/',
            'idpc' => '0',
            'ua' => $ua,
        ])
        ->withBody('', 'text/plain')
        ->post('https://daotao.vnua.edu.vn/manage/api/srm/w-locdsdiemsinhvien?hien_thi_mon_theo_hkdk=false');

    $json = $response->json();

    $semesters = data_get($json, 'data.ds_diem_hocky', []);

    $rows = [];

    foreach ($semesters as $semester) {
        foreach (($semester['ds_diem_mon_hoc'] ?? []) as $subject) {
            $rows[] = [
                'hoc_ky' => $semester['ten_hoc_ky'] ?? null,
                'ma_mon' => $subject['ma_mon'] ?? null,
                'ten_mon' => $subject['ten_mon'] ?? null,
                'so_tin_chi' => $subject['so_tin_chi'] ?? null,
                'diem_thi' => $subject['diem_thi'] ?? null,
                'diem_tk_10' => $subject['diem_tk'] ?? null,
                'diem_tk_4' => $subject['diem_tk_so'] ?? null,
                'diem_chu' => $subject['diem_tk_chu'] ?? null,
                'ket_qua' => ($subject['ket_qua'] ?? 0) == 1 ? 'Đạt' : 'Chưa đạt',
            ];
        }
    }

    dd($rows);
});

Route::get('/vnua-sync-test', function () {
    abort_unless(app()->isLocal(), 404);

    return '
        <form method="POST" action="/vnua-sync-test" style="max-width:420px;margin:40px auto;font-family:Arial">
            ' . csrf_field() . '

            <h2>Test đồng bộ điểm VNUA</h2>

            <div style="margin-bottom:12px">
                <label>Mã sinh viên</label><br>
                <input name="student_code" style="width:100%;padding:8px" required>
            </div>

            <div style="margin-bottom:12px">
                <label>Mật khẩu</label><br>
                <input name="password" type="password" style="width:100%;padding:8px" required>
            </div>

            <button type="submit" style="padding:8px 14px">Đồng bộ thử</button>
        </form>
    ';
});

Route::post('/vnua-sync-test', function (Request $request, VnuaTrainingService $service) {
    abort_unless(app()->isLocal(), 404);

    $validated = $request->validate([
        'student_code' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    try {
        $result = $service->syncGrades(
            studentCode: $validated['student_code'],
            password: $validated['password']
        );

        $rows = $result['rows'] ?? [];

        $studentModel = Student::where('student_code', $validated['student_code'])->first();

        if ($studentModel && !empty($rows)) {
            $service->saveGradesToDatabase($studentModel->id, $rows);

            $service->updateStudentStats($studentModel->id, $result['semesters']);
        }

        $student = [
            'userName' => data_get($result, 'current_user.userName'),
            'FullName' => data_get($result, 'current_user.FullName'),
            'principal' => data_get($result, 'current_user.principal'),
            'roles' => data_get($result, 'current_user.roles'),
        ];

        $semesters = collect($result['semesters'] ?? []);

        return view('vnua-sync-result', [
            'student' => $student,
            'semesters' => $semesters,
            'rows' => collect($rows),
            'is_saved_to_db' => $studentModel ? true : false,
        ]);

    } catch (\Throwable $e) {
        return back()->withErrors([
            'sync' => $e->getMessage(),
        ])->withInput($request->except('password'));
    }
});
