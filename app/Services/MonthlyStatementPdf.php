<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class MonthlyStatementPdf
{
    public function create(array $report): string
    {
        $binary = $this->browserPath();
        abort_unless($binary, 503, __('PDF ထုတ်ရန် server တွင် Chrome / Chromium သတ်မှတ်ပေးရန် လိုအပ်ပါသည်။'));
        $directory = sys_get_temp_dir().'/motel-pdf-'.bin2hex(random_bytes(16));
        File::makeDirectory($directory, 0700);
        $output = tempnam(sys_get_temp_dir(), 'motel-statement-pdf-');
        if ($output === false) {
            File::deleteDirectory($directory);
            throw new RuntimeException('Unable to create PDF download.');
        }
        try {
            $font = base64_encode(File::get(public_path('fonts/noto-sans-myanmar-variable.ttf')));
            File::put($directory.'/statement.html', view('reports.financial-pdf', [...$report, 'embeddedFont' => $font, 'templateSheets' => app(MonthlyStatementWorkbook::class)->preview($report), 'templateNotes' => app(MonthlyStatementTemplate::class)->notes($report)])->render());
            $process = new Process([
                $binary, '--headless', '--disable-gpu', '--disable-extensions', '--disable-background-networking',
                '--no-first-run', '--no-default-browser-check', '--no-pdf-header-footer',
                '--user-data-dir='.$directory.'/browser', '--print-to-pdf='.$directory.'/statement.pdf',
                'file://'.$directory.'/statement.html',
            ]);
            $process->setTimeout(45);
            $process->run();
            if (! $process->isSuccessful() || ! is_file($directory.'/statement.pdf')) {
                throw new RuntimeException('The PDF renderer could not finish the statement.');
            }
            File::move($directory.'/statement.pdf', $output);
            if (file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
                throw new RuntimeException('The PDF renderer returned an invalid document.');
            }

            return $output;
        } catch (Throwable $exception) {
            File::delete($output);
            report($exception);
            abort(503, __('PDF ထုတ်ခြင်း မအောင်မြင်ပါ။ ခဏအကြာတွင် ပြန်ကြိုးစားပါ။'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function browserPath(): ?string
    {
        $configured = config('services.pdf.chrome_binary');
        $paths = $configured ? [$configured] : [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        ];
        foreach ($paths as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
