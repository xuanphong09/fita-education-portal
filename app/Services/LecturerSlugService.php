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
        $surname = $nameParts['surname'] ?? '';
        $middleName = $nameParts['middleName'] ?? '';
        $lastName = $nameParts['lastName'] ?? '';

        // middleParts: array of middle tokens (preserve original tokens if available)
        $middleParts = $nameParts['middleParts'] ?? [];
        if (empty($middleParts) && $middleName !== '') {
            // fallback: split by spaces (in case parseFullName provided single string)
            $middleParts = preg_split('/\s+/', $middleName);
        }

        // Build initials by taking first char of each middle token
        $middleInitials = '';
        foreach ($middleParts as $mp) {
            $middleInitials .= mb_substr($mp, 0, 1);
        }

        $firstSurnameChar = mb_substr($surname, 0, 1);
        $firstTwoSurnameChars = mb_substr($surname, 0, 2);

        return [
            // 1. first char of surname + initials of middle parts + last name
            $this->buildSlug($firstSurnameChar, $middleInitials, $lastName),
            // 2. first two chars of surname + initials of middle parts + last name
            $this->buildSlug($firstTwoSurnameChars, $middleInitials, $lastName),
            // 3. full surname + initials of middle parts + last name
            $this->buildSlug($surname, $middleInitials, $lastName),
            // 4. full surname + full middle name (concatenated) + last name
            $this->buildSlug($surname, implode('', $middleParts), $lastName),
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
        $parts = preg_split('/\s+/', $fullName); // normalize spaces

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
        // Keep middle parts as array to build initials
        $middleParts = $parts;
        $middleName = implode('', $parts); // Combine remaining parts for fallback

        return [
            'surname' => $surname,
            'middleName' => $middleName,
            'middleParts' => $middleParts,
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
        // Lowercase first so we can map only lowercase diacritics
        $slug = Str::lower($slug);
        // Remove Vietnamese diacritics
        $slug = $this->removeDiacritics($slug);
                // Normalize multiple spaces and trim
                $slug = preg_replace('/\s+/', '', $slug);
                // Keep only a-z and 0-9
                $slug = preg_replace('/[^a-z0-9]/', '', $slug);

                return $slug;
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
            'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
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

    /**
     * Generate slug from email prefix (before @). If taken, append -1, -2...
     *
     * @param string $email
     * @param ?int $excludeLecturerId
     * @return string
     */
    public function generateFromEmail(string $email, ?int $excludeLecturerId = null): string
    {
        $local = strtolower(explode('@', $email)[0] ?? '');
        // remove diacritics and non-alnum
        $local = $this->removeDiacritics($local);
        $local = preg_replace('/[^a-z0-9]/', '', strtolower($local));

        if ($local === '') {
            // fallback to name-based candidate
            $local = $this->buildSlug($nameParts['surname'] ?? '', $nameParts['middleName'] ?? '', $nameParts['lastName'] ?? '');
        }

        $candidate = $local;
        $i = 1;
        while (! $this->isSlugUnique($candidate, $excludeLecturerId)) {
            $candidate = $local . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}

