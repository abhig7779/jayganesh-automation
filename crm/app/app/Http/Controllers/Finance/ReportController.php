<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\Period;
use App\Services\Finance\ReportBuilder;
use App\Services\Finance\SalesQueries;
use App\Services\Finance\XlsxExport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const DEFAULT_PERIOD = ['pnl' => 'this_month', 'ca' => 'last_month', 'products' => 'this_month', 'gst' => 'last_month'];

    public function __construct(private ReportBuilder $builder) {}

    public function index(Request $request)
    {
        [$key, $period] = $this->resolve($request);
        $installed = SalesQueries::installed();
        $blocked = ReportBuilder::needsBilling($key) && ! $installed;

        return view('reports.index', [
            'key' => $key,
            'reports' => ReportBuilder::available($request->user()),
            'period' => $period,
            'installed' => $installed,
            'blocked' => $blocked,
            'report' => $blocked ? null : $this->builder->build($key, $period, $request->user(), $request->user()->branch_id),
        ]);
    }

    public function export(Request $request)
    {
        [$key, $period] = $this->resolve($request);
        abort_if(ReportBuilder::needsBilling($key) && ! SalesQueries::installed(), 404, 'Bills module not installed yet.');
        abort_if($key === 'ca', 404);

        $report = $this->builder->build($key, $period, $request->user(), $request->user()->branch_id);

        return XlsxExport::download($report, 'jay-ganesh-'.$key.'-'.$period->fileStamp().'.xlsx');
    }

    /** Tally / CA registers: sales register and expense register (finance.view, see routes). */
    public function register(Request $request, string $type)
    {
        abort_unless($request->user()->can('finance.view'), 403);
        $period = Period::fromRequest($request, 'last_month');
        if ($type === 'sales' && ! SalesQueries::installed()) {
            return back()->withErrors(['register' => 'Bills module not installed yet, so there is no sales register.']);
        }
        $report = $this->builder->register($type, $period, $request->user()->branch_id);

        return XlsxExport::download($report, 'jay-ganesh-'.$type.'-register-'.$period->fileStamp().'.xlsx');
    }

    /** @return array{0:string, 1:Period} */
    private function resolve(Request $request): array
    {
        $available = ReportBuilder::available($request->user());
        $key = (string) $request->query('report', array_key_first($available) ?? 'daily');
        abort_unless(array_key_exists($key, ReportBuilder::REPORTS), 404);
        abort_unless(array_key_exists($key, $available), 403);

        return [$key, Period::fromRequest($request, self::DEFAULT_PERIOD[$key] ?? 'today')];
    }
}
