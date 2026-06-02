@php
    $record = $getRecord();

    // Use lines_processed/total_records for smooth 0-100% during import (file progress).
    // Fall back to records for backward compat when lines_processed is null.
    $pct = match (true) {
        $record->total_records > 0 && ($record->lines_processed ?? $record->records_processed) > 0
            => min(100, (int) round(($record->lines_processed ?? $record->records_processed) / $record->total_records * 100)),
        $record->status === 'finished' => 100,
        default => (int) $record->progress,
    };

    [$barBg, $trackBg] = match (true) {
        $record->status === 'finished'                                        => ['bg-success-500', 'bg-success-100 dark:bg-success-900/40'],
        $record->status === 'failed'                                          => ['bg-danger-500',  'bg-danger-100 dark:bg-danger-900/40'],
        in_array($record->status, ['downloading','decompressing','importing']) => ['bg-primary-500', 'bg-primary-100 dark:bg-primary-900/40'],
        default                                                               => ['bg-gray-300',    'bg-gray-100 dark:bg-gray-800'],
    };

    $isAnimated = $record->isRunning() && $pct < 100;
@endphp

<div class="flex items-center gap-2 min-w-[140px]">
    {{-- Spinner shown while any job step is active --}}
    @if ($isAnimated)
        <svg class="shrink-0 w-3.5 h-3.5 text-primary-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
        </svg>
    @endif

    <div class="relative flex-1 h-2 {{ $trackBg }} rounded-full overflow-hidden">
        <div
            class="{{ $barBg }} h-2 rounded-full transition-all duration-700"
            style="width: {{ $pct }}%"
        ></div>
        @if ($isAnimated)
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-[shimmer_1.5s_infinite]"></div>
        @endif
    </div>

    <span class="w-9 shrink-0 text-right text-xs font-semibold tabular-nums
        {{ $record->status === 'finished' ? 'text-success-600 dark:text-success-400' : ($record->isRunning() ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400') }}">
        {{ $pct }}%
    </span>
</div>
