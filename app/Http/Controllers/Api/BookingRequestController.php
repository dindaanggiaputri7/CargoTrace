<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingPayment;
use App\Models\BookingRequest;
use App\Models\BookingRequestHistory;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BookingCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingRequestController extends Controller
{
    private function relations(): array
    {
        return [
            'customer:id,name,email', 'customerRecord:id,name,company_name,email,phone,user_id', 'origin', 'destination', 'items',
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
            $customerRecord = Customer::firstOrCreate(['user_id' => $request->user()->id], [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ]);
            $booking = BookingRequest::create($data + [
                'request_number' => $this->requestNumber(),
                'customer_id' => $request->user()->id,
                'customer_record_id' => $customerRecord->id,
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

    public function createPaymentIntent(Request $request, BookingRequest $booking, AuditService $audit, BookingCommunicationService $communication)
    {
        abort_unless($request->user()->isCustomer(), 403);
        $this->authorizeCustomer($request, $booking);

        // Expire legacy/mock or overdue pending attempts before deciding whether checkout can resume.
        DB::transaction(function () use ($booking, $audit, $communication) {
            $lockedBooking = BookingRequest::query()->lockForUpdate()->findOrFail($booking->id);
            $stalePayments = $lockedBooking->payments()->where('status', 'PENDING')
                ->where(function ($query) {
                    $query->where('provider', '!=', 'midtrans')
                        ->orWhereNull('expires_at')
                        ->orWhere('expires_at', '<=', now());
                })->lockForUpdate()->get();

            foreach ($stalePayments as $stalePayment) {
                $stalePayment->update(['status' => 'EXPIRED']);
                $audit->record(null, 'BOOKING_PAYMENT_EXPIRED', 'BookingPayment', $stalePayment->id,
                    'Transaksi lama atau kedaluwarsa tidak dapat digunakan sebagai bukti pembayaran.',
                    ['status' => 'PENDING'], ['status' => 'EXPIRED']);
            }

            $hasPaid = $lockedBooking->payments()->where('status', 'PAID')->exists();
            $hasActivePending = $lockedBooking->payments()->where('provider', 'midtrans')->where('status', 'PENDING')
                ->whereNotNull('expires_at')->where('expires_at', '>', now())->exists();
            if ($lockedBooking->status === 'PENDING_PAYMENT' && !$hasPaid && !$hasActivePending) {
                $lockedBooking->update(['status' => 'QUOTE_ACCEPTED']);
                $this->recordHistory($lockedBooking, null, 'PENDING_PAYMENT', 'QUOTE_ACCEPTED',
                    'Transaksi sebelumnya telah kedaluwarsa atau berasal dari simulasi; customer dapat membuat checkout Midtrans baru.');
                $communication->notify($lockedBooking->customer, 'PAYMENT_EXPIRED', 'Transaksi pembayaran perlu dibuat ulang',
                    'Transaksi lama tidak dapat diverifikasi. Silakan mulai pembayaran Midtrans kembali.', $lockedBooking);
            }
        });
        $booking->refresh();

        if (!in_array($booking->status, ['QUOTE_ACCEPTED', 'PENDING_PAYMENT'], true)) {
            throw ValidationException::withMessages(['payment' => ['Customer harus menyetujui penawaran sebelum membuat pembayaran.']]);
        }
        $quotation = $booking->latestQuotation;
        if (!$quotation || $quotation->status !== 'ACCEPTED') {
            throw ValidationException::withMessages(['quotation' => ['Penawaran yang disetujui tidak ditemukan.']]);
        }
        // Only a still-valid Midtrans transaction may be resumed. No local/demo payment can become PAID.
        $pending = $booking->payments()->where('provider', 'midtrans')->where('status', 'PENDING')
            ->whereNotNull('expires_at')->where('expires_at', '>', now())->latest()->first();
        if ($pending) {
            return response()->json([
                'message' => 'Pembayaran Midtrans yang masih berlaku tersedia.',
                'data' => $pending, 'demo_mode' => false,
                'checkout_url' => data_get($pending->provider_data, 'redirect_url'),
            ]);
        }

        if ($booking->status !== 'QUOTE_ACCEPTED') {
            throw ValidationException::withMessages(['payment' => ['Status permintaan belum siap untuk membuat transaksi pembayaran baru.']]);
        }

        // Layer 3: real Midtrans Snap checkout; sandbox is recommended for academic demos.
        $midtransServerKey = trim((string) config('services.midtrans.server_key'));
        if ($midtransServerKey !== '') {
            $isProduction = (bool) config('services.midtrans.is_production');
            $isSandboxKey = str_starts_with($midtransServerKey, 'SB-Mid-server-');
            $environmentMismatch = $isProduction
                ? $isSandboxKey
                : !$isSandboxKey;
            if ($environmentMismatch) {
                return response()->json([
                    'message' => $isProduction
                        ? 'MIDTRANS_SERVER_KEY adalah key Sandbox, tetapi MIDTRANS_IS_PRODUCTION=true.'
                        : 'MIDTRANS_SERVER_KEY adalah key Production, tetapi MIDTRANS_IS_PRODUCTION=false.',
                    'code' => 'PAYMENT_GATEWAY_ENVIRONMENT_MISMATCH',
                ], 503);
            }
            if (strtoupper((string) $quotation->currency) !== 'IDR') {
                throw ValidationException::withMessages(['currency' => ['Midtrans Snap pada konfigurasi ini hanya menerima quotation IDR.']]);
            }
            $orderId = 'CTPAY-'.now()->format('ymdHis').'-'.Str::upper(Str::random(8));
            $amount = (int) round((float) $quotation->amount);
            if ($amount < 1) {
                throw ValidationException::withMessages(['amount' => ['Nilai pembayaran harus lebih besar dari nol.']]);
            }
            $payload = [
                'callbacks' => [
                    'finish' => 'https://cargotrace-eoch.ethiodeploy.app/',
                ],
                'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
                'customer_details' => ['first_name' => $request->user()->name, 'email' => $request->user()->email],
                'item_details' => [[
                    'id' => 'BOOKING-'.$booking->request_number,
                    'price' => $amount, 'quantity' => 1,
                    'name' => 'CargoTrace shipment booking',
                ]],
                'expiry' => [
                    'start_time' => now()->format('Y-m-d H:i:s O'),
                    'unit' => 'hour',
                    'duration' => 24,
                ],
            ];
            try {
                $gateway = \Illuminate\Support\Facades\Http::withBasicAuth($midtransServerKey, '')
                    ->acceptJson()->asJson()->timeout(20)->post(config('services.midtrans.snap_url'), $payload);
            } catch (\Throwable $exception) {
                report($exception);
                return response()->json(['message' => 'Payment gateway tidak dapat dihubungi. Silakan coba kembali.', 'code' => 'PAYMENT_GATEWAY_UNAVAILABLE'], 502);
            }
            if (!$gateway->successful() || !$gateway->json('token') || !$gateway->json('redirect_url')) {
                $gatewayStatus = $gateway->status();
                $gatewayPayload = $gateway->json() ?? [];
                $gatewayMessages = data_get($gatewayPayload, 'error_messages',
                    data_get($gatewayPayload, 'validation_messages', []));
                if (is_string($gatewayMessages)) {
                    $gatewayMessages = [$gatewayMessages];
                }
                $gatewayMessages = is_array($gatewayMessages)
                    ? array_values(array_filter($gatewayMessages, fn ($message) => is_string($message) && trim($message) !== ''))
                    : [];
                $gatewayDetail = implode(' ', $gatewayMessages);

                // Keep the actual gateway response for server-side diagnosis, but never log credentials.
                Log::warning('Midtrans Snap transaction creation rejected.', [
                    'http_status' => $gatewayStatus,
                    'order_id' => $orderId,
                    'environment' => config('services.midtrans.is_production') ? 'production' : 'sandbox',
                    'endpoint' => config('services.midtrans.snap_url'),
                    'gateway_error_messages' => $gatewayMessages,
                ]);

                $message = match ($gatewayStatus) {
                    400, 408, 413 => 'Midtrans menolak data transaksi (HTTP '.$gatewayStatus.'). '.($gatewayDetail ?: 'Periksa nominal, mata uang, dan detail item quotation.'),
                    401 => 'Midtrans menolak autentikasi (HTTP 401). Pastikan Server Key berasal dari Sandbox yang sama dengan endpoint Sandbox, dan tidak ada spasi saat menyalinnya.',
                    402 => 'Akun Midtrans tidak memiliki akses ke metode pembayaran yang diminta (HTTP 402). Periksa pengaturan akses produk/payment channel pada dashboard Sandbox.',
                    406 => 'Order ID transaksi sudah pernah digunakan (HTTP 406). Coba buat transaksi baru.',
                    429 => 'Batas request Midtrans tercapai (HTTP 429). Tunggu sebentar lalu coba kembali.',
                    default => 'Midtrans menolak pembuatan transaksi (HTTP '.$gatewayStatus.'). '.($gatewayDetail ?: 'Periksa konfigurasi dan coba kembali.'),
                };

                return response()->json([
                    'message' => $message,
                    'code' => 'PAYMENT_GATEWAY_REJECTED',
                    'gateway_status' => $gatewayStatus,
                ], 502);
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

        return response()->json([
            'message' => 'Payment gateway belum dikonfigurasi. Pembayaran tidak dapat dibuat dan status tidak akan diubah menjadi PAID.',
            'code' => 'PAYMENT_PROVIDER_NOT_CONFIGURED',
        ], 503);
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
                'customer_record_id' => $locked->customer_record_id,
                'source_type' => 'BOOKING',
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
            'desired_departure_at' => ['required', 'date', 'after:now'],
            'desired_arrival_at' => ['required', 'date', 'after:desired_departure_at'],
            'cargo_type' => ['required', 'string', 'max:120'],
            'cargo_description' => ['nullable', 'string', 'max:3000'],
            'package_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'total_weight' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'weight_unit' => ['required', 'string', 'max:10'],
            'total_volume' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'volume_unit' => ['required', 'string', 'max:10'],
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
