<?php

use App\Models\GroupSubject;
use App\Models\Subject;
use App\Models\SubjectEquivalent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithFileUploads;

    public string $code = '';
    public string $name_vi = '';
    public string $name_en = '';
    public int|string|null $group_subject_id = null;
    public string $credits = '0';
    public string $credits_theory = '0';
    public string $credits_practice = '0';
    public bool $is_active = true;
    public array $prerequisite_subject_ids = [];
    public $syllabus_file;

    // Các biến cho phần Môn tương đương
    public array $equivalent_subject_ids = [];
    public string $equivalentSearch = '';

    // Các biến cho phần Import
    public $import_file;
    public array $importPreviewRows = [];
    public array $selectedImportRowKeys = [];
    public array $importMissingHeaders = [];
    public string $importSearch = '';
    public bool $showImportSuccessModal = false;
    public int $importValidCount = 0;
    public int $importInvalidCount = 0;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('subjects', 'code')],
            'name_vi' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'group_subject_id' => ['nullable', 'integer', 'exists:group_subjects,id'],
            'credits' => $this->decimalRules('Tổng tín chỉ'),
            'credits_theory' => $this->decimalRules('Tín chỉ lý thuyết'),
            'credits_practice' => $this->decimalRules('Tín chỉ thực hành'),
            'is_active' => ['boolean'],
            'equivalent_subject_ids' => ['array'],
            'equivalent_subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
            'syllabus_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:' . implode(',', $this->allowedSyllabusMimeTypes()),
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $detectedMime = strtolower((string) $value->getMimeType());

                    if (!in_array($detectedMime, $this->allowedSyllabusMimeTypes(), true)) {
                        $fail('Định dạng nội dung file không hợp lệ. Chỉ chấp nhận PDF.');
                    }
                },
                'max:10240',
            ],
            'import_file' => [
                'nullable',
                'file',
                'mimes:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream',
                'max:10240',
            ],
        ];
    }

    protected $messages = [
        'code.required' => 'Mã môn học không được để trống.',
        'code.regex' => 'Mã môn chỉ gồm chữ cái, số và dấu gạch nối (-).',
        'code.unique' => 'Mã môn học đã tồn tại.',
        'name_vi.required' => 'Tên môn học tiếng Việt không được để trống.',
        'credits.required' => 'Tổng tín chỉ không được để trống.',
        'credits.regex' => 'Tổng tín chỉ chỉ nhận số nguyên hoặc thập phân 1 chữ số (vd: 1.5 hoặc 1,5).',
        'credits_theory.required' => 'Tín chỉ lý thuyết không được để trống.',
        'credits_theory.regex' => 'Tín chỉ lý thuyết chỉ nhận số nguyên hoặc thập phân 1 chữ số (vd: 1.5 hoặc 1,5).',
        'credits_practice.required' => 'Tín chỉ thực hành không được để trống.',
        'credits_practice.regex' => 'Tín chỉ thực hành chỉ nhận số nguyên hoặc thập phân 1 chữ số (vd: 1.5 hoặc 1,5).',
        'syllabus_file.mimes' => 'Đề cương môn học chỉ hỗ trợ định dạng PDF.',
        'syllabus_file.mimetypes' => 'Nội dung file không đúng định dạng PDF hợp lệ.',
        'syllabus_file.max' => 'Đề cương môn học không được vượt quá 10MB.',
        'import_file.mimes' => 'File import phải là định dạng .xlsx.',
        'import_file.max' => 'File import không được vượt quá 10MB.',
    ];

    protected function allowedSyllabusMimeTypes(): array
    {
        return [
            'application/pdf',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'credits' => 'Tổng tín chỉ',
            'credits_theory' => 'Tín chỉ lý thuyết',
            'credits_practice' => 'Tín chỉ thực hành',
        ];
    }

    protected function decimalRules(string $label): array
    {
        return [
            'required',
            'regex:/^\d+(?:[\.,]\d)?$/',
            function ($attribute, $value, $fail) use ($label) {
                $decimal = $this->toDecimal($value);

                if ($decimal === null) {
                    $fail($label . ' không hợp lệ.');
                    return;
                }

                if ($decimal < 0 || $decimal > 20) {
                    $fail($label . ' phải nằm trong khoảng từ 0 đến 20.');
                }
            },
        ];
    }

    protected function toDecimal(int|float|string|null $value): ?float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        if ($normalized === '' || !preg_match('/^\d+(?:\.\d)?$/', $normalized)) {
            return null;
        }

        return round((float) $normalized, 1);
    }

    public function updated(string $property): void
    {
        $property = ltrim($property, '$');

        if (!property_exists($this, $property)) {
            return;
        }

        if ($property === 'group_subject_id' && $this->group_subject_id === '') {
            $this->group_subject_id = null;
        }

        if (in_array($property, ['credits', 'credits_theory', 'credits_practice'], true)) {
            $this->validateCreditsDistribution();
        }

        if ($property === 'import_file' ) {
            $this->validateOnly('import_file');
            return;
        }

        $this->validateOnly($property);
    }

    public function getGroupOptionsProperty(): array
    {
        return GroupSubject::query()
            ->orderBy('sort_order')
            ->orderByRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(name, '$.vi')), '') ASC")
            ->get()
            ->map(fn (GroupSubject $group) => [
                'id' => $group->id,
                'name' => $group->getTranslation('name', 'vi', false) ?: ('#' . $group->id),
            ])
            ->toArray();
    }

    // --- CÁC HÀM XỬ LÝ MÔN TƯƠNG ĐƯƠNG ---
    public function getEquivalentSubjectOptionsProperty(): array
    {
        // Khi tạo mới, lấy tất cả môn học đang active
        return Subject::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->code
                    . ' - ' . ($subject->getTranslation('name', 'vi', false) ?: 'N/A')
                    . ' (' . Subject::formatCredit($subject->credits) . ' TC)'
            ])
            ->toArray();
    }

    public function getSelectedEquivalentsProperty()
    {
        if (empty($this->equivalent_subject_ids)) {
            return collect();
        }
        return Subject::query()->whereIn('id', $this->equivalent_subject_ids)->get();
    }

    public function getEquivalentOptionsProperty(): array
    {
        $keyword = trim($this->equivalentSearch);

        if ($keyword === '') {
            return $this->equivalentSubjectOptions;
        }

        $normalizedKeyword = $this->normalizeSearchText($keyword);

        return collect($this->equivalentSubjectOptions)
            ->filter(function (array $subject) use ($keyword, $normalizedKeyword) {
                $name = (string) ($subject['name'] ?? '');

                if (mb_stripos($name, $keyword) !== false) {
                    return true;
                }

                return str_contains($this->normalizeSearchText($name), $normalizedKeyword);
            })
            ->values()
            ->all();
    }

    public function removeEquivalent(int $equivalentId): void
    {
        // Gỡ ID khỏi mảng nháp, không đụng tới DB
        if (in_array($equivalentId, $this->equivalent_subject_ids)) {
            $this->equivalent_subject_ids = array_values(array_filter(
                $this->equivalent_subject_ids,
                fn($id) => $id !== $equivalentId
            ));
        }
    }
    // --- KẾT THÚC XỬ LÝ MÔN TƯƠNG ĐƯƠNG ---

    protected function validateCreditsDistribution(): void
    {
        $credits = $this->toDecimal($this->credits);
        $creditsTheory = $this->toDecimal($this->credits_theory);
        $creditsPractice = $this->toDecimal($this->credits_practice);

        if ($credits === null || $creditsTheory === null || $creditsPractice === null) {
            return;
        }

        if (abs(($creditsTheory + $creditsPractice) - $credits) > 0.0001) {
            throw ValidationException::withMessages([
                'credits' => ' ',
                'credits_theory' => ' ',
                'credits_practice' => ' ',
                'credits_error' => 'Tổng tín chỉ phải bằng tổng tín chỉ lý thuyết và thực hành.',
            ]);
        }

        $this->resetValidation(['credits', 'credits_theory', 'credits_practice', 'credits_error']);
    }

    protected function payload(): array
    {
        return [
            'code' => strtoupper(trim($this->code)),
            'name' => [
                'vi' => trim($this->name_vi),
                'en' => trim($this->name_en),
            ],
            'group_subject_id' => !blank($this->group_subject_id) ? (int) $this->group_subject_id : null,
            'credits' => $this->toDecimal($this->credits),
            'credits_theory' => $this->toDecimal($this->credits_theory),
            'credits_practice' => $this->toDecimal($this->credits_practice),
            'is_active' => $this->is_active,
        ];
    }

    public function save(): void
    {
        if ($this->group_subject_id === '') {
            $this->group_subject_id = null;
        }

        try {
            $this->validate();
            $this->validateCreditsDistribution();
        } catch (ValidationException $e) {
            $this->error('Vui lòng kiểm tra lại thông tin môn học.');
            throw $e;
        }

        $syllabusPath = null;
        $syllabusOriginalName = null;

        if ($this->syllabus_file) {
            $syllabusPath = $this->syllabus_file->store('uploads/subjects/syllabi', 'local');
            $syllabusOriginalName = (string) $this->syllabus_file->getClientOriginalName();
        }

        $subject = Subject::query()->create(array_merge($this->payload(), [
            'syllabus_path' => $syllabusPath,
            'syllabus_original_name' => $syllabusOriginalName,
        ]));

        // Lưu danh sách môn tương đương sau khi tạo thành công Subject
        try {
            if (!empty($this->equivalent_subject_ids)) {
                SubjectEquivalent::syncForSubject($subject->id, $this->equivalent_subject_ids);
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
        }

        $this->success('Tạo môn học thành công!', redirectTo: route('admin.subject.index'));
    }

    protected function normalizeHeader(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
        return trim($ascii);
    }

    protected function normalizeSearchText(?string $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }

    public function getFilteredImportPreviewRowsProperty(): array
    {
        $keyword = $this->normalizeSearchText($this->importSearch);

        if ($keyword === '') {
            return $this->importPreviewRows;
        }

        return array_values(array_filter($this->importPreviewRows, function (array $row) use ($keyword) {
            $searchable = $this->normalizeSearchText(implode(' ', [
                (string) ($row['code'] ?? ''),
                (string) ($row['name_vi'] ?? ''),
                (string) ($row['name_en'] ?? ''),
            ]));

            return str_contains($searchable, $keyword);
        }));
    }

    protected function xlsxColumnIndex(string $column): int
    {
        $column = strtoupper(trim($column));
        $result = 0;

        for ($i = 0; $i < strlen($column); $i++) {
            $result = ($result * 26) + (ord($column[$i]) - 64);
        }

        return max(0, $result - 1);
    }

    protected function extractXmlText(mixed $node, ?string $namespace): string
    {
        if (!$node) {
            return '';
        }

        $text = '';
        $work = $namespace ? $node->children($namespace) : $node;

        if (isset($work->t)) {
            $text .= (string) $work->t;
        }

        if (isset($work->r)) {
            foreach ($work->r as $run) {
                $runNode = $namespace ? $run->children($namespace) : $run;
                $text .= (string) ($runNode->t ?? '');
            }
        }

        return trim($text);
    }

    protected function parseWorksheetRows(string $sheetXml, array $sharedStrings): array
    {
        $sheetXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $sheetXml);
        $xml = simplexml_load_string($sheetXml);

        if ($xml === false || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                preg_match('/[A-Z]+/', $ref, $match);
                $index = isset($match[0]) ? $this->xlsxColumnIndex($match[0]) : count($values);

                $value = '';
                $type = (string) ($cell['t'] ?? '');

                if ($type === 's') {
                    $sharedIdx = (int) ($cell->v ?? -1);
                    $value = $sharedStrings[$sharedIdx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = trim(strip_tags($cell->is->asXML()));
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $values[$index] = trim($value);
            }

            if (!empty($values)) {
                ksort($values);
                $rows[] = $values;
            }
        }

        return $rows;
    }

    protected function readXlsxSheets(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'import_file' => 'Không thể đọc file XLSX.',
            ]);
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sharedStringsXml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $sharedStringsXml);
            $xml = simplexml_load_string($sharedStringsXml);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = trim(strip_tags($si->asXML()));
                }
            }
        }

        $sheetEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) {
                $sheetEntries[] = $name;
            }
        }

        natcasesort($sheetEntries);
        $sheetEntries = array_values($sheetEntries);

        if (empty($sheetEntries)) {
            $zip->close();
            throw ValidationException::withMessages([
                'import_file' => 'Không tìm thấy dữ liệu worksheet trong file Excel.',
            ]);
        }

        $sheets = [];
        foreach ($sheetEntries as $sheetName) {
            $sheetXml = $zip->getFromName($sheetName);
            if ($sheetXml === false) {
                continue;
            }

            $rows = $this->parseWorksheetRows($sheetXml, $sharedStrings);
            if (!empty($rows)) {
                $sheets[$sheetName] = $rows;
            }
        }

        $zip->close();

        if (empty($sheets)) {
            throw ValidationException::withMessages([
                'import_file' => 'Không tìm thấy dữ liệu hợp lệ trong các sheet của file Excel.',
            ]);
        }

        return $sheets;
    }

    protected function resolveHeaderMap(array $headerRow): array
    {
        $lookup = [
            'tenhocphan' => 'name_vi',
            'tenhocphann' => 'name_vi',
            'tienganh' => 'name_en',
            'tentienganh' => 'name_en',
            'mahocphan' => 'code',
            'tongsotc' => 'credits',
            'tongtc' => 'credits',
            'lt' => 'credits_theory',
            'th' => 'credits_practice',
            'nhommon' => 'group_name',
            'nhommonhoc' => 'group_name',
        ];

        $map = [];
        foreach ($headerRow as $index => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized !== '' && isset($lookup[$normalized])) {
                $map[$lookup[$normalized]] = (int) $index;
            }
        }

        $required = ['name_vi', 'code', 'credits', 'credits_theory', 'credits_practice', 'group_name'];
        $this->importMissingHeaders = array_values(array_filter($required, fn ($field) => !array_key_exists($field, $map)));

        return $map;
    }

    protected function resolveHeaderMapFromRows(array $rows): array
    {
        $bestMap = [];
        $bestHeaderRowIndex = -1;

        $scanLimit = min(count($rows), 15);
        for ($i = 0; $i < $scanLimit; $i++) {
            $map = $this->resolveHeaderMap($rows[$i] ?? []);
            if (count($map) > count($bestMap)) {
                $bestMap = $map;
                $bestHeaderRowIndex = $i;
            }

            if (count($bestMap) >= 6) {
                break;
            }
        }

        return [$bestMap, $bestHeaderRowIndex];
    }

    protected function importRules(): array
    {
        return [
            'import_file' => [
                'required',
                'file',
                'mimes:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream',
                'max:10240',
            ],
        ];
    }

    protected array $groupMapCache = [];

    protected function mapGroupSubjectId(string $groupName): ?int
    {
        if (trim($groupName) === '') {
            return null;
        }

        if (empty($this->groupMapCache)) {
            $this->groupMapCache = GroupSubject::all()->mapWithKeys(function ($g) {
                $nameVi = $g->getTranslation('name', 'vi', false) ?: '';
                return [$this->normalizeHeader($nameVi) => $g->id];
            })->toArray();
        }

        $normalizedInput = $this->normalizeHeader($groupName);

        return $this->groupMapCache[$normalizedInput] ?? null;
    }

    public function parseImportFile(): void
    {
        $this->importPreviewRows = [];
        $this->selectedImportRowKeys = [];
        $this->importMissingHeaders = [];
        $this->importSearch = '';
        $this->showImportSuccessModal = false;
        $this->importValidCount = 0;
        $this->importInvalidCount = 0;

        if (!$this->import_file) {
            $this->error('Vui lòng chọn file Excel (.xlsx) trước khi đọc dữ liệu.');
            return;
        }

        $this->validate($this->importRules());

        $sheets = $this->readXlsxSheets((string) $this->import_file->getRealPath());

        $rows = [];
        $headerMap = [];
        $headerRowIndex = -1;
        $bestScore = -1;

        foreach ($sheets as $sheetRows) {
            if (count($sheetRows) < 2) {
                continue;
            }

            [$candidateMap, $candidateHeaderIndex] = $this->resolveHeaderMapFromRows($sheetRows);
            $score = count($candidateMap);

            if ($score > $bestScore) {
                $bestScore = $score;
                $rows = $sheetRows;
                $headerMap = $candidateMap;
                $headerRowIndex = $candidateHeaderIndex;
            }
        }

        if (empty($rows) || count($rows) < 2) {
            $this->error('File import không có dữ liệu môn học.');
            return;
        }

        $required = ['name_vi', 'code', 'credits', 'credits_theory', 'credits_practice', 'group_name'];
        $this->importMissingHeaders = array_values(array_filter($required, fn ($field) => !array_key_exists($field, $headerMap)));

        if (!empty($this->importMissingHeaders)) {
            $labels = [
                'name_vi' => 'Tên học phần',
                'name_en' => 'Tên Tiếng anh',
                'code' => 'Mã học phần',
                'credits' => 'Tổng Số TC',
                'credits_theory' => 'LT',
                'credits_practice' => 'TH',
                'group_name' => 'Nhóm môn',
            ];

            $missing = implode(', ', array_map(fn ($k) => $labels[$k] ?? $k, $this->importMissingHeaders));
            $this->error('Thiếu cột bắt buộc trong file: ' . $missing);
            return;
        }

        if ($headerRowIndex < 0 || $headerRowIndex >= count($rows) - 1) {
            $this->error('Không tìm thấy dòng tiêu đề hợp lệ trong file Excel.');
            return;
        }

        $existingCodes = Subject::query()->pluck('code')->map(fn ($v) => strtoupper(trim((string) $v)))->all();
        $existingCodeSet = array_flip($existingCodes);
        $seenInFile = [];

        $preview = [];
        $selected = [];
        $key = 1;


        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $nameVi = trim((string) ($row[$headerMap['name_vi']] ?? ''));
            $nameEn = trim((string) ($row[$headerMap['name_en']] ?? ''));
            $code = strtoupper(trim((string) ($row[$headerMap['code']] ?? '')));
            $creditsRaw = trim((string) ($row[$headerMap['credits']] ?? ''));
            $creditsTheoryRaw = trim((string) ($row[$headerMap['credits_theory']] ?? ''));
            $creditsPracticeRaw = trim((string) ($row[$headerMap['credits_practice']] ?? ''));
            $groupName = trim((string) ($row[$headerMap['group_name']] ?? ''));

            if ($nameVi === '' && $nameEn === '' && $code === '' && $creditsRaw === '' && $creditsTheoryRaw === '' && $creditsPracticeRaw === '' && $groupName === '') {
                continue;
            }

            $errors = [];

            if ($nameVi === '') {
                $errors[] = 'Thiếu Tên học phần.';
            }

            if ($code === '') {
                $errors[] = 'Thiếu Mã học phần.';
            }

            if ($code !== '' && isset($existingCodeSet[$code])) {
                $errors[] = 'Mã học phần đã tồn tại trong hệ thống.';
            }

            if ($code !== '') {
                if (isset($seenInFile[$code])) {
                    $errors[] = 'Mã học phần bị trùng trong file import.';
                }
                $seenInFile[$code] = true;
            }

            $credits = $this->toDecimal($creditsRaw);
            $creditsTheory = $this->toDecimal($creditsTheoryRaw);
            $creditsPractice = $this->toDecimal($creditsPracticeRaw);

            if ($credits === null) {
                $errors[] = 'Tổng Số TC không hợp lệ.';
            }

            if ($creditsTheory === null) {
                $errors[] = 'LT không hợp lệ.';
            }

            if ($creditsPractice === null) {
                $errors[] = 'TH không hợp lệ.';
            }

            if ($credits !== null && $creditsTheory !== null && $creditsPractice !== null) {
                if (abs(($creditsTheory + $creditsPractice) - $credits) > 0.0001) {
                    $errors[] = 'Tổng Số TC phải bằng LT + TH.';
                }
            }

            $groupId = $this->mapGroupSubjectId($groupName);
            if ($groupName !== '' && $groupId === null) {
                $errors[] = 'Không tìm thấy Nhóm môn phù hợp trong hệ thống.';
            }

            $preview[] = [
                'key' => $key,
                'row_no' => $i + 1,
                'name_vi' => $nameVi,
                'name_en' => $nameEn,
                'code' => $code,
                'credits' => $credits,
                'credits_theory' => $creditsTheory,
                'credits_practice' => $creditsPractice,
                'group_name' => $groupName,
                'group_subject_id' => $groupId,
                'errors' => $errors,
                'is_valid' => empty($errors),
            ];

            if (empty($errors)) {
                $selected[] = $key;
            }

            $key++;
        }

        $this->importPreviewRows = $preview;
        $this->selectedImportRowKeys = $selected;

        if (empty($preview)) {
            $this->error('Không tìm thấy dữ liệu hợp lệ trong file import.');
            return;
        }

        $invalidCount = count(array_filter($preview, fn ($row) => !$row['is_valid']));
        $this->importValidCount = count($preview) - $invalidCount;
        $this->importInvalidCount = $invalidCount;
        $this->showImportSuccessModal = true;
        $this->success('Đã đọc file thành công: ' . count($preview) . ' dòng, lỗi ' . $invalidCount . ' dòng.');
    }

    public function selectAllValidImportRows(): void
    {
        $this->selectedImportRowKeys = array_values(array_map(
            fn ($row) => (int) $row['key'],
            array_filter($this->importPreviewRows, fn ($row) => (bool) $row['is_valid'])
        ));
    }

    public function clearImportSelection(): void
    {
        $this->selectedImportRowKeys = [];
    }

    public function saveImportedSubjects(): void
    {
        if (empty($this->importPreviewRows)) {
            $this->error('Chưa có dữ liệu import để lưu.');
            return;
        }

        $selected = array_flip(array_map('intval', $this->selectedImportRowKeys));

        $rowsToSave = array_values(array_filter($this->importPreviewRows, function (array $row) use ($selected) {
            return (bool) ($row['is_valid'] ?? false) && isset($selected[(int) ($row['key'] ?? 0)]);
        }));

        if (empty($rowsToSave)) {
            $this->error('Vui lòng chọn ít nhất 1 dòng hợp lệ để lưu.');
            return;
        }

        $codes = array_values(array_unique(array_map(fn ($row) => (string) $row['code'], $rowsToSave)));
        $existing = Subject::query()->whereIn('code', $codes)->pluck('code')->all();

        if (!empty($existing)) {
            $this->error('Không thể lưu vì có mã học phần đã tồn tại: ' . implode(', ', $existing));
            return;
        }

        DB::transaction(function () use ($rowsToSave) {
            foreach ($rowsToSave as $row) {
                Subject::query()->create([
                    'code' => (string) $row['code'],
                    'name' => [
                        'vi' => (string) $row['name_vi'],
                        'en' => (string) ($row['name_en'] ?? ''),
                    ],
                    'group_subject_id' => $row['group_subject_id'] ? (int) $row['group_subject_id'] : null,
                    'credits' => (float) $row['credits'],
                    'credits_theory' => (float) $row['credits_theory'],
                    'credits_practice' => (float) $row['credits_practice'],
                    'is_active' => $this->is_active,
                ]);
            }
        });

        $count = count($rowsToSave);

        $this->importPreviewRows = [];
        $this->selectedImportRowKeys = [];
        $this->importMissingHeaders = [];
        $this->importSearch = '';
        $this->import_file = null;

        $this->success('Đã import thành công ' . $count . ' môn học.', redirectTo: route('admin.subject.index'));
    }
};
?>

<div>
    <x-slot:title>Tạo môn học</x-slot:title>

    <x-slot:breadcrumb>
        <a href="{{ route('admin.subject.index') }}" class="font-semibold text-slate-700" wire:navigate>Danh sách môn học</a>
        <span class="mx-1">/</span>
        <span>Tạo mới</span>
    </x-slot:breadcrumb>

    <x-header title="Tạo môn học mới"
              class="pb-3 mb-5! border-(length:--var(--border)) border-b border-gray-300"/>

    <div class="grid lg:grid-cols-12 gap-5 custom-form-admin text-[14px]!">
        <div class="col-span-12 lg:col-span-9 flex flex-col gap-5">
            <x-card shadow class="p-3!">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input
                        label="Mã môn học"
                        wire:model.live.debounce.300ms="code"
                        placeholder="VD: IT202"
                        required
                    />
                    <x-select
                        label="Nhóm môn học"
                        wire:model.live="group_subject_id"
                        :options="$this->groupOptions"
                        option-value="id"
                        option-label="name"
                        placeholder="Chọn nhóm môn học"
                        placeholder-value=""
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <x-input
                        label="Tên (Tiếng Việt)"
                        wire:model.live.debounce.300ms="name_vi"
                        placeholder="VD: Cơ sở dữ liệu"
                        required
                    />
                    <x-input
                        label="Tên (Tiếng Anh)"
                        wire:model.live.debounce.300ms="name_en"
                        placeholder="VD: Database Systems"
                    />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <x-input
                        label="Tổng tín chỉ"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="credits"
                        required
                        placeholder="VD: 1,5"
                    />
                    <x-input
                        label="Tín chỉ lý thuyết"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="credits_theory"
                        required
                        placeholder="VD: 1,0"
                    />
                    <x-input
                        label="Tín chỉ thực hành"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="credits_practice"
                        required
                        placeholder="VD: 0,5"
                    />
                </div>
                @error('credits_error')
                <div class="mt-2 text-xs text-red-500">
                    {{ $message }}
                </div>
                @enderror
                <div class="mt-3 text-xs text-gray-500">
                    Gợi ý: tổng LT + TH bằng tổng tín chỉ của môn học.
                </div>

                <div class="mt-4 mb-4">
                    <x-file
                        label="Đề cương môn học (PDF)"
                        wire:model.live="syllabus_file"
                        accept=".pdf,application/pdf"
                        hint="Tối đa 10MB"
                    />
                </div>

                <div class="mt-8 border-t pt-4 col-span-2">
                    <label class="font-semibold text-gray-700 mb-3 block">Danh sách môn học tương đương</label>
                    <x-input
                        icon="o-magnifying-glass"
                        placeholder="Tìm môn tương đương theo mã hoặc tên môn..."
                        wire:model.live.debounce.300ms="equivalentSearch"
                        clearable
                    />
                    <div class="relative mt-2">
                        <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-4 p-5 bg-gray-50/50 rounded-xl border border-gray-200 shadow-sm max-h-50 overflow-auto">
                            @forelse($this->equivalentOptions as $subject)
                                <div class="select-none" wire:key="subject-equivalent-{{ $subject['id'] }}">
                                    <x-checkbox
                                        label="{{ $subject['name'] }}"
                                        wire:model.live="equivalent_subject_ids"
                                        value="{{ $subject['id'] }}"
                                        class="checkbox-primary checkbox-sm"
                                    />
                                </div>
                            @empty
                                <div class="col-span-full text-center py-4 text-red-500">
                                    Không tìm thấy môn học nào.
                                </div>
                            @endforelse
                            <div wire:loading.flex wire:target="equivalentSearch" class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <x-loading class="loading-spinner text-primary" />
                                    <span>Đang lọc môn học tương đương...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 col-span-2">
                    <label class="font-semibold text-black mb-2 block">Các môn tương đương đã chọn</label>
                    @if(empty($equivalent_subject_ids))
                        <div class="text-sm text-gray-500">Chưa có môn tương đương.</div>
                    @else
                        <div class="grid grid-cols-1 gap-2">
                            @foreach($this->selectedEquivalents as $equiv)
                                @if($equiv)
                                    <div class="flex items-center justify-between rounded border p-2">
                                        <div class="text-sm font-medium">{{ $equiv->code }} - {{ $equiv->getTranslation('name','vi',false) ?: '—' }} - {{ $equiv->credits_display. ' TC' }}</div>
                                        <div class="flex gap-2">
                                            <x-button class="btn-xs btn-ghost text-error" icon="o-trash" wire:click="removeEquivalent({{ $equiv->id }})" tooltip="Gỡ bỏ khỏi danh sách" />
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

            </x-card>
        </div>

        <div class="col-span-12 lg:col-span-3 flex flex-col gap-5">
            <x-card title="Hành động" shadow separator class="p-3!">
                <x-button
                    label="Lưu môn học"
                    class="bg-primary text-white w-full my-1"
                    wire:click="save"
                    spinner="save"
                />
            </x-card>

            <x-card title="Cài đặt" shadow class="p-3!">
                <x-toggle
                    label="Kích hoạt"
                    wire:model="is_active"
                    class="toggle-primary"
                />
            </x-card>
            <x-card title="Import môn học" shadow class="p-3!">
                <div class="grid grid-cols-1 gap-4 items-end">
                    <x-file
                        label="File Excel (.xlsx)"
                        wire:model.live="import_file"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        hint="Dùng file Excel để import hàng loạt môn học. Tối đa 10MB."
                    />

                    <x-button
                        label="Đọc file"
                        class="bg-primary text-white w-full"
                        wire:click="parseImportFile"
                        spinner="parseImportFile"
                    />
                </div>

                @if(!empty($importPreviewRows))
                    @php
                        $validCount = collect($importPreviewRows)->where('is_valid', true)->count();
                        $invalidCount = collect($importPreviewRows)->where('is_valid', false)->count();
                    @endphp

                    <div class="mt-4 flex flex-wrap gap-2 items-center">
                        <x-badge value="{{ count($importPreviewRows) }} dòng" class="badge-outline" />
                        <x-badge value="{{ $validCount }} hợp lệ" class="badge-success text-white" />
                        <x-badge value="{{ $invalidCount }} lỗi" class="badge-error text-white" />
                        <x-button label="Mở danh sách import" class="w-full btn-md" wire:click="$set('showImportSuccessModal', true)" />
                    </div>
                @endif
            </x-card>
        </div>

    </div>

    <x-modal wire:model="showImportSuccessModal" title="Danh sách import môn học" separator box-class="max-w-7xl">
        <div class="space-y-3 text-md">
            <div class="flex flex-wrap gap-2 items-center justify-between">
                <div class="flex flex-wrap gap-x-4 gap-y-2 items-center">
                    <x-badge value="{{ count($importPreviewRows) }} dòng" class="badge-outline" />
                    <x-badge value="{{ $importValidCount }} hợp lệ" class="badge-success text-white" />
                    <x-badge value="{{ $importInvalidCount }} lỗi" class="badge-error text-white" />
                    <x-badge value="{{ count($this->filteredImportPreviewRows) }} đang hiển thị" class="badge-ghost" />
                    <x-input
                        wire:model.live.debounce.300ms="importSearch"
                        placeholder="Nhập mã môn hoặc tên môn..."
                        clearable
                        class="w-full lg:w-96 flex"
                    />
                </div>
                <div class="flex gap-2">
                    <x-button label="Chọn tất cả hợp lệ" class="btn-md" wire:click="selectAllValidImportRows" />
                    <x-button label="Bỏ chọn" class="btn-md" wire:click="clearImportSelection" />
                </div>
            </div>

            <div class="overflow-x-auto border border-gray-200 rounded-md max-h-[60vh]">
                <table class="table table-zebra w-full">
                    <thead class="text-black">
                    <tr>
                        <th class="w-10"></th>
                        <th>STT</th>
                        <th>Mã học phần</th>
                        <th>Tên học phần</th>
                        <th>Tên Tiếng anh</th>
                        <th>Tổng Số TC</th>
                        <th>LT</th>
                        <th>TH</th>
                        <th>Nhóm môn</th>
                        <th>Trạng thái</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($this->filteredImportPreviewRows as $row)
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    value="{{ $row['key'] }}"
                                    wire:model="selectedImportRowKeys"
                                    @disabled(!$row['is_valid'])
                                >
                            </td>
                            <td>{{ $row['row_no'] }}</td>
                            <td>{{ $row['code'] ?: '—' }}</td>
                            <td>{{ $row['name_vi'] ?: '—' }}</td>
                            <td>{{ $row['name_en'] ?: '—' }}</td>
                            <td>{{ $row['credits'] ?? '—' }}</td>
                            <td>{{ $row['credits_theory'] ?? '—' }}</td>
                            <td>{{ $row['credits_practice'] ?? '—' }}</td>
                            <td>{{ $row['group_name'] ?: '—' }}</td>
                            <td>
                                @if($row['is_valid'])
                                    <span class="text-green-600">Hợp lệ</span>
                                @else
                                    <div class="text-red-600 text-md leading-5">
                                        {{ implode(' ', $row['errors']) }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-gray-500 py-4">Không có dòng nào khớp từ khóa tìm kiếm.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Đóng" class="btn-ghost" wire:click="$set('showImportSuccessModal', false)" />
            <x-button label="Lưu các dòng đã chọn" class="bg-primary text-white" wire:click="saveImportedSubjects" spinner="saveImportedSubjects" />
        </x-slot:actions>
    </x-modal>
</div>
