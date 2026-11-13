<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingPayment;
use App\Models\BookingRequest;
use App\Models\BookingRequestHistory;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BookingCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingRequestController extends Controller
{
    private function relations(): array
    {
        return [
            'customer:id,name,email', 'origin', 'destination', 'items',
            'reviewer:id,name', 'histories.actor:id,name',
            'quotations.carrier', 'quotations.preparer:id,name',
            'latestQuotation.carrier', 'latestQuotation.preparer:id,name',
            'latestPayment', 'shipment:id,shipment_number,status',
        ];
    }

    private function authorizeCustomer(Request $request, BookingRequest $booking): void
    {
        if ($request->user()->isCustomer() && (int) $booking->customer_id !== (int) $request->user()->id) {
            abort(404);
        }
    }

    private function recordHistory(BookingRequest $booking, ?User $actor, ?string $from, string $to, ?string $note = null, ?array $changes = null): void
    {
        BookingRequestHistory::create([
            'booking_request_id' => $booking->id,
            'actor_id' => $actor?->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }

    private function requestNumber(): string
    {
        do {
            $number = 'BR-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (BookingRequest::where('request_number', $number)->exists());
        return $number;
    }

    public function index(Request $request)
    {
        $query = BookingRequest::query()->with($this->relations());
        if ($request->user()->isCustomer()) {
            $query->where('customer_id', $request->user()->id);
        }
        if ($request->filled('status') && in_array($request->string('status')->toString(), BookingRequest::STATUSES, true)) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('cargo_type', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
            });
        }
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        return response()->json($query->latest()->paginate($perPage));
    }

    public function store(Request $request, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isCustomer(), 403);
        $data = $request->validate($this->requestRules());
        $items = $data['items'];
        unset($data['items']);
        if ((int) $data['origin_location_id'] === (int) $data['destination_location_id']) {
            throw ValidationException::withMessages(['destination_location_id' => ['Lokasi asal dan tujuan harus berbeda.']]);
        }

        $booking = DB::transaction(function () use ($data, $items, $request, $communication, $audit) {
            $booking = BookingRequest::create($data + [
                'request_number' => $this->requestNumber(),
                'customer_id' => $request->user()->id,
                'status' => 'PENDING_REVIEW',
                'submitted_at' => now(),
            ]);
            foreach ($items as $item) $booking->items()->create($item);
            $this->recordHistory($booking, $request->user(), null, 'PENDING_REVIEW', 'Permintaan pengiriman dikirim customer.');
            $audit->record($request->user(), 'BOOKING_REQUEST_CREATED', 'BookingRequest', $booking->id,
                'Permintaan '.$booking->request_number.' dibuat.', null, ['request_number' => $booking->request_number, 'status' => $booking->status]);
            $communication->notify($request->user(), 'BOOKING_SUBMITTED', 'Permintaan pengiriman diterima',
                'Permintaan '.$booking->request_number.' telah dikirim dan menunggu pemeriksaan operator.', $booking);
            $communication->notifyOperators('BOOKING_NEW', 'Permintaan pengiriman baru',
                'Ada permintaan pengiriman baru '.$booking->request_number.' yang perlu diperiksa.', $booking);
            return $booking;
        });

        return response()->json(['message' => 'Permintaan pengiriman berhasil dikirim.', 'data' => $booking->load($this->relations())], 201);
    }

    public function show(Request $request, BookingRequest $booking)
    {
        $this->authorizeCustomer($request, $booking);
        return response()->json(['data' => $booking->load($this->relations())]);
    }

    public function update(Request $request, BookingRequest $booking, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isCustomer(), 403);
        $this->authorizeCustomer($request, $booking);
        if ($booking->status !== 'NEEDS_REVISION') {
            throw ValidationException::withMessages(['booking' => ['Permintaan hanya dapat diedit setelah operator meminta revisi.']]);
        }
        $data = $request->validate($this->requestRules());
        $items = $data['items'];
        unset($data['items']);
        if ((int) $data['origin_location_id'] === (int) $data['destination_location_id']) {
            throw ValidationException::withMessages(['destination_location_id' => ['Lokasi asal dan tujuan harus berbeda.']]);
        }

        DB::transaction(function () use ($booking, $data, $items, $request, $communication, $audit) {
            $from = $booking->status;
            $booking->update($data + ['status' => 'PENDING_REVIEW', 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'operator_note' => null]);
            $booking->items()->delete();
            foreach ($items as $item) $booking->items()->create($item);
            $this->recordHistory($booking, $request->user(), $from, 'PENDING_REVIEW', 'Customer mengirim revisi permintaan.');
            $audit->record($request->user(), 'BOOKING_REQUEST_REVISED', 'BookingRequest', $booking->id,
                'Customer merevisi '.$booking->request_number.'.', ['status' => $from], ['status' => 'PENDING_REVIEW']);
            $communication->notifyOperators('BOOKING_REVISED', 'Permintaan diperbarui',
                'Customer telah memperbarui permintaan '.$booking->request_number.'.', $booking);
        });
        return response()->json(['message' => 'Revisi permintaan berhasil dikirim ulang.', 'data' => $booking->fresh()->load($this->relations())]);
    }

    public function review(Request $request, BookingRequest $booking, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'request_revision', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (!in_array($booking->status, ['PENDING_REVIEW', 'NEEDS_REVISION'], true)) {
            throw ValidationException::withMessages(['booking' => ['Permintaan ini tidak berada pada tahap pemeriksaan.']]);
        }
        if (in_array($data['decision'], ['request_revision', 'reject'], true) && empty(trim($data['note'] ?? ''))) {
            throw ValidationException::withMessages(['note' => ['Catatan wajib diisi untuk meminta revisi atau menolak permintaan.']]);
        }

        $to = match ($data['decision']) {
            'approve' => 'APPROVED',
            'request_revision' => 'NEEDS_REVISION',
            default => 'REJECTED',
        };
        $from = $booking->status;
        $booking->update(['status' => $to, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'operator_note' => $data['note'] ?? null]);
        $this->recordHistory($booking, $request->user(), $from, $to, $data['note'] ?? null);
        $audit->record($request->user(), 'BOOKING_'.$to, 'BookingRequest', $booking->id,
            'Status permintaan '.$booking->request_number.' diubah menjadi '.$to.'.', ['status' => $from], ['status' => $to, 'note' => $data['note'] ?? null]);
        $communication->notify($booking->customer, 'BOOKING_'.$to, 'Pembaruan permintaan pengiriman',
            match ($to) {
                'APPROVED' => 'Permintaan '.$booking->request_number.' telah disetujui. Operator akan menyiapkan penawaran biaya.',
                'NEEDS_REVISION' => 'Permintaan '.$booking->request_number.' membutuhkan perbaikan: '.$data['note'],
                default => 'Permintaan '.$booking->request_number.' ditolak: '.$data['note'],
            }, $booking);

        return response()->json(['message' => 'Keputusan pemeriksaan berhasil disimpan.', 'data' => $booking->fresh()->load($this->relations())]);
    }

    public function createQuotation(Request $request, BookingRequest $booking, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(), 403);
        if (!in_array($booking->status, ['APPROVED', 'QUOTED', 'QUOTE_DECLINED'], true)) {
            throw ValidationException::withMessages(['booking' => ['Penawaran hanya dapat dibuat untuk permintaan yang sudah disetujui atau memerlukan penawaran baru.']]);
        }
        $data = $request->validate([
            'carrier_id' => ['required', 'exists:carriers,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'terms' => ['nullable', 'string', 'max:3000'],
        ]);
        $carrier = \App\Models\Carrier::query()->whereKey($data['carrier_id'])->where('is_active', true)->first();
        if (!$carrier) throw ValidationException::withMessages(['carrier_id' => ['Carrier tidak aktif atau tidak tersedia.']]);

        $quotation = DB::transaction(function () use ($booking, $request, $data, $communication, $audit) {
            $previous = $booking->quotations()->latest('version_number')->first();
            if ($previous && $previous->status === 'SENT') $previous->update(['status' => 'SUPERSEDED']);
            $version = (int) $booking->quotations()->max('version_number') + 1;
            $quotation = $booking->quotations()->create($data + [
                'version_number' => $version,
                'prepared_by' => $request->user()->id,
                'status' => 'SENT',
                'valid_until' => $data['valid_until'] ?? now()->addDays(3),
            ]);
            $from = $booking->status;
            $booking->update(['status' => 'QUOTED']);
            $this->recordHistory($booking, $request->user(), $from, 'QUOTED', 'Penawaran biaya versi '.$version.' dikirim.');
            $audit->record($request->user(), 'BOOKING_QUOTATION_SENT', 'BookingRequest', $booking->id,
                'Penawaran versi '.$version.' dikirim untuk '.$booking->request_number.'.', ['status' => $from], ['status' => 'QUOTED', 'quotation_id' => $quotation->id]);
            $communication->notify($booking->customer, 'BOOKING_QUOTED', 'Penawaran pengiriman tersedia',
                'Penawaran untuk '.$booking->request_number.' sudah tersedia. Periksa biaya dan jadwal di CargoTrace.', $booking);
            return $quotation;
        });
        return response()->json(['message' => 'Penawaran berhasil dikirim.', 'data' => $quotation->load('carrier', 'preparer:id,name')], 201);
    }

    public function acceptQuotation(Request $request, BookingRequest $booking, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isCustomer(), 403);
        $this->authorizeCustomer($request, $booking);
        $quotation = $booking->latestQuotation;
        if ($booking->status !== 'QUOTED' || !$quotation || $quotation->status !== 'SENT') {
            throw ValidationException::withMessages(['quotation' => ['Tidak ada penawaran aktif yang dapat disetujui.']]);
        }
        if ($quotation->valid_until && $quotation->valid_until->isPast()) {
            $quotation->update(['status' => 'EXPIRED']);
            throw ValidationException::withMessages(['quotation' => ['Masa berlaku penawaran telah berakhir.']]);
        }
        DB::transaction(function () use ($booking, $quotation, $request, $communication, $audit) {
            $quotation->update(['status' => 'ACCEPTED', 'accepted_at' => now()]);
            $booking->update(['status' => 'QUOTE_ACCEPTED']);
            $this->recordHistory($booking, $request->user(), 'QUOTED', 'QUOTE_ACCEPTED', 'Customer menyetujui penawaran.');
            $audit->record($request->user(), 'BOOKING_QUOTATION_ACCEPTED', 'BookingRequest', $booking->id,
                'Customer menerima penawaran '.$booking->request_number.'.', ['status' => 'QUOTED'], ['status' => 'QUOTE_ACCEPTED']);
            $communication->notifyOperators('BOOKING_QUOTE_ACCEPTED', 'Penawaran disetujui customer',
                'Customer telah menyetujui penawaran '.$booking->request_number.'.', $booking);
        });
        return response()->json(['message' => 'Penawaran berhasil disetujui.', 'data' => $booking->fresh()->load($this->relations())]);
    }

    public function declineQuotation(Request $request, BookingRequest $booking, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isCustomer(), 403);
        $this->authorizeCustomer($request, $booking);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $quotation = $booking->latestQuotation;
        if ($booking->status !== 'QUOTED' || !$quotation || $quotation->status !== 'SENT') {
            throw ValidationException::withMessages(['quotation' => ['Tidak ada penawaran aktif yang dapat ditolak.']]);
        }
        DB::transaction(function () use ($booking, $quotation, $request, $data, $communication, $audit) {
            $quotation->update(['status' => 'DECLINED']);
            $booking->update(['status' => 'QUOTE_DECLINED']);
            $this->recordHistory($booking, $request->user(), 'QUOTED', 'QUOTE_DECLINED', $data['note'] ?? 'Customer menolak penawaran.');
            $audit->record($request->user(), 'BOOKING_QUOTATION_DECLINED', 'BookingRequest', $booking->id, 'Customer menolak penawaran.', ['status' => 'QUOTED'], ['status' => 'QUOTE_DECLINED']);
            $communication->notifyOperators('BOOKING_QUOTE_DECLINED', 'Penawaran ditolak customer',
                'Customer menolak penawaran '.$booking->request_number.'.', $booking);
        });
        return response()->json(['message' => 'Penawaran ditolak.', 'data' => $booking->fresh()->load($this->relations())]);
    }

    public function createPaymentIntent(Request $request, BookingRequest $booking, AuditService $audit)
    {
        abort_unless($request->user()->isCustomer(), 403);
        $this->authorizeCustomer($request, $booking);
        if ($booking->status !== 'QUOTE_ACCEPTED') {
            throw ValidationException::withMessages(['payment' => ['Customer harus menyetujui penawaran sebelum membuat pembayaran.']]);
        }
        $quotation = $booking->latestQuotation;
        if (!$quotation || $quotation->status !== 'ACCEPTED') {
            throw ValidationException::withMessages(['quotation' => ['Penawaran yang disetujui tidak ditemukan.']]);
        }
        $pending = $booking->payments()->where('status', 'PENDING')->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->latest()->first();
        if ($pending) return response()->json(['message' => 'Pembayaran yang menunggu masih tersedia.', 'data' => $pending, 'demo_mode' => true]);
        $booking->payments()->where('status', 'PENDING')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'EXPIRED']);

        // Layer 3: use Midtrans Snap when credentials are configured. Demo mode remains local/testing only.
        $midtransServerKey = (string) config('services.midtrans.server_key');
        if ($midtransServerKey !== '') {
            if (strtoupper((string) $quotation->currency) !== 'IDR') {
                throw ValidationException::withMessages(['currency' => ['Midtrans Snap pada konfigurasi ini hanya menerima quotation IDR.']]);
            }
            $orderId = 'CTPAY-'.now()->format('ymdHis').'-'.Str::upper(Str::random(8));
            $amount = (int) round((float) $quotation->amount);
            if ($amount < 1) {
                throw ValidationException::withMessages(['amount' => ['Nilai pembayaran harus lebih besar dari nol.']]);
            }
            $payload = [
                'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
                'customer_details' => ['first_name' => $request->user()->name, 'email' => $request->user()->email],
                'item_details' => [[
                    'id' => 'BOOKING-'.$booking->request_number,
                    'price' => $amount, 'quantity' => 1,
                    'name' => 'CargoTrace shipment booking',
                ]],
            ];
            try {
                $gateway = \Illuminate\Support\Facades\Http::withBasicAuth($midtransServerKey, '')
                    ->acceptJson()->asJson()->timeout(20)->post(config('services.midtrans.snap_url'), $payload);
            } catch (\Throwable $exception) {
                report($exception);
                return response()->json(['message' => 'Payment gateway tidak dapat dihubungi. Silakan coba kembali.', 'code' => 'PAYMENT_GATEWAY_UNAVAILABLE'], 502);
            }
            if (!$gateway->successful() || !$gateway->json('token') || !$gateway->json('redirect_url')) {
                report(new \RuntimeException('Midtrans Snap transaction creation failed: HTTP '.$gateway->status()));
                return response()->json(['message' => 'Payment gateway menolak pembuatan transaksi.', 'code' => 'PAYMENT_GATEWAY_REJECTED'], 502);
            }
            $payment = DB::transaction(function () use ($booking, $quotation, $request, $audit, $orderId, $amount, $gateway) {
                $payment = $booking->payments()->create([
                    'quotation_id' => $quotation->id, 'customer_id' => $request->user()->id,
                    'amount' => $amount, 'currency' => $quotation->currency,
                    'provider' => 'midtrans', 'payment_method' => 'MIDTRANS_SNAP',
                    'provider_reference' => $orderId, 'status' => 'PENDING',
                    'expires_at' => now()->addHours(24),
                    'provider_data' => ['snap_token' => $gateway->json('token'), 'redirect_url' => $gateway->json('redirect_url')],
                ]);
                $booking->update(['status' => 'PENDING_PAYMENT']);
                $this->recordHistory($booking, $request->user(), 'QUOTE_ACCEPTED', 'PENDING_PAYMENT', 'Customer memulai pembayaran melalui payment gateway.');
                $audit->record($request->user(), 'BOOKING_PAYMENT_INTENT_CREATED', 'BookingPayment', $payment->id,
                    'Payment gateway intent dibuat untuk '.$booking->request_number.'.', null, ['status' => 'PENDING', 'provider' => 'midtrans']);
                return $payment;
            });
            return response()->json([
                'message' => 'Payment gateway checkout berhasil dibuat.',
                'data' => $payment, 'demo_mode' => false,
                'checkout_url' => $gateway->json('redirect_url'),
            ], 201);
        }

        // Mock checkout remains restricted to local/testing and is never used in production.
        if (!app()->environment(['local', 'testing']) || !config('cargotrace.allow_demo_payments')) {
            return response()->json(['message' => 'Payment gateway belum dikonfigurasi. Isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY untuk mengaktifkan pembayaran sandbox/production.', 'code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED'], 503);
        }

        $payment = DB::transaction(function () use ($booking, $quotation, $request, $audit) {
            $payment = $booking->payments()->create([
                'quotation_id' => $quotation->id,
                'customer_id' => $request->user()->id,
                'amount' => $quotation->amount,
                'currency' => $quotation->currency,
                'provider' => 'mock',
                'payment_method' => 'DEMO_CHECKOUT',
                'provider_reference' => 'DEMO-'.Str::upper(Str::random(18)),
                'status' => 'PENDING',
                'expires_at' => now()->addMinutes(30),
                'provider_data' => ['mode' => 'simulation', 'notice' => 'No real payment is processed.'],
            ]);
            $booking->update(['status' => 'PENDING_PAYMENT']);
            $this->recordHistory($booking, $request->user(), 'QUOTE_ACCEPTED', 'PENDING_PAYMENT', 'Customer memulai checkout demo.');
            $audit->record($request->user(), 'BOOKING_PAYMENT_INTENT_CREATED', 'BookingPayment', $payment->id,
                'Payment demo intent dibuat untuk '.$booking->request_number.'.', null, ['status' => 'PENDING', 'provider' => 'mock']);
            return $payment;
        });
        return response()->json([
            'message' => 'Demo checkout dibuat. Tidak ada uang sungguhan yang ditagihkan.',
            'data' => $payment,
            'demo_mode' => true,
            'notice' => 'DEMO ONLY — this does not charge a real bank account or card.',
        ], 201);
    }

    public function convertToShipment(Request $request, BookingRequest $booking, BookingCommunicationService $communication, AuditService $audit)
    {
        abort_unless($request->user()->isOperator(), 403);
        $result = DB::transaction(function () use ($booking, $request, $communication, $audit) {
            $locked = BookingRequest::query()->lockForUpdate()->findOrFail($booking->id);
            if ($locked->status === 'CONVERTED' && $locked->shipment_id) {
                return ['shipment' => Shipment::with(['customer', 'carrier', 'origin', 'destination', 'items', 'trackingEvents'])->findOrFail($locked->shipment_id), 'already_converted' => true];
            }
            if ($locked->status !== 'READY_FOR_CONVERSION') {
                throw ValidationException::withMessages(['booking' => ['Permintaan hanya dapat dikonversi setelah penawaran diterima dan pembayaran dikonfirmasi.']]);
            }
            $locked->load(['items', 'latestQuotation']);
            $quotation = $locked->latestQuotation;
            $payment = $locked->payments()->where('status', 'PAID')->latest()->first();
            if (!$quotation || $quotation->status !== 'ACCEPTED' || !$payment) {
                throw ValidationException::withMessages(['booking' => ['Penawaran diterima dan pembayaran berhasil wajib tersedia sebelum konversi.']]);
            }
            $departure = $quotation->expected_departure_at ?? $locked->desired_departure_at ?? now();
            $arrival = $quotation->expected_arrival_at ?? $locked->desired_arrival_at;
            if (!$arrival || $arrival->lt($departure)) {
                throw ValidationException::withMessages(['expected_arrival_at' => ['Jadwal kedatangan yang valid diperlukan untuk membuat shipment.']]);
            }

            $shipment = Shipment::create([
                'shipment_number' => $this->shipmentNumber(),
                'customer_reference' => $locked->request_number,
                'customer_id' => $locked->customer_id,
                'created_by' => $request->user()->id,
                'carrier_id' => $quotation->carrier_id,
                'origin_location_id' => $locked->origin_location_id,
                'destination_location_id' => $locked->destination_location_id,
                'cargo_type' => $locked->cargo_type,
                'cargo_description' => $locked->cargo_description,
                'package_count' => $locked->package_count,
                'total_weight' => $locked->total_weight,
                'weight_unit' => $locked->weight_unit,
                'total_volume' => $locked->total_volume,
                'volume_unit' => $locked->volume_unit,
                'expected_departure_at' => $departure,
                'expected_arrival_at' => $arrival,
                'status' => 'CREATED',
                'notes' => 'Created from booking request '.$locked->request_number.'.',
            ]);
            foreach ($locked->items as $item) {
                $shipment->items()->create([
                    'item_name' => $item->item_name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_weight' => $item->unit_weight,
                    'weight_unit' => $item->weight_unit,
                    'unit_volume' => $item->unit_volume,
                    'volume_unit' => $item->volume_unit,
                ]);
            }
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'created_by' => $request->user()->id,
                'event_type' => TrackingEvent::TYPE_STATUS,
                'status' => 'CREATED',
                'description' => 'Shipment dibuat dari booking request '.$locked->request_number.'.',
                'event_time' => now(),
            ]);
            $locked->update(['status' => 'CONVERTED', 'shipment_id' => $shipment->id]);
            $this->recordHistory($locked, $request->user(), 'READY_FOR_CONVERSION', 'CONVERTED', 'Booking dikonversi menjadi shipment '.$shipment->shipment_number.'.');
            $audit->record($request->user(), 'BOOKING_CONVERTED_TO_SHIPMENT', 'BookingRequest', $locked->id,
                'Booking '.$locked->request_number.' dikonversi menjadi '.$shipment->shipment_number.'.', ['status' => 'READY_FOR_CONVERSION'], ['status' => 'CONVERTED', 'shipment_id' => $shipment->id]);
            $communication->notify($locked->customer, 'BOOKING_CONVERTED', 'Shipment berhasil dibuat',
                'Permintaan '.$locked->request_number.' telah menjadi shipment '.$shipment->shipment_number.'.', $locked);
            return ['shipment' => $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items', 'trackingEvents']), 'already_converted' => false];
        });

        return response()->json([
            'message' => $result['already_converted'] ? 'Booking ini sudah pernah dikonversi.' : 'Booking berhasil dikonversi menjadi shipment.',
            'data' => $result['shipment'],
            'already_converted' => $result['already_converted'],
        ], $result['already_converted'] ? 200 : 201);
    }

    private function shipmentNumber(): string
    {
        do {
            $number = 'CT-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Shipment::withTrashed()->where('shipment_number', $number)->exists());
        return $number;
    }

    private function requestRules(): array
    {
        return [
            'origin_location_id' => ['required', 'integer', 'exists:locations,id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id', 'different:origin_location_id'],
            'desired_departure_at' => ['nullable', 'date'],
            'desired_arrival_at' => ['nullable', 'date', 'after_or_equal:desired_departure_at'],
            'cargo_type' => ['required', 'string', 'max:120'],
            'cargo_description' => ['nullable', 'string', 'max:3000'],
            'package_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'total_weight' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'weight_unit' => ['required', 'string', 'max:10'],
            'total_volume' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'volume_unit' => ['nullable', 'string', 'max:10'],
            'customer_notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight_unit' => ['nullable', 'string', 'max:10'],
            'items.*.unit_volume' => ['nullable', 'numeric', 'min:0'],
            'items.*.volume_unit' => ['nullable', 'string', 'max:10'],
        ];
    }
}
