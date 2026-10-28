<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
        }

        .sheet {
            position: relative;
            width: 194mm;
            height: 280mm;
        }

        .cell {
            position: absolute;
            height: 56mm;
            padding: 2mm;
            border: 0.2mm dashed #ccc;
        }

        .inst {
            font-size: 7pt;
            color: #333;
            margin: 0 0 0.8mm;
        }

        .name {
            font-size: 8pt;
            font-weight: bold;
            line-height: 1.2;
            margin: 0 0 0.8mm;
        }

        .code {
            font-size: 9pt;
            font-weight: bold;
            margin: 0 0 0.8mm;
        }

        .meta {
            font-size: 7pt;
            color: #444;
            line-height: 1.3;
            margin: 0;
        }

        .logo {
            height: 8mm;
            width: auto;
            margin: 0 0 0.8mm;
        }
    </style>
</head>

<body>
    @foreach ($sheets as $sheetRows)
        <div class="sheet">
            @foreach ($sheetRows as $r => $row)
                @foreach ($row as $c => $cell)
                    <div class="cell" style="left: {{ $c * 97 }}mm; top: {{ $r * 56 }}mm; width: 93mm;">
                        @if ($cell !== null)
                            <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
                                <tr>
                                    <td style="width:40mm; padding:0; vertical-align:top;">
                                        <img src="{{ $qrs[$cell->id] }}" style="width:40mm; height:40mm; display:block;"
                                            alt="">
                                    </td>
                                    <td style="width:60mm; padding:0 0 0 4mm; vertical-align:top;">
                                        @if ($labelLogo)
                                            <img src="{{ $labelLogo }}" class="logo" alt="">
                                        @endif
                                        <p class="inst">{{ $institution }}</p>
                                        <p class="name">{{ \Illuminate\Support\Str::limit($cell->name, 32) }}</p>
                                        <p class="code">{{ $cell->code }}</p>
                                        <p class="meta">
                                            {{ $cell->year_acquired }}<br>
                                            {{ \Illuminate\Support\Str::limit($cell->unit?->name ?? '-', 22) }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
        @if (!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
</body>

</html>
