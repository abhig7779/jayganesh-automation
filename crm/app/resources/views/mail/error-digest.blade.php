Errors logged by the Jay Ganesh CRM since {{ $since->copy()->timezone('Asia/Kolkata')->format('d M Y, H:i') }} IST.

@foreach ($groups as $g)
{{ $g['count'] }}x  {{ $g['level'] }}  (last at {{ $g['last'] }} IST)
    {!! $g['message'] !!}

@endforeach
Full details are in storage/logs on the server. Check the Health page too: {{ url('/health') }}

To stop these emails, set ERROR_DIGEST_ENABLED=false in the server .env and run: php artisan config:cache
