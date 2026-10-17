<?php

namespace App\Services;

use App\Models\SchoolRecord;

class StudentNumber
{
    /** Called inside the admission transaction, independently of PPDB references. */
    public static function next(string $year): string
    {
        $prefix = 'SCN-'.substr($year, 0, 4).'-';
        $last = 0;
        foreach (SchoolRecord::query()->get() as $record) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/D', (string) $record->code, $match)) {
                $last = max($last, (int) $match[1]);
            }
        }

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
