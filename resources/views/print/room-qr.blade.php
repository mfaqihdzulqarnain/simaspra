<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #0F1E33; text-align: center; }
        .kepala { margin-bottom: 6mm; }
        .kepala img { height: 14mm; }
        .instansi { font-size: 9pt; color: #475467; margin-top: 2mm; }
        .label { font-size: 9pt; letter-spacing: 2pt; text-transform: uppercase; color: #667085; margin-top: 4mm; }
        .nama { font-size: 26pt; font-weight: bold; margin: 2mm 0 1mm; }
        .lokasi { font-size: 10pt; color: #475467; }
        .qr { margin: 8mm auto 4mm; width: 78mm; height: 78mm; }
        .ajakan { font-size: 12pt; font-weight: bold; margin-top: 2mm; }
        .ket { font-size: 9pt; color: #475467; margin-top: 1.5mm; }
        .url { font-size: 8pt; color: #667085; margin-top: 5mm; font-family: DejaVu Sans Mono, monospace; }
        .kode { font-size: 8pt; color: #98A2B3; margin-top: 1mm; }
    </style>
</head>

<body>
    <div class="kepala">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div class="instansi">{{ $institution }}</div>
    </div>

    <div class="label">Ruang</div>
    <div class="nama">{{ $room->name }}</div>
    <div class="lokasi">
        {{ collect([$room->building?->name, $room->floor ? 'Lantai '.$room->floor : null])->filter()->implode(' · ') }}
        @if ($room->capacity)
            · Kapasitas {{ $room->capacity }} orang
        @endif
    </div>

    <img class="qr" src="{{ $qr }}" alt="QR">

    <div class="ajakan">Pindai untuk melihat jadwal ruang</div>
    <div class="ket">Jadwal hari ini dan 7 hari ke depan{{ $room->is_bookable ? ', serta pemesanan ruang' : '' }}.</div>

    <div class="url">{{ $url }}</div>
    <div class="kode">{{ $room->code }} · {{ $appName }}</div>
</body>

</html>