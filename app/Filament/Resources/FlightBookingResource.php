<?php

namespace App\Filament\Resources;

use App;
use App\Enum\BookingStatus;
use App\Enum\FlightSupplier;
use App\Filament\Resources\BookingResource\Pages;
use App\Jobs\TFusion\CheckBookingStatusJob;
use App\Jobs\TFusion\GenerateTicketJob;
use App\Jobs\TFusion\StartBookingJob;
use App\Jobs\XmlAgency\ConfirmBookingJob;
use App\Services\AirportLocatorService;
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

                TextColumn::make('contactDetail.phone')
                    ->label('Full name')
                    ->formatStateUsing(fn($record) => "+{$record->contactDetail->phone['code']} {$record->contactDetail->phone['number']}")
                    ->searchable(),

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
                TextEntry::make('booking_reference')
                    ->label('Order'),

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
                    ->label('Статус заказа')->badge(),

                TextEntry::make('created_at')
                    ->label('Дата и время заказa')
                    ->dateTime(),

                TextEntry::make('flight_directions')
                    ->label('Directions')
                    ->getStateUsing(fn($record) => $record->getFlightDirectionsAndType(App::make(AirportLocatorService::class)))
                    ->columnSpanFull(),
//
                // Special Section for Tickets
                Section::make('Билеты')
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
                                    \App\Jobs\Nemo\GenerateTicketJob::dispatch($record);
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
