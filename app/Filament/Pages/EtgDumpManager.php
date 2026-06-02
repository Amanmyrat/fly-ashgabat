<?php

namespace App\Filament\Pages;

use App\Jobs\ETG\StartDumpJob;
use App\Models\EtgDumpStatus;
use App\Services\ETG\Dumps\HotelDumpImporter;
use App\Services\ETG\Dumps\RegionDumpImporter;
use App\Services\ETG\Dumps\ReviewDumpImporter;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class EtgDumpManager extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationGroup = 'ETG';
    protected static ?string $navigationLabel = 'Dump Manager';
    protected static ?string $title           = 'ETG Dump Manager';
    protected static ?int    $navigationSort  = 50;

    protected static string $view = 'filament.pages.etg-dump-manager';

    public function mount(): void
    {
        EtgDumpStatus::forTypeAndLanguage('hotel', 'en');
        EtgDumpStatus::forTypeAndLanguage('hotel', 'ru');
        EtgDumpStatus::forTypeAndLanguage('region', 'en');
        EtgDumpStatus::forTypeAndLanguage('review', 'en');

        // Remove legacy region/ru rows created before the importer was
        // simplified to a single multilingual pass.
        EtgDumpStatus::where('type', 'region')
            ->where('language', '!=', 'en')
            ->delete();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EtgDumpStatus::query()->orderBy('type')->orderBy('language'))
            ->columns([

                Tables\Columns\TextColumn::make('type')
                    ->label('Dump')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, ?EtgDumpStatus $record): string
                        => $this->rowLabel($record))
                    ->color(fn (?string $state): string => match ($state) {
                        'hotel'  => 'info',
                        'region' => 'success',
                        'review' => 'warning',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'idle'          => 'gray',
                        'downloading'   => 'primary',
                        'decompressing' => 'primary',
                        'importing'     => 'primary',
                        'finished'      => 'success',
                        'failed'        => 'danger',
                        default         => 'gray',
                    })
                    ->description(fn (?EtgDumpStatus $record): ?string => $record?->status === 'failed'
                        ? $record->error_message
                        : null
                    ),

                Tables\Columns\ViewColumn::make('progress')
                    ->label('Progress')
                    ->view('filament.columns.dump-progress'),

                Tables\Columns\TextColumn::make('records_processed')
                    ->label('Records')
                    ->formatStateUsing(function (?int $state, ?EtgDumpStatus $record): string {
                        $inserted = $state !== null ? number_format($state) : null;
                        $totalLines = $record?->total_records ? number_format($record->total_records) : null;
                        $linesDone = ($record?->lines_processed !== null) ? number_format($record->lines_processed) : null;

                        if (!$totalLines && !$inserted) {
                            return '—';
                        }
                        // During import: "X inserted (Z / Y lines)"
                        if ($record?->status === 'importing' && $linesDone !== null && $totalLines) {
                            return ($inserted ?? '0') . " inserted ({$linesDone} / {$totalLines} lines)";
                        }
                        // Finished: "X inserted of Y total"
                        if ($totalLines) {
                            return ($inserted ?? '0') . " of {$totalLines}";
                        }
                        return $inserted ?? '—';
                    }),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('File Size')
                    ->formatStateUsing(function (?int $state, ?EtgDumpStatus $record): string {
                        if (!$state || $record?->status !== 'downloading') {
                            return '—';
                        }
                        $downloaded = $record->downloaded_bytes
                            ? Number::fileSize($record->downloaded_bytes) . ' / '
                            : '';

                        return $downloaded . Number::fileSize($state);
                    }),

                Tables\Columns\TextColumn::make('last_update')
                    ->label('Last Import')
                    ->placeholder('Never')
                    ->since()
                    ->tooltip(fn (?EtgDumpStatus $record): ?string => $record?->last_update),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->placeholder('—')
                    ->since()
                    ->tooltip(fn (?EtgDumpStatus $record): ?string => $record?->started_at?->toDateTimeString()),

            ])
            ->actions([

                Tables\Actions\Action::make('update')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading(fn (?EtgDumpStatus $record): string
                        => 'Update ' . $this->rowLabel($record) . '?')
                    ->modalDescription(fn (?EtgDumpStatus $record): string
                        => match ($record?->type) {
                            'region' => 'Downloads and imports the full regions dump (EN + RU names in one pass) if a newer version is available.',
                            'review' => 'Only runs when ETG reports a newer dump than your last import. If the remote file has not changed, nothing is downloaded — use “Reimport” to force a full download and import, or “From File” to re-run import on the existing .zst.',
                            default => 'Downloads and imports this language dump if a newer version is available.',
                        })
                    ->modalSubmitActionLabel('Start Update')
                    ->action(fn (?EtgDumpStatus $record) => $record && $this->dispatchImport($record, false))
                    ->disabled(fn (?EtgDumpStatus $record): bool => (bool) $record?->isRunning()),

                Tables\Actions\Action::make('reimport')
                    ->label('Reimport')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (?EtgDumpStatus $record): string
                        => 'Force reimport ' . $this->rowLabel($record) . '?')
                    ->modalDescription(fn (?EtgDumpStatus $record): string
                        => match ($record?->type) {
                            'region' => 'Forces a full download and reimport of the regions dump even if the remote dump has not changed.',
                            'review' => 'Forces a full download and reimport of the hotel reviews dump even if the remote dump has not changed.',
                            default => 'Forces a full download and reimport even if the remote dump has not changed.',
                        })
                    ->modalSubmitActionLabel('Force Reimport')
                    ->action(fn (?EtgDumpStatus $record) => $record && $this->dispatchImport($record, true))
                    ->disabled(fn (?EtgDumpStatus $record): bool => (bool) $record?->isRunning()),

                Tables\Actions\Action::make('reimport_from_file')
                    ->label('From File')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(fn (?EtgDumpStatus $record): string
                        => 'Reimport ' . $this->rowLabel($record) . ' from existing file?')
                    ->modalDescription('Skips the download step and runs the import pipeline directly on the already-downloaded dump file.')
                    ->modalSubmitActionLabel('Reimport from File')
                    ->action(fn (?EtgDumpStatus $record) => $record && $this->dispatchReimportFromFile($record))
                    ->disabled(fn (?EtgDumpStatus $record): bool => (bool) $record?->isRunning())
                    ->visible(fn (?EtgDumpStatus $record): bool => $this->dumpFileExists($record)),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel import?')
                    ->modalDescription('Marks the import as cancelled. The currently running queue task will finish, but the chain stops at the next step.')
                    ->modalSubmitActionLabel('Cancel Import')
                    ->action(function (?EtgDumpStatus $record): void {
                        if (!$record) {
                            return;
                        }

                        EtgDumpStatus::cancelAll($record->type);
                        app($this->resolveImporterClass($record->type))->clearPendingUpdate();

                        Notification::make()
                            ->title($this->rowLabel($record) . ' import cancelled')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (?EtgDumpStatus $record): bool => (bool) $record?->isRunning()),

                Tables\Actions\Action::make('reset')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Reset failed import?')
                    ->modalDescription('Clears the failed state so you can start a new import. Use this when a retry may still be running or to dismiss the error and retry manually.')
                    ->modalSubmitActionLabel('Reset')
                    ->action(function (?EtgDumpStatus $record): void {
                        if (!$record) {
                            return;
                        }

                        EtgDumpStatus::resetFailed($record->type, $record->language);

                        Notification::make()
                            ->title($this->rowLabel($record) . ' reset — you can start a new import')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (?EtgDumpStatus $record): bool => $record?->status === 'failed'),

            ])
            ->recordClasses(fn (?EtgDumpStatus $record): string => $record?->isRunning()
                ? 'bg-primary-50 dark:bg-primary-950/20 border-l-2 border-l-primary-400'
                : '')
            ->poll('3s')
            ->striped()
            ->paginated(false);
    }

    private function rowLabel(?EtgDumpStatus $record): string
    {
        if (!$record) {
            return '—';
        }

        return match ($record->type) {
            'hotel'  => 'Hotels ' . strtoupper($record->language),
            'region' => 'Regions',
            'review' => 'Reviews',
            default  => ucfirst($record->type),
        };
    }

    /**
     * Hotels run in single-language mode (onlyLanguage = row language).
     * Regions run in full-pipeline mode (onlyLanguage = null) — RegionDumpImporter
     * overrides SUPPORTED_LANGUAGES = ['en'], so only one pass runs anyway.
     */
    private function dispatchImport(EtgDumpStatus $record, bool $force): void
    {
        $onlyLanguage = in_array($record->type, ['hotel'], true) ? $record->language : null;

        StartDumpJob::dispatch(
            $this->resolveImporterClass($record->type),
            $record->type,
            $force,
            $onlyLanguage,
        );

        Notification::make()
            ->title($this->rowLabel($record) . ' dump queued')
            ->body('The download and import pipeline has been dispatched.')
            ->success()
            ->send();
    }

    private function dumpFileExists(?EtgDumpStatus $record): bool
    {
        if (!$record) {
            return false;
        }

        try {
            $importer   = app($this->resolveImporterClass($record->type));
            $lastUpdate = $record->last_update
                ?? $importer->getPendingUpdate()
                ?? $importer->getStoredLastUpdate()
                ?? $importer->getLastUpdateFromExistingFile($record->language);

            if (!$lastUpdate) {
                return false;
            }

            $path = $importer->getDumpFilePath($lastUpdate, $record->language);

            return file_exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function dispatchReimportFromFile(EtgDumpStatus $record): void
    {
        $onlyLanguage = in_array($record->type, ['hotel'], true) ? $record->language : null;

        StartDumpJob::dispatch(
            $this->resolveImporterClass($record->type),
            $record->type,
            true,           // force — skip up-to-date check
            $onlyLanguage,
            true,           // skipDownload — use existing .zst on disk
        );

        Notification::make()
            ->title($this->rowLabel($record) . ' reimport queued (from file)')
            ->body('Skipping download — using the existing dump file on disk.')
            ->success()
            ->send();
    }

    private function resolveImporterClass(string $type): string
    {
        return match ($type) {
            'hotel'  => HotelDumpImporter::class,
            'region' => RegionDumpImporter::class,
            'review' => ReviewDumpImporter::class,
            default  => throw new \InvalidArgumentException("Unknown dump type: {$type}"),
        };
    }
}
