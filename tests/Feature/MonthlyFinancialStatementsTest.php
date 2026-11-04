<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\User;
use App\Services\MonthlyFinancialStatements;
use App\Services\MonthlyStatementPdf;
use App\Services\MonthlyStatementTemplate;
use App\Services\MonthlyStatementWorkbook;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyFinancialStatementsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $this->manager = User::factory()->create(['role' => 'manager']);
    }

    private function stay(int $amount = 10001, string $arrival = '2026-10-31 14:00', string $departure = '2026-11-02 12:00'): Stay
    {
        $room = Room::create(['number' => '101', 'floor_id' => Floor::create(['number' => 1])->id,
            'room_type_id' => RoomType::create(['name' => 'Standard'])->id, 'rate' => 5000]);

        return Stay::create(['room_id' => $room->id, 'guest_name' => 'Monthly guest', 'phone' => '09',
            'room_charge' => $amount, 'check_in_at' => $arrival, 'expected_check_out_at' => $departure,
            'check_out_at' => $departure, 'created_by' => $this->manager->id, 'created_at' => $arrival]);
    }

    private function report(string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfDay();
        $end = $start->copy()->addMonth();

        return app(MonthlyFinancialStatements::class)->build($start, $end->isFuture() ? now()->addSecond() : $end);
    }

    private function entry(string $debit, string $credit, int $amount, string $date = '2026-11-03', bool $opening = false, ?string $flow = null): void
    {
        DB::table('financial_entries')->insert([
            'entry_date' => $date, 'debit_account' => $debit, 'credit_account' => $credit,
            'amount' => $amount, 'is_opening' => $opening, 'cash_flow_category' => $flow,
            'description' => 'Adjustment', 'created_by' => $this->manager->id, 'created_at' => now(),
        ]);
    }

    public function test_earned_nights_split_across_months_while_cash_and_advances_reconcile(): void
    {
        $stay = $this->stay();
        Payment::create(['stay_id' => $stay->id, 'amount' => 10001, 'method' => 'cash', 'created_by' => $this->manager->id, 'created_at' => '2026-10-31 14:00']);
        Charge::create(['stay_id' => $stay->id, 'amount' => 3000, 'description' => 'Extra', 'created_by' => $this->manager->id, 'created_at' => '2026-11-01 10:00']);
        Expense::create(['expense_date' => '2026-11-01', 'category' => 'other', 'description' => 'Supplies', 'amount' => 1000, 'created_by' => $this->manager->id]);
        $stay->delete();

        $october = $this->report('2026-10');
        $november = $this->report('2026-11');

        $this->assertSame(5000, $october['earnedRevenue']);
        $this->assertSame(5001, $october['liabilities']);
        $this->assertSame(10001, $october['closingCash']);
        $this->assertSame(8001, $november['earnedRevenue']);
        $this->assertSame(7001, $november['profit']);
        $this->assertSame(10001, $november['openingCash']);
        $this->assertSame(9001, $november['closingCash']);
        $this->assertSame(12001, $november['assets']);
        $this->assertSame(0, $november['liabilities']);
        $this->assertSame(0, $october['balanceDifference']);
        $this->assertSame(0, $november['balanceDifference']);
        $this->assertSame(0, $november['cashDifference']);
    }

    public function test_opening_balances_non_cash_expenses_and_three_cash_flow_categories(): void
    {
        $this->entry('cash', 'opening_equity', 100000, '2026-11-01', true);
        $this->entry('cash', 'capital', 30000);
        $this->entry('cash', 'loans', 20000);
        $this->entry('fixed_assets', 'cash', 40000);
        $this->entry('operating_expense', 'payables', 7000);
        $this->entry('payables', 'cash', 2000);
        $this->entry('depreciation_expense', 'accumulated_depreciation', 500);
        $this->entry('drawings', 'cash', 1000);
        $this->entry('loans', 'cash', 5000);

        $report = $this->report('2026-11');

        $this->assertSame(-7500, $report['profit']);
        $this->assertSame(['operating' => -2000, 'investing' => -40000, 'financing' => 44000], $report['flows']);
        $this->assertSame(100000, $report['openingCash']);
        $this->assertSame(102000, $report['closingCash']);
        $this->assertSame(2000, $report['netCash']);
        $this->assertSame(141500, $report['assets']);
        $this->assertSame(20000, $report['liabilities']);
        $this->assertSame(121500, $report['equity']);
        $this->assertSame(0, $report['balanceDifference']);
        $this->assertSame(0, $report['cashDifference']);
        $this->assertTrue($report['hasOpeningEntries']);
        $sheets = app(MonthlyStatementWorkbook::class)->preview(['month' => '2026-11', 'comparisonMonth' => '2026-10', 'financials' => $report, 'comparativeFinancials' => $this->report('2026-10')]);
        $find = static fn (array $rows, string $label): array => array_values(array_filter($rows, fn ($row) => $row['label'] === $label))[0]['values'];
        $this->assertSame([-7500, 0], $find($sheets[0]['rows'], 'Profit/(Loss) for the period'));
        $this->assertSame([141500, 0], $find($sheets[1]['rows'], 'Total Assets'));
        $this->assertSame([141500, 0], $find($sheets[1]['rows'], 'Total liabilities and equity'));
        $this->assertSame([15000, 0], $find($sheets[1]['rows'], 'Borrowings (maturity unclassified)'));
        $this->assertSame([130000, -8500, 121500], $find($sheets[2]['rows'], 'Balance at end of the month'));
        $this->assertSame([-2000, 0], $find($sheets[3]['rows'], 'Net cash provided by (used in) operating activities'));
        $this->assertSame([-40000, 0], $find($sheets[3]['rows'], 'Net cash provided by (used in) investing activities'));
        $this->assertSame([44000, 0], $find($sheets[3]['rows'], 'Net cash provided by (used in) financing activities'));
        $this->assertSame([102000, 0], $find($sheets[3]['rows'], 'Cash and cash equivalents at end of month'));

    }

    public function test_current_month_excludes_future_payments_charges_and_expenses(): void
    {
        $stay = $this->stay(30000, '2026-11-14 14:00', '2026-11-17 12:00');
        $stay->update(['check_out_at' => null]);
        Charge::create(['stay_id' => $stay->id, 'amount' => 9000, 'description' => 'Future', 'created_by' => $this->manager->id, 'created_at' => '2026-11-20 12:00']);
        Payment::create(['stay_id' => $stay->id, 'amount' => 10000, 'method' => 'cash', 'created_by' => $this->manager->id, 'created_at' => '2026-11-20 12:00']);
        Expense::create(['expense_date' => '2026-11-20', 'category' => 'other', 'description' => 'Future', 'amount' => 4000, 'created_by' => $this->manager->id]);

        $report = $this->report('2026-11');

        $this->assertSame(10000, $report['earnedRevenue']);
        $this->assertSame(10000, $report['profit']);
        $this->assertSame(0, $report['closingCash']);
        $this->assertSame(10000, $report['assets']);
        $this->assertSame(0, $report['balanceDifference']);
    }

    public function test_rounding_remainder_is_earned_in_the_final_month(): void
    {
        $this->stay(10000, '2026-10-30 14:00', '2026-11-02 12:00');

        $this->assertSame(6666, $this->report('2026-10')['earnedRevenue']);
        $this->assertSame(3334, $this->report('2026-11')['earnedRevenue']);
    }

    public function test_completed_same_day_stays_are_fully_earned(): void
    {
        $this->stay(5000, '2026-11-15 07:00', '2026-11-15 09:00');

        $this->assertSame(5000, $this->report('2026-11')['earnedRevenue']);
    }

    public function test_explicit_cash_classification_and_reversals_reconcile(): void
    {
        $this->entry('other_assets', 'cash', 1000, flow: 'investing');
        $this->entry('cash', 'other_assets', 1000, flow: 'investing');
        $report = $this->report('2026-11');

        $this->assertSame(0, $report['flows']['investing']);
        $this->assertSame(0, $report['assets']);
        $this->assertSame(0, $report['cashDifference']);
    }

    public function test_journal_entries_are_manager_only_validated_and_audited(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $entry = ['entry_date' => '2026-11-03', 'debit_account' => 'cash', 'credit_account' => 'capital', 'amount' => 20000, 'description' => 'Owner capital'];
        $this->actingAs($staff)->post(route('financial-entries.store'), $entry)->assertForbidden();
        $this->actingAs($this->manager);
        $this->post(route('financial-entries.store'), [...$entry, 'credit_account' => 'cash'])->assertSessionHasErrors('credit_account');
        $this->post(route('financial-entries.store'), [...$entry, 'amount' => -1])->assertSessionHasErrors('amount');
        $this->post(route('financial-entries.store'), [...$entry, 'debit_account' => 'invalid'])->assertSessionHasErrors('debit_account');
        $this->post(route('financial-entries.store'), [...$entry, 'entry_date' => '2026-11-16'])->assertSessionHasErrors('entry_date');
        $this->post(route('financial-entries.store'), [...$entry, 'is_opening' => 1])->assertSessionHasErrors('is_opening');
        $this->post(route('financial-entries.store'), [...$entry, 'is_opening' => 1, 'entry_date' => '2026-11-01', 'credit_account' => 'revenue'])->assertSessionHasErrors('is_opening');
        $this->assertDatabaseCount('financial_entries', 0);
        $this->post(route('financial-entries.store'), $entry)->assertRedirect(route('reports.monthly', ['month' => '2026-11']));
        $this->assertDatabaseCount('financial_entries', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'financial_entry.created', 'user_id' => $this->manager->id]);
    }

    public function test_monthly_template_compares_the_selected_month_to_the_previous_month(): void
    {
        $this->stay(10000, '2026-10-30 14:00', '2026-11-02 12:00');
        $this->actingAs($this->manager)->get(route('reports.monthly', ['month' => '2026-11']))
            ->assertOk()->assertViewHas('comparisonMonth', '2026-10')
            ->assertViewHas('financials', null)
            ->assertViewHas('comparativeFinancials', null)
            ->assertDontSee('standard-statement-table', false)
            ->assertSee(route('reports.monthly.statements', ['month' => '2026-11']), false);
        $csv = $this->get(route('reports.monthly.export', ['month' => '2026-11']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10', $csv);
        $this->assertStringContainsString('3334,6666,MMK', $csv);
    }

    public function test_monthly_page_offers_download_and_csv_includes_all_three_statements_and_safe_journal_text(): void
    {
        $this->actingAs($this->manager);
        $this->post(route('financial-entries.store'), ['entry_date' => '2026-11-01', 'debit_account' => 'cash', 'credit_account' => 'opening_equity', 'amount' => 9000, 'is_opening' => 1, 'description' => '=SUM(A1:A2)'])->assertSessionHasNoErrors();
        $this->get(route('reports.monthly', ['month' => '2026-11']))->assertOk()
            ->assertSee('Balance Sheet')->assertSee('P&amp;L', false)->assertSee('Cash Flow')
            ->assertDontSee('standard-statement-table', false);
        $csv = $this->get(route('reports.monthly.export', ['month' => '2026-11']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Balance Sheet', $csv);
        $this->assertStringContainsString('P&L', $csv);
        $this->assertStringContainsString('Cash Flow', $csv);
        $this->assertStringContainsString("'=SUM(A1:A2)", $csv);
    }

    public function test_download_preserves_the_supplied_template_and_populates_comparative_amounts_without_reference_errors(): void
    {
        $this->stay(10000, '2026-10-30 14:00', '2026-11-02 12:00');
        config(['app.name' => '=Example & <Motel>']);
        $response = $this->actingAs($this->manager)
            ->get(route('reports.monthly.statements', ['month' => '2026-11']))
            ->assertOk()->assertDownload('motel-financial-statements-2026-11.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (! preg_match('/\.(xml|rels)$/', $zip->getNameIndex($index))) {
                    continue;
                }
                $xml = $zip->getFromIndex($index);
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($xml), $zip->getNameIndex($index));
            }
            $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
            $this->assertSame(['pl', 'bs', 'equity', 'cashflow', 'notes'], array_map(fn ($sheet) => (string) $sheet['name'], iterator_to_array($workbook->sheets->sheet, false)));
            $income = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $income->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('3334', (string) $income->xpath('//s:c[@r="G10"]/s:v')[0]);
            $this->assertSame('6666', (string) $income->xpath('//s:c[@r="I10"]/s:v')[0]);
            $this->assertSame('SUM(G28:G30)', (string) $income->xpath('//s:c[@r="G32"]/s:f')[0]);
            $this->assertSame('n.a.', (string) $income->xpath('//s:c[@r="G26"]/s:is/s:t')[0]);
            $this->assertSame('1', (string) $income->xpath('//s:col[@min="3"]')[0]['hidden']);
            $original = new \ZipArchive;
            $original->open(resource_path('templates/financial-statements.xlsx'));
            for ($sheet = 1; $sheet <= 4; $sheet++) {
                $xml = $zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml');
                $this->assertStringNotContainsString('#REF!', $xml);
                $this->assertStringNotContainsString('notes!', $xml);
                $this->assertStringNotContainsString('2025-2026', $xml);
                $this->assertStringContainsString('=Example &amp; &lt;Motel&gt;', $xml);
                $this->assertStringContainsString('MMK', $xml);
                $document = simplexml_load_string($xml);
                $source = simplexml_load_string($original->getFromName('xl/worksheets/sheet'.$sheet.'.xml'));
                $this->assertSame($source->cols->asXML(), $document->cols->asXML());
            }
            $equity = simplexml_load_string($zip->getFromName('xl/worksheets/sheet3.xml'));
            $equity->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('Share capital', (string) $equity->xpath('//s:c[@r="C9"]/s:is/s:t')[0]);
            $original->close();
            $notes = $zip->getFromName('xl/worksheets/sheet5.xml');
            $this->assertStringContainsString('Opening balances are missing', $notes);
            $this->assertStringContainsString('No income tax or exchange gain has been estimated', $notes);
            $this->assertStringContainsString('style="double"', $zip->getFromName('xl/styles.xml'));
        } finally {
            $zip->close();
            @unlink($path);
        }
    }

    public function test_statement_download_is_manager_only_and_validates_the_selected_month(): void
    {
        $url = route('reports.monthly.statements');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get($url)->assertForbidden();
        $this->actingAs($this->manager)->get($url.'?month=invalid')->assertSessionHasErrors('month');
        $this->get($url.'?month=2026-12')->assertSessionHasErrors('month');
    }

    public function test_pdf_download_has_pdf_content_and_manager_authorization(): void
    {
        $url = route('reports.monthly.pdf', ['month' => '2026-11']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get($url)->assertForbidden();
        $this->actingAs($this->manager)->get(route('reports.monthly.pdf', ['month' => 'invalid']))->assertSessionHasErrors('month');
        $this->get(route('reports.monthly.pdf', ['month' => '2026-12']))->assertSessionHasErrors('month');
        $renderer = $this->mock(MonthlyStatementPdf::class);
        $path = tempnam(sys_get_temp_dir(), 'motel-pdf-test-');
        file_put_contents($path, '%PDF-1.7 test');
        $renderer->shouldReceive('browserPath')->once()->andReturn('/test/chrome');
        $renderer->shouldReceive('create')->once()->withArgs(fn ($report) => $report['month'] === '2026-11' && count($report['financials']['statements']) === 3)->andReturn($path);
        try {
            $response = $this->get($url)->assertOk()->assertDownload('motel-financial-statements-2026-11.pdf')->assertHeader('Content-Type', 'application/pdf');
            $this->assertSame('%PDF-', file_get_contents($response->baseResponse->getFile()->getPathname(), false, null, 0, 5));
            $this->get(route('reports.monthly', ['month' => '2026-11']))->assertSee($url, false)->assertDontSee('standard-statement-table', false);
        } finally {
            @unlink($path);
        }
    }

    public function test_pdf_template_is_self_contained_and_includes_all_three_comparative_statements(): void
    {
        $current = $this->report('2026-11');
        $prior = $this->report('2026-10');
        config(['app.name' => '<script>bad()</script>']);
        $html = view('reports.financial-pdf', ['month' => '2026-11', 'comparisonMonth' => '2026-10', 'financials' => $current, 'comparativeFinancials' => $prior, 'embeddedFont' => 'test-font', 'templateSheets' => app(MonthlyStatementWorkbook::class)->preview(['month' => '2026-11', 'comparisonMonth' => '2026-10', 'financials' => $current, 'comparativeFinancials' => $prior]), 'templateNotes' => app(MonthlyStatementTemplate::class)->notes(['month' => '2026-11', 'comparisonMonth' => '2026-10', 'financials' => $current, 'comparativeFinancials' => $prior])])->render();
        $this->assertStringContainsString('STATEMENT OF FINANCIAL POSITION', $html);
        $this->assertStringContainsString('STATEMENT OF PROFIT OR LOSS', $html);
        $this->assertStringContainsString('STATEMENT OF CASH FLOWS', $html);
        $this->assertStringContainsString('STATEMENT OF CHANGES IN EQUITY', $html);
        $this->assertStringContainsString('2026-10', $html);
        $this->assertStringContainsString('data:font/ttf;base64,test-font', $html);
        $this->assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_pdf_uses_printable_template_when_hosting_has_no_browser(): void
    {
        config(['services.pdf.chrome_binary' => '/missing-chrome-for-test']);
        $this->actingAs($this->manager)->get(route('reports.monthly.pdf', ['month' => '2026-11']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Print / Save as PDF')->assertSee('STATEMENT OF FINANCIAL POSITION')->assertSee('STATEMENT OF PROFIT OR LOSS')->assertSee('STATEMENT OF CASH FLOWS')->assertSee('script-src');
    }

    public function test_template_separates_recorded_salary_without_double_counting_operating_expenses(): void
    {
        foreach (['salary' => 2000, 'other' => 3000] as $category => $amount) {
            Expense::create(['expense_date' => '2026-11-02', 'category' => $category, 'description' => 'Monthly expense', 'amount' => $amount, 'created_by' => $this->manager->id]);
        }
        $report = $this->report('2026-11');
        $sheets = app(MonthlyStatementWorkbook::class)->preview(['month' => '2026-11', 'comparisonMonth' => '2026-10', 'financials' => $report, 'comparativeFinancials' => $this->report('2026-10')]);
        $find = static fn (string $label): array => array_values(array_filter($sheets[0]['rows'], fn ($row) => $row['label'] === $label))[0]['values'];
        $this->assertSame([-2000, 0], $find('Staff cost'));
        $this->assertSame([-3000, 0], $find('General and administrative expenses'));
        $this->assertSame([-5000, 0], $find('Profit/(Loss) for the period'));
    }

    public function test_template_shows_non_cash_and_classification_adjustments_without_hiding_reconciliation(): void
    {
        $this->entry('fixed_assets', 'payables', 4000);
        $this->entry('operating_expense', 'cash', 600, flow: 'financing');
        $report = $this->report('2026-11');
        $sheets = app(MonthlyStatementWorkbook::class)->preview(['month' => '2026-11', 'comparisonMonth' => '2026-10', 'financials' => $report, 'comparativeFinancials' => $this->report('2026-10')]);
        $find = static fn (string $label): array => array_values(array_filter($sheets[3]['rows'], fn ($row) => $row['label'] === $label))[0]['values'];
        $this->assertSame([-3400, 0], $find('Other non-cash / reclassification adjustments'));
        $this->assertSame([0, 0], $find('Net cash provided by (used in) operating activities'));
        $this->assertSame([-600, 0], $find('Other financing cash flows'));
        $this->assertSame([-600, 0], $find('Cash and cash equivalents at end of month'));
        $labels = array_column($sheets[1]['rows'], 'label');
        $this->assertLessThan(array_search('Total Assets', $labels, true), array_search('Other assets (classification pending)', $labels, true));
        $labels = array_column($sheets[3]['rows'], 'label');
        $this->assertSame('Cash and cash equivalents at end of month', end($labels));
    }
}
