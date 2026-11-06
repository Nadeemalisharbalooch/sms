<?php

namespace App\Jobs;

use App\Models\FeeVoucher;
use App\Models\Institute;
use App\Models\User;
use App\Models\VoucherPdfExport;
use App\Notifications\VoucherPdfReadyNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateVoucherPdfExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $exportId) {}

    public function handle(): void
    {
        $export = VoucherPdfExport::query()->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);
        $filters = $export->filters;

        $vouchers = FeeVoucher::query()
            ->where('institute_id', $export->institute_id)
            ->where('session_id', $filters['session_id'])
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->where('billing_month', $month))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status === 'partially_paid' ? 'partial' : $status))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when(($filters['class_id'] ?? null) || ($filters['section_id'] ?? null), fn ($query) => $query->whereHas('student.enrollments', function ($query) use ($filters, $export) {
                $query->where('session_id', $filters['session_id'])
                    ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
                    ->when($filters['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id));
            }))
            ->when(trim($filters['search'] ?? '') !== '', fn ($query) => $query->whereHas('student', function ($query) use ($filters, $export) {
                $search = trim($filters['search']);
                $query->where(fn ($query) => $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereHas('enrollments', fn ($enrollment) => $enrollment->where('session_id', $filters['session_id'])->where('roll_number', 'like', '%'.$search.'%')));
            }))
            ->with(['student.enrollments' => fn ($query) => $query->where('session_id', $filters['session_id'])->with(['academicClass', 'section']), 'items', 'session'])
            ->orderBy('student_id')->orderBy('id')->get();

        if ($vouchers->isEmpty()) {
            $export->update(['status' => 'failed', 'error_message' => 'No vouchers match the selected filters.']);
            return;
        }

        $institute = Institute::query()->findOrFail($export->institute_id);
        $pdf = Pdf::loadView('pdf.fee-vouchers', ['vouchers' => $vouchers, 'institute' => $institute])
            ->setPaper('a4', 'portrait');
        $path = 'voucher-exports/'.$export->institute_id.'/'.$export->id.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());
        $export->update(['status' => 'ready', 'file_path' => $path, 'error_message' => null]);

        User::query()->find($export->user_id)?->notify(new VoucherPdfReadyNotification($export));
    }

    public function failed(Throwable $exception): void
    {
        VoucherPdfExport::query()->whereKey($this->exportId)->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }
}
