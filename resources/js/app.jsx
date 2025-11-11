import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

// Configure CSRF token for Inertia
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Map Inertia component names to feature-based paths
const featureMap = {
    // Dashboard
    'Dashboard': './features/dashboard/pages/Dashboard.jsx',
    'RealtimeDashboard': './features/dashboard/pages/RealtimeDashboard.jsx',
    
    // Shipments
    'Shipments/Index': './features/shipments/pages/Index.jsx',
    'Shipments/Create': './features/shipments/pages/Create.jsx',
    'Shipments/Show': './features/shipments/pages/Show.jsx',
    'Shipments/Search': './features/shipments/pages/Search.jsx',
    
    // Customers
    'Customers/Show': './features/customers/pages/Show.jsx',
    
    // Analytics
    'Analytics/Index': './features/analytics/pages/Index.jsx',
    'Analytics/AdvancedDashboard': './features/analytics/pages/AdvancedDashboard.jsx',
    'Analytics/Export': './features/analytics/pages/Export.jsx',
    
    // Orders
    'Orders/Index': './features/orders/pages/Index.jsx',
    'Orders/Show': './features/orders/pages/Show.jsx',
    
    // Alerts
    'Alerts/Index': './features/alerts/pages/Index.jsx',
    'Alerts/Rules': './features/alerts/pages/Rules.jsx',
    
    // Notifications
    'Notifications/Index': './features/notifications/pages/Index.jsx',
    
    // Auth
    'Auth/Login': './features/auth/pages/Login.jsx',
    'Auth/Register': './features/auth/pages/Register.jsx',
    'Auth/ForgotPassword': './features/auth/pages/ForgotPassword.jsx',
    'Auth/ResetPassword': './features/auth/pages/ResetPassword.jsx',
    'Auth/ConfirmPassword': './features/auth/pages/ConfirmPassword.jsx',
    'Auth/VerifyEmail': './features/auth/pages/VerifyEmail.jsx',
    'Registration/EShopRegister': './features/auth/pages/EShopRegister.jsx',
    
    // Profile
    'Profile/Edit': './features/profile/pages/Edit.jsx',
    'Profile/Partials/UpdateProfileInformationForm': './features/profile/pages/Partials/UpdateProfileInformationForm.jsx',
    'Profile/Partials/UpdatePasswordForm': './features/profile/pages/Partials/UpdatePasswordForm.jsx',
    'Profile/Partials/DeleteUserForm': './features/profile/pages/Partials/DeleteUserForm.jsx',
    
    // Settings
    'Settings/Index': './features/settings/pages/Index.jsx',
    
    // Super Admin
    'SuperAdmin/Dashboard': './features/super-admin/pages/Dashboard.jsx',
    'SuperAdmin/Tenants': './features/super-admin/pages/Tenants.jsx',
    'SuperAdmin/TenantDetails': './features/super-admin/pages/TenantDetails.jsx',
    'SuperAdmin/Users': './features/super-admin/pages/Users.jsx',
    'SuperAdmin/UserDetails': './features/super-admin/pages/UserDetails.jsx',
    'SuperAdmin/Orders': './features/super-admin/pages/Orders.jsx',
    'SuperAdmin/OrderItems': './features/super-admin/pages/OrderItems.jsx',
    'SuperAdmin/TenantCouriers': './features/super-admin/pages/TenantCouriers.jsx',
    
    // Help
    'Help/Index': './features/help/pages/Index.jsx',
    'Help/GettingStarted': './features/help/pages/GettingStarted.jsx',
    'Help/Shipments': './features/help/pages/Shipments.jsx',
    'Help/Analytics': './features/help/pages/Analytics.jsx',
    'Help/Notifications': './features/help/pages/Notifications.jsx',
    'Help/ShipmentsCreateFirst': './features/help/pages/ShipmentsCreateFirst.jsx',
    'Help/NotificationsSetup': './features/help/pages/NotificationsSetup.jsx',
    'Help/DashboardOverview': './features/help/pages/DashboardOverview.jsx',
    
    // Chatbot
    'Chatbot/Index': './features/chatbot/pages/Index.jsx',
    'Chatbot/Chat': './features/chatbot/pages/Chat.jsx',
    
    // Predictive ETA
    'PredictiveEta/Index': './features/predictive-eta/pages/Index.jsx',
    'PredictiveEta/Show': './features/predictive-eta/pages/Show.jsx',
    
    // Courier Performance
    'CourierPerformance': './features/courier-performance/pages/CourierPerformance.jsx',
    
    // Courier Reports
    'CourierReports/Import/Index': './features/courier-reports/pages/Import/Index.jsx',
    
    // Home & Welcome
    'Home': './features/home/pages/Home.jsx',
    'Welcome': './features/welcome/pages/Welcome.jsx',
    
    // Users
    'Users/Index': './features/users/pages/Index.jsx',
};

// Glob all feature pages
const pageModules = import.meta.glob('./features/**/pages/**/*.jsx');

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        // Check if we have a direct mapping
        const mappedPath = featureMap[name];
        if (mappedPath) {
            return resolvePageComponent(mappedPath, pageModules);
        }
        
        // Fallback: try to construct path from name
        // Convert "Feature/SubFeature" to "./features/feature/pages/SubFeature.jsx"
        const parts = name.split('/');
        if (parts.length === 1) {
            // Single part like "Dashboard" - try common features
            const featureName = name.toLowerCase().replace(/([A-Z])/g, '-$1').toLowerCase();
            const fallbackPath = `./features/${featureName}/pages/${name}.jsx`;
            return resolvePageComponent(fallbackPath, pageModules);
        } else {
            // Multi-part like "Shipments/Index"
            const featureName = parts[0].toLowerCase().replace(/([A-Z])/g, '-$1').toLowerCase();
            const pageName = parts.slice(1).join('/');
            const fallbackPath = `./features/${featureName}/pages/${pageName}.jsx`;
            return resolvePageComponent(fallbackPath, pageModules);
        }
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
