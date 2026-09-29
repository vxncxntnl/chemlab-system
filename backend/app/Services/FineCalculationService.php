<?php

namespace App\Services;

use App\Models\Borrowing;
use Carbon\Carbon;

class FineCalculationService
{
    public function updateAllOverdueBorrowings(): int
    {
        $count = Borrowing::whereIn('status', ['approved', 'borrowed'])
            ->where('return_date', '<', Carbon::today())
            ->update(['status' => 'overdue', 'overdue_flag' => true]);

        return $count;
    }
}
