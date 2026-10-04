<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

class ReferenceNumberGenerator
{
    /** Format: REF-YYDDMM-<n>, unique, sequential per day. */
    public static function generate(): string
    {
        $now = Carbon::now('Asia/Manila');
        $yy = $now->format('y');
        $dd = $now->format('d');
        $mm = $now->format('m');

        $count = Transaction::whereDate('dateBorrowed', $now->toDateString())->count();
        $num = $count + 1;
        $ref = sprintf('REF-%s%s%s-%d', $yy, $dd, $mm, $num);

        while (Transaction::where('refnum', $ref)->exists()) {
            $num++;
            $ref = sprintf('REF-%s%s%s-%d', $yy, $dd, $mm, $num);
        }

        return $ref;
    }
}
