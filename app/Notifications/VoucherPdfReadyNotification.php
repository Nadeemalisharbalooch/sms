<?php

namespace App\Notifications;

use App\Models\VoucherPdfExport;

class VoucherPdfReadyNotification extends BaseNotification
{
    public function __construct(private readonly VoucherPdfExport $export) {}

    public function title(): string { return 'Voucher PDF is ready'; }
    public function summary(): string { return 'Your filtered voucher PDF is ready to download. It includes institute and candidate copies.'; }
    public function priority(): string { return 'standard'; }
    public function category(): string { return 'billing'; }
    public function actionText(): ?string { return 'Download PDF'; }
    public function actionUrl(): ?string { return url('/api/institutes/fees/vouchers/exports/'.$this->export->id.'/download'); }
    public function payload(): array { return ['export_id' => $this->export->id, 'status' => 'ready', 'download_url' => $this->actionUrl()]; }
}
