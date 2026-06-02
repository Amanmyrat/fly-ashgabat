<?php

namespace App\Filament\Resources;

use App;
use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Filament\Resources\BookingResource\Pages;
use App\Jobs\Nemo\GenerateTicketJob;
use App\Jobs\TFusion\CheckBookingStatusJob;
use App\Jobs\TFusion\StartBookingJob;
use App\Jobs\XmlAgency\ConfirmBookingJob;
use App\Services\AirportLocatorService;
use App\Services\FlightMarkupService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\MyAgent\PayBookingJob as MyAgentPayBookingJob;


class FlightBookingResource extends Resource
{
    protected static ?string $model = App\Models\FlightBooking::class;

    protected static ?string $navigationIcon = 'heroicon-c-list-bullet';

    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $pluralLabel = 'Orders';

    public function __construct(protected AirportLocatorService $airportLocatorService)
    {
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID'),

                TextColumn::make('flight_type')
                    ->label('Supplier')->badge(),

                TextColumn::make('booking_reference')
                    ->label('Order')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('contactDetail.name')
                    ->label('Full name')
                    ->formatStateUsing(fn ($record) => $record->contactDetail?->name ?? '-')
                    ->searchable(),

                TextColumn::make('contactDetail.phone')
                    ->label('Phone')
                    ->formatStateUsing(function ($record) {
                        $phone = $record->contactDetail?->phone;

                        if (!is_array($phone)) {
                            return '-';
                        }

                        return '+' . ($phone['code'] ?? '') . ' ' . ($phone['number'] ?? '');
                    }),

                TextColumn::make('contactDetail.email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Order status')->badge(),

                TextColumn::make('payment_type')
                    ->badge(),

                TextColumn::make('flight_directions_and_time')
                    ->label('Directions')
                    ->getStateUsing(fn($record) => $record->getFlightDirectionsAndType(App::make(AirportLocatorService::class)))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Order date')
                    ->dateTime()
                    ->sortable(),

            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Order date')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('From'),
                        DatePicker::make('created_to')
                            ->label('To'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', Carbon::parse($date))
                            )
                            ->when(
                                $data['created_to'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', Carbon::parse($date))
                            );
                    }),

                SelectFilter::make('status')
                    ->label('Order status')
                    ->options([
                        BookingStatus::PENDING->value  => 'Pending',
                        BookingStatus::BOOKING_IN_PROGRESS->value  => 'In-progress',
                        BookingStatus::SUCCEEDED->value => 'Approved',
                        BookingStatus::FAILED->value   => 'Failed',
                        BookingStatus::UNCONFIRMED->value => 'Unconfirmed',
                        BookingStatus::UNCONFIRMED_BY_SUPPLIER->value => 'Unconfirmed by supplier',
                        BookingStatus::DUPLICATE->value => 'Duplicate',
                    ])->native(false),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Price Summary at the top
                Section::make('Booking Summary')
                    ->schema([
                        TextEntry::make('booking_reference')
                            ->label('Order Reference')
                            ->columnSpan(1),

                        TextEntry::make('price_display')
                            ->label('Total Price')
                            ->getStateUsing(function ($record) {
                                $price = $record->price;
                                if (!$price || !isset($price['Amount'], $price['Currency'])) {
                                    return 'N/A';
                                }

                                // Check if this is already processed data or original data
                                if (isset($price['MarkupPercentage'])) {
                                    // Already processed - just show the final amount
                                    return number_format($price['Amount'], 2) . ' ' . $price['Currency'];
                                } else {
                                    // Original data - process it
                                    $markupService = app(FlightMarkupService::class);
                                    $processedPrice = $markupService->applyMarkup(
                                        (float) $price['Amount'],
                                        $price['Currency'],
                                        $record->flight_type
                                    );

                                    return number_format($processedPrice['Amount'], 2) . ' ' . $processedPrice['Currency'];
                                }
                            })
                            ->badge()
                            ->color('success')
                            ->size('lg')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(false),

                Section::make('Contact Details')
                    ->schema([

                        TextEntry::make('contactDetail.name')
                            ->label('Full name')
                            ->formatStateUsing(fn($record) => $record->contactDetail->name)
                            ->visible(fn ($record) => $record->contactDetail->name != null),

                        TextEntry::make('contactDetail.email')
                            ->label('Email')
                            ->formatStateUsing(fn($record) => "{$record->contactDetail->email}"),

                        TextEntry::make('contactDetail.phone')
                            ->label('Phone')
                            ->formatStateUsing(fn($record) => "{$record->contactDetail->phone['code']} {$record->contactDetail->phone['number']}"),

                        TextEntry::make('status')
                            ->label('Order Status')->badge(),

                        TextEntry::make('created_at')
                            ->label('Order Date & Time')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Flight Information')
                    ->schema([

                        TextEntry::make('flight_directions')
                            ->label('Route')
                            ->getStateUsing(fn($record) => $record->getFlightDirectionsAndType(App::make(AirportLocatorService::class)))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                // Price Processing Details (optional technical info)
                Section::make('Price Details')
                    ->schema([
                        TextEntry::make('price_processing_info')
                            ->label('Applied Processing')
                            ->getStateUsing(function ($record) {
                                $price = $record->price;
                                if (!$price || !isset($price['Amount'], $price['Currency'])) {
                                    return 'No price data available';
                                }

                                $details = [];

                                // Handle different price formats
                                $originalAmount = (float) $price['Amount'];
                                $originalCurrency = $price['Currency'];

                                $details[] = "Total price: " . number_format($originalAmount, 2) . ' ' . $originalCurrency;

                                // Only process if this is original simple format (not already processed)
                                if (!isset($price['MarkupPercentage'])) {
                                    $markupService = app(FlightMarkupService::class);
                                    $processedPrice = $markupService->applyMarkup(
                                        $originalAmount,
                                        $originalCurrency,
                                        $record->flight_type
                                    );

                                    // Currency conversion info
                                    if (isset($processedPrice['OriginalCurrency'])) {
                                        $usdToRubRate = 1 / $processedPrice['ConversionRate']; // Show the rate they entered
                                        $details[] = "Currency conversion: {$processedPrice['OriginalCurrency']} → {$processedPrice['Currency']} (1 USD = " . number_format($usdToRubRate, 0) . " RUB)";
                                    }

                                    // Markup info
                                    if ($processedPrice['MarkupPercentage'] > 0) {
                                        $details[] = "Markup applied: {$processedPrice['MarkupPercentage']}% (+" . number_format($processedPrice['MarkupAmount'], 2) . ' ' . $processedPrice['Currency'] . ')';
                                    } else {
                                        $details[] = "No markup applied";
                                    }
                                } else {
                                    // This is already processed price data
                                    if (isset($price['OriginalCurrency'])) {
                                        $usdToRubRate = 1 / $price['ConversionRate'];
                                        $details[] = "Currency conversion: {$price['OriginalCurrency']} → {$price['Currency']} (1 USD = " . number_format($usdToRubRate, 0) . " RUB)";
                                    }

                                    if ($price['MarkupPercentage'] > 0) {
                                        $details[] = "Markup applied: {$price['MarkupPercentage']}% (+" . number_format($price['MarkupAmount'], 2) . ' ' . $price['Currency'] . ')';
                                    } else {
                                        $details[] = "No markup applied";
                                    }
                                }

                                return implode('<br>', $details);
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true),
//
                // Special Section for Tickets
                Section::make('Tickets')
                    ->schema([
                        TextEntry::make('tickets')
                            ->getStateUsing(function ($record) {
                                return $record->tickets->map(function ($ticket) {
                                    return sprintf(
                                        '%s - <a href="%s" target="_blank" style="color: blue; text-decoration: underline;">Download</a>',
                                        e($ticket->name),
                                        e($ticket->ticket_url)
                                    );
                                })->implode('<br>');
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false)
                    ->visible(fn ($record) => $record->tickets->isNotEmpty()),


                Actions::make([
                    Action::make('start_booking')
                        ->label('Start Booking')
                        ->color('primary')
                        ->icon('heroicon-o-play')
                        ->visible(fn ($record) => $record->status === BookingStatus::PENDING)
                        ->action(function ($record) {
                            $record->update(['status' => BookingStatus::BOOKING_IN_PROGRESS]);
                            switch ($record->flight_type) {
                                case FlightSupplier::TFUSION:
                                    StartBookingJob::dispatch($record);
                                    CheckBookingStatusJob::dispatch($record);
                                    break;
                                case FlightSupplier::XMLAGENCY:
                                    ConfirmBookingJob::dispatch($record);
                                    break;
                                case FlightSupplier::NEMO:
                                    GenerateTicketJob::dispatch($record);
                                case FlightSupplier::MYAGENT:
                                    MyAgentPayBookingJob::dispatch($record);
                                    break;
                            }

                            Notification::make()
                                ->title('Booking Started')
                                ->body("The booking for record #{$record->id} has been started successfully.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->tooltip('Click to start the booking process'),

//                    Action::make('generate_tickets')
//                        ->label('Generate tickets')
//                        ->color('primary')
//                        ->icon('heroicon-o-play')
//                        ->visible(fn ($record) => $record->status === BookingStatus::SUCCEEDED && $record->tickets->isEmpty())
//                        ->action(function ($record) {
//                            // Dispatch a job
//
//                            GenerateTicketJob::dispatch($record);
//
//                            Notification::make()
//                                ->title('Generating tickets')
//                                ->body("The ticket generation for record #{$record->id} has been started successfully.")
//                                ->success()
//                                ->send();
//                        })
//                        ->requiresConfirmation()
//                        ->tooltip('Click to start the booking process'),
                ]),

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }
}
