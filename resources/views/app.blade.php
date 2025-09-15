<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @php
      // IMPORTANT: these must match your actual route NAMES (check php artisan route:list)
      $routeFilters = [
        'dashboard',
        'login',
        'logout',

        'register', // include for registration

        // Password reset routes
        'password.request',
        'password.email', 
        'password.reset',
        'password.store',
        'password.update',
        'password.confirm',

        'profile.*',

        // App sections
        'shipments.*',
        'settings.*',

        // Courier performance (use the REAL name; change to 'courier-performance' if that's how it's defined)
        'courier.performance',

        // Test routes used in the menu
        'test.courier-api',
        'test.acs-credentials',
      ];

      if (auth()->user()?->isSuperAdmin()) {
          $routeFilters[] = 'super-admin.*';
      }

      $ziggy = new \Tighten\Ziggy\Ziggy();
      $ziggy = $ziggy->filter($routeFilters);
    @endphp
    <script>window.Ziggy = {!! $ziggy->toJson() !!};</script>
    @routes  {{-- ✅ inject ziggy-js route() properly --}}

    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead
  </head>
  <body class="font-sans antialiased">
    @inertia
  </body>
</html>
