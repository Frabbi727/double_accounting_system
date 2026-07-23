<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'NotoBengali';
            font-style: normal;
            font-weight: 400;
            src: url('{{ base_path('resources/fonts/NotoSansBengali-Regular.ttf') }}') format('truetype');
        }
        * { font-family: 'NotoBengali', sans-serif; }
        body { color: #111; font-size: 11px; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .shop { color: #555; margin: 0 0 12px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 7px; text-align: left; border-bottom: 1px solid #ddd; }
        thead th { border-bottom: 2px solid #333; font-weight: 700; }
        td.num, th.num { text-align: right; }
    </style>
</head>
<body>
    <div class="shop">{{ $shop }}</div>
    <h1>{{ $title }}</h1>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $i => $h)
                    <th class="{{ $i === 0 ? '' : 'num' }}">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        <td class="{{ $i === 0 ? '' : 'num' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
