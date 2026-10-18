<x-card title="Metode pembayaran pendaftar">
<p><strong>{{ \App\Support\AdmissionPayment::label($record->value('payment_method')) }}</strong></p>
@if($record->value('payment_method') === 'transfer')
@php($proofs = \App\Support\CloudData::table('school_documents')->where('record_id', $record->id)->get()->filter(fn ($doc) => in_array($doc->field ?? '', ['payment_proof', 'transfer_proof', 'bukti_transfer'])))
@forelse($proofs as $proof)
<p><a class="btn secondary" href="/admin/dokumen/{{ $proof->id }}">Unduh bukti transfer · {{ $proof->name }}</a></p>
@empty
<p class="muted">Bukti transfer belum tersedia dari pendaftaran.</p>
@endforelse
<p class="muted">Periksa bukti transfer dan cocokkan dana masuk sebelum mencatat pembayaran.</p>
@endif
</x-card>
