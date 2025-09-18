<?php
/**
 * Debug script to check frontend route detection
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Frontend Route Debug ===\n";

// Check user
$user = App\Models\User::where('email', 'admin@dmm.gr')->first();
if ($user) {
    echo "✅ User found: {$user->email}\n";
    echo "   Role: {$user->role}\n";
    echo "   Is Super Admin: " . ($user->isSuperAdmin() ? 'YES' : 'NO') . "\n";
} else {
    echo "❌ User not found!\n";
    exit(1);
}

// Check routes
$routes = app('router')->getRoutes();
$superAdminRoutes = [];
foreach ($routes as $route) {
    if (strpos($route->getName(), 'super-admin.') === 0) {
        $superAdminRoutes[] = $route->getName();
    }
}

echo "\nSuper admin routes:\n";
foreach ($superAdminRoutes as $route) {
    echo "  - {$route}\n";
}

// Test the middleware
echo "\n=== Testing Middleware ===\n";
$request = \Illuminate\Http\Request::create('/dashboard', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

$middleware = new \App\Http\Middleware\HandleInertiaRequests();
$shared = $middleware->share($request);

echo "Ziggy routes in shared data:\n";
if (isset($shared['ziggy']['routes'])) {
    $ziggyRoutes = $shared['ziggy']['routes'];
    $superAdminZiggyRoutes = [];
    
    foreach ($ziggyRoutes as $name => $route) {
        if (strpos($name, 'super-admin.') === 0) {
            $superAdminZiggyRoutes[] = $name;
        }
    }
    
    if (empty($superAdminZiggyRoutes)) {
        echo "  ❌ No super admin routes in Ziggy!\n";
        echo "  Available routes:\n";
        foreach (array_keys($ziggyRoutes) as $routeName) {
            echo "    - {$routeName}\n";
        }
    } else {
        echo "  ✅ Super admin routes in Ziggy:\n";
        foreach ($superAdminZiggyRoutes as $route) {
            echo "    - {$route}\n";
        }
    }
} else {
    echo "  ❌ No ziggy routes found!\n";
}
