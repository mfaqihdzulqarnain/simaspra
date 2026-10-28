<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ \App\Support\Sapra::appName() }}</title>
    @if ($favicon = \App\Support\Sapra::favicon())
        <link rel="icon" href="{{ $favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary: {{ \App\Support\Sapra::primaryColor() }};
        }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
        }
    </style>
</head>

<body class="min-h-full bg-slate-50 flex items-center justify-center p-5">

    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white border border-slate-200 p-8 text-center">

            @if ($logo = \App\Support\Sapra::logoLight())
                <img src="{{ $logo }}" alt="" class="h-12 w-auto mx-auto mb-5">
            @endif

            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full {{ $iconBg }}">
                {!! $icon !!}
            </div>

            <p class="text-xs font-semibold tracking-widest text-slate-400 mb-2">{{ $code }}</p>
            <h1 class="text-xl font-bold text-slate-900 mb-2">{{ $title }}</h1>
            <p class="text-sm text-slate-600 leading-relaxed mb-6">{{ $message }}</p>

            <div class="space-y-2">
                {!! $actions !!}
            </div>
        </div>

        <p class="text-center text-xs text-slate-400 mt-5">
            {{ \App\Support\Sapra::institutionName() }}
        </p>
    </div>

</body>

</html>
