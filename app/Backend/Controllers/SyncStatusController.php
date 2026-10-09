<?php

namespace App\Backend\Controllers;

use App\Backend\Services\SyncSourcesService;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class SyncStatusController extends Controller
{
    /**
     * Whitelisted commands: key => [artisan_command, args_array]
     * args_array uses the same format as Artisan::call() second argument.
     */
    private const ALLOWED_COMMANDS = [
        'sync:news' => ['sync:news',              []],
        'sync:exchange-rates' => ['sync:exchange-rates',    []],
        'sync:hot-industries' => ['sync:hot-industries',    []],
        'sync:stock-prices' => ['sync:stock-prices',      []],
        'sync:stock-data' => ['sync:stock-data',        []],
        'sync:company-financials' => ['sync:company-financials', ['--stale' => true, '--dispatch' => true]],
        'sync:funds' => ['sync:funds',             []],
        'sync:etfs' => ['sync:etfs',              []],
        'sync:gold-prices' => ['sync:gold-prices',       []],
        'sync:market-overview' => ['sync:market-overview',   []],
        'sync:company-profiles' => ['sync:company-profiles',  ['--seed' => true, '--limit' => 50, '--dispatch' => true]],
        'sync:world-markets' => ['sync:world-markets',     []],
        'signals:build' => ['signals:build',          []],
    ];

    public function index(SyncSourcesService $sources): View
    {
        Gate::authorize('manage-features');

        return view('backend.sync-status.index', $sources->build());
    }

    /**
     * AJAX endpoint: trigger a whitelisted artisan command.
     */
    public function trigger(string $key): JsonResponse
    {
        Gate::authorize('manage-features');

        if (! isset(self::ALLOWED_COMMANDS[$key])) {
            return TransformerResponse::unprocessable('Unknown command.', extra: ['error' => 'Unknown command.']);
        }

        $command = self::ALLOWED_COMMANDS[$key];
        [$cmd, $args] = $command;

        try {
            Artisan::call($cmd, $args);
            $output = Artisan::output();

            ActivityLogger::log('admin_action', "Manual sync triggered: {$key}", ['output' => trim(mb_substr($output, 0, 300))]);

            return TransformerResponse::success(trim($output) ?: 'Sync hoàn tất.');
        } catch (Throwable $e) {
            report($e);

            $failure = 'Sync thất bại, xem chi tiết trong log hệ thống.';

            return TransformerResponse::serverError($failure, ['error' => $failure]);
        }
    }
}
