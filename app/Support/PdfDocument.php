<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class PdfDocument
{
    public static function download(string $view, array $data, string $filename)
    {
        $cache = storage_path('app/pdf');
        File::ensureDirectoryExists($cache);
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'fontCache' => $cache, 'tempDir' => $cache, 'chroot' => public_path()]));
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.preg_replace('/[^a-zA-Z0-9_.-]/', '-', $filename).'"', 'Cache-Control' => 'private, no-store']);
    }
}
