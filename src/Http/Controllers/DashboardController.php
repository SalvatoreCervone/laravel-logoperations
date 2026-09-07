<?php

namespace SalvatoreCervone\LogOperations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use SalvatoreCervone\LogOperations\Http\Controllers\Concerns\AuthorizesLogOperations;

class DashboardController extends Controller
{
    use AuthorizesLogOperations;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->authorizeAccess($request);
            return $next($request);
        });
    }

    /**
     * Renderizza la Dashboard Web Standalone per la visualizzazione e analisi dei log.
     */
    public function index(Request $request): View
    {
        if (!config('logoperations.dashboard.enabled', true)) {
            abort(404, 'LogOperations Dashboard disabilitata.');
        }

        $apiPrefix = config('logoperations.api_prefix', 'api/logoperations');
        $perPage = (int) config('logoperations.dashboard.per_page', 20);
        $perPageOptions = config('logoperations.dashboard.per_page_options', [15, 20, 25, 50, 100]);
        $appName = config('logoperations.app_name', 'laravel');

        return view('logoperations::dashboard', compact(
            'apiPrefix',
            'perPage',
            'perPageOptions',
            'appName'
        ));
    }
}
