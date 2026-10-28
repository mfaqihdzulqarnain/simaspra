<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }

        table.label {
            width: 56mm;
            height: 36mm;
            margin: 2mm;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.label td { padding: 0; vertical-align: middle; }

        td.qr { width: 26mm; }
        td.qr img { width: 26mm; height: 26mm; display: block; }

        td.txt { width: 30mm; padding-left: 1.5mm; }

        .inst { font-size: 6pt; color: #333; margin: 0 0 0.5mm; }
        .name { font-size: 6pt; font-weight: bold; line-height: 1.15; margin: 0 0 0.5mm; }
        .code { font-size: 6pt; font-weight: bold; margin: 0 0 0.5mm; }
        .meta { font-size: 6pt; color: #444; line-height: 1.2; margin: 0; }
        .logo { height: 8.5mm; width: auto; margin: 0 0 0.5mm; }
    </style>
</head>
<body>
@foreach ($assets as $asset)
    <table class="label" @if (! $loop->last) style="page-break-after: always;" @endif>
        <tr>
            <td class="qr">
                <img src="{{ $qrs[$asset->id] }}" alt="">
            </td>
            <td class="txt">
                @if ($labelLogo)
                    <img src="{{ $labelLogo }}" class="logo" alt="">
                @endif
                <p class="inst">{{ $institution }}</p>
                <p class="name">{{ \Illuminate\Support\Str::limit($asset->name, 26) }}</p>
                <p class="code">{{ $asset->code }}</p>
                <p class="meta">
                    {{ $asset->year_acquired }}<br>
                    {{ \Illuminate\Support\Str::limit($asset->unit?->name ?? '-', 18) }}
                </p>
            </td>
        </tr>
    </table>
@endforeach
</body>
</html>