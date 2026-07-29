<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\Lecturer;

class LecturerSlugService
{
    /**
     * Generate slug for lecturer based on full name.
     * Rules:
     * 1. First letter of surname + first letter of middle name + last name
     * 2. If exists: First 2 letters of surname + first letter of middle name + last name
     * 3. If still exists: Full surname + first letter of middle name + last name
     * 4. If still exists: Full surname + middle name + last name
     *
     * Example: Ngô Công Thắng
     * 1. ncthang (n=Ngô, c=Công, thang=Thắng)
     * 2. ngcthang (ng=Ngô, c=Công, thang=Thắng)
     * 3. ngocthang (ngo=Ngô, c=Công, thang=Thắng)
     * 4. ngocongthang (ngo=Ngô, cong=Công, thang=Thắng)
     *
     * @param string $fullName
     * @param ?int $excludeLecturerId
     * @return string
     */
    public function generateSlug(string $fullName, ?int $excludeLecturerId = null): string
    {
        $nameParts = $this->parseFullName($fullName);

        $candidates = $this->generateCandidates($nameParts);

        foreach ($candidates as $candidate) {
            if ($this->isSlugUnique($candidate, $excludeLecturerId)) {
                return $candidate;
            }
        }

        // Fallback: if all are taken, append timestamp
        return end($candidates) . '-' . time();
    }

    /**
     * Generate all candidate slugs in order of preference.
     *
     * @param array $nameParts
     * @return array
     */
    public function generateCandidates(array $nameParts): array
    {
        return [
            $this->buildSlug($nameParts['surname'][0] ?? '', $nameParts['middleName'][0] ?? '', $nameParts['lastName']),
            $this->buildSlug(substr($nameParts['surname'], 0, 2), $nameParts['middleName'][0] ?? '', $nameParts['lastName']),
            $this->buildSlug($nameParts['surname'], $nameParts['middleName'][0] ?? '', $nameParts['lastName']),
            $this->buildSlug($nameParts['surname'], $nameParts['middleName'], $nameParts['lastName']),
        ];
    }

    /**
     * Parse full name into surname, middle name, and last name.
     * Assumes Vietnamese naming convention: Surname Middle-name(s) Last-name
     *
     * @param string $fullName
     * @return array
     */
    public function parseFullName(string $fullName): array
    {
        $fullName = trim($fullName);
        $parts = preg_split('/\s+/', $fullName);

        if (count($parts) < 2) {
            return [
                'surname' => $parts[0] ?? '',
                'middleName' => '',
                'lastName' => '',
            ];
        }

        if (count($parts) === 2) {
            return [
                'surname' => $parts[0],
                'middleName' => '',
                'lastName' => $parts[1],
            ];
        }

        // More than 2 parts: first is surname, last is lastName, middle are in between
        $surname = array_shift($parts);
        $lastName = array_pop($parts);
        $middleName = implode('', $parts); // Combine remaining parts

        return [
            'surname' => $surname,
            'middleName' => $middleName,
            'lastName' => $lastName,
        ];
    }

    /**
     * Build slug from surname, middle name, and last name.
     *
     * @param string $surname
     * @param string $middleName
     * @param string $lastName
     * @return string
     */
    public function buildSlug(string $surname, string $middleName, string $lastName): string
    {
        $slug = $surname . $middleName . $lastName;
        // Convert to lowercase and remove Vietnamese diacritics
        $slug = $this->removeDiacritics($slug);

        return Str::lower($slug);
    }

    /**
     * Remove Vietnamese diacritics from string.
     *
     * @param string $str
     * @return string
     */
    public function removeDiacritics(string $str): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', '�ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ];

        return strtr($str, $map);
    }

    /**
     * Check if slug is unique in lecturers table.
     *
     * @param string $slug
     * @param ?int $excludeLecturerId
     * @return bool
     */
    private function isSlugUnique(string $slug, ?int $excludeLecturerId = null): bool
    {
        $query = Lecturer::where('slug', $slug);

        if ($excludeLecturerId) {
            $query->where('id', '!=', $excludeLecturerId);
        }

        return $query->doesntExist();
    }
}
