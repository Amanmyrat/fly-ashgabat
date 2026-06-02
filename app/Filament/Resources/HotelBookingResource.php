<?php

namespace App\Filament\Resources;

use App\Mail\HotelBookingConfirmationMail;
use App\Models\HotelBooking;
use App\Filament\Resources\HotelBookingResource\Pages;
use App\Services\ETG\HotelBookingService;
use App\Services\HotelBookingDocumentService;
use App\Services\HotelPostpayConfirmationService;
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
use Illuminate\Support\Facades\Mail;
use Throwable;

class HotelBookingResource extends Resource
{
    protected static ?string $model = HotelBooking::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Hotel Bookings';
    protected static ?string $pluralLabel = 'Hotel Bookings';

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
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('partner_order_id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('hotel.name_en')
                    ->label('Hotel')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('room_type')
                    ->label('Room Type')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('rooms_guests_display')
                    ->label('Guests')
                    ->getStateUsing(fn($record) => sprintf(
                        '%d 🏠 | %d',
                        $record->rooms_count ?? 0,
                        $record->adults_count ?? 0,
                    ))
                    ->html(),

                TextColumn::make('contact_email')
                    ->label('Email')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('contact_phone')
                    ->label('Phone')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn($state, $record) => number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'processing' => 'warning',
                        'confirmed' => 'success',
                        'failed' => 'danger',
                        'pending' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('payment_type')
                    ->label('Payment')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('M d, Y H:i')
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
                    ->label('Status')
                    ->options([
                        'processing' => 'Processing',
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                    ])->native(false),

                SelectFilter::make('payment_type')
                    ->label('Payment Type')
                    ->options([
                        'balance' => 'Balance',
                        'stripe' => 'Stripe',
                        'postpay' => 'Postpay',
                    ])->native(false),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Booking Summary')
                    ->schema([
                        TextEntry::make('partner_order_id')
                            ->label('Partner Order ID')
                            ->columnSpan(1),

                        TextEntry::make('etg_order_id')
                            ->label('ETG Order ID')
                            ->columnSpan(1),

                        TextEntry::make('price_display')
                            ->label('Total Price')
                            ->getStateUsing(function ($record) {
                                return number_format((float) $record->amount, 2) . ' ' . $record->currency;
                            })
                            ->badge()
                            ->color('success')
                            ->size('lg')
                            ->columnSpan(1),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'processing' => 'warning',
                                'confirmed' => 'success',
                                'failed' => 'danger',
                                'pending' => 'info',
                                default => 'gray',
                            })
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(false),

                Section::make('Hotel Information')
                    ->schema([
                        TextEntry::make('hotel.name_en')
                            ->label('🏨 Hotel Name'),

                        TextEntry::make('room_type')
                            ->label('🏠 Room Type'),

                        TextEntry::make('hotel.star_rating')
                            ->label('⭐ Star Rating'),

                        TextEntry::make('hotel.address_en')
                            ->label('📍 Address')
                            ->columnSpanFull(),

                        TextEntry::make('rooms_guests_detail')
                            ->label('👥 Rooms & Guests')
                            ->getStateUsing(function ($record) {
                                return sprintf(
                                    '%d Rooms | %d Adults',
                                    $record->rooms_count ?? 0,
                                    $record->adults_count ?? 0,
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Contact Details')
                    ->schema([
                        TextEntry::make('contact_email')
                            ->label('Email'),

                        TextEntry::make('contact_phone')
                            ->label('Phone'),

                        TextEntry::make('payment_type')
                            ->label('Payment Type')
                            ->badge(),

                        TextEntry::make('created_at')
                            ->label('Order Date & Time')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Guests')
                    ->schema([
                        TextEntry::make('guests_list')
                            ->label('Guests Information')
                            ->getStateUsing(function ($record) {
                                if (!is_array($record->guests) || empty($record->guests)) {
                                    return 'No guest information available';
                                }

                                $output = [];
                                $roomNumber = 1;

                                foreach ($record->guests as $room) {
                                    $roomGuests = $room['guests'] ?? [];

                                    if (!is_array($roomGuests) || empty($roomGuests)) {
                                        $roomNumber++;
                                        continue;
                                    }

                                    foreach ($roomGuests as $guest) {
                                        $firstName = trim((string) ($guest['first_name'] ?? ''));
                                        $lastName = trim((string) ($guest['last_name'] ?? ''));
                                        $name = trim($firstName . ' ' . $lastName);

                                        if ($name === '') {
                                            continue;
                                        }

                                        $output[] = '
                            <div style="padding: 12px; margin: 8px 0; background-color: #f8fafc; border-left: 3px solid #0084FF; border-radius: 4px;">
                                <div style="font-weight: 600; color: #1a1a1a; margin-bottom: 4px;">
                                    Room ' . e($roomNumber) . ': ' . e($name) . '
                                </div>
                            </div>
                        ';
                                    }

                                    $roomNumber++;
                                }

                                return !empty($output)
                                    ? implode('', $output)
                                    : 'No guest information available';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Documents')
                    ->schema([
                        TextEntry::make('confirmation_pdf_url')
                            ->label('Confirmation PDF')
                            ->getStateUsing(function ($record) {
                                if ($record->confirmation_pdf_url) {
                                    return sprintf(
                                        '<a href="%s" target="_blank" style="color: #0084FF; text-decoration: underline;">📄 Download Confirmation</a>',
                                        e($record->confirmation_pdf_url)
                                    );
                                }
                                return 'No PDF available';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Actions::make([
                    Action::make('confirm_booking')
                        ->label('Confirm Booking')
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn ($record) => $record->status === 'processing' && $record->payment_type === 'postpay')
                        ->action(function ($record) {
                            try {
                                app(HotelPostpayConfirmationService::class)->confirmPostpayBooking($record);

                                Notification::make()
                                    ->title('Booking Confirmed')
                                    ->body("Hotel booking #{$record->partner_order_id} confirmed successfully.")
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                $record->update([
                                    'status' => 'failed',
                                    'api_response' => ['error' => $e->getMessage()],
                                ]);

                                Notification::make()
                                    ->title('Confirmation Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->tooltip('Click to confirm this postpay booking'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHotelBookings::route('/'),
            'view' => Pages\ViewHotelBooking::route('/{record}'),
        ];
    }
}
