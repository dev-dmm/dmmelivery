# Frontend Structure Reorganization

## Overview

The frontend has been reorganized from a page-based structure to a feature-based structure for better organization and maintainability.

## New Structure

```
resources/js/
├── features/
│   ├── shipments/
│   │   ├── components/     # Shipment-specific components
│   │   ├── hooks/          # Shipment-specific hooks
│   │   └── pages/          # Shipment pages (Index, Create, Show, Search)
│   ├── analytics/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Analytics pages (Index, AdvancedDashboard, Export)
│   ├── orders/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Order pages (Index, Show)
│   ├── alerts/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Alert pages (Index, Rules)
│   ├── notifications/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Notification pages (Index)
│   ├── dashboard/
│   │   ├── components/     # Dashboard-specific components
│   │   │   ├── AlertBanner.jsx
│   │   │   ├── BatchActions.jsx
│   │   │   ├── CustomizableDashboard.jsx
│   │   │   ├── EnhancedStatCard.jsx
│   │   │   ├── OnboardingHelp.jsx
│   │   │   ├── RealTimeDashboard.jsx
│   │   │   ├── SnapshotOverview.jsx
│   │   │   └── StatsDrillDown.jsx
│   │   ├── hooks/
│   │   └── pages/          # Dashboard pages (Dashboard, RealtimeDashboard)
│   ├── auth/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Auth pages (Login, Register, etc.)
│   ├── profile/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Profile pages (Edit, Partials)
│   ├── settings/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Settings pages (Index)
│   ├── super-admin/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Super admin pages
│   ├── help/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Help pages
│   ├── chatbot/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Chatbot pages
│   ├── predictive-eta/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Predictive ETA pages
│   ├── courier-performance/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Courier performance pages
│   ├── courier-reports/
│   │   ├── components/
│   │   ├── hooks/
│   │   └── pages/          # Courier reports pages
│   ├── home/
│   │   └── pages/          # Home page
│   ├── welcome/
│   │   └── pages/          # Welcome page
│   └── users/
│       └── pages/          # User management pages
├── Components/             # Shared components (UI, forms, etc.)
│   ├── ui/                 # UI components (Button, Card, Badge, etc.)
│   ├── ApplicationLogo.jsx
│   ├── Checkbox.jsx
│   ├── DangerButton.jsx
│   ├── Dropdown.jsx
│   ├── InputError.jsx
│   ├── InputLabel.jsx
│   ├── Modal.jsx
│   ├── NavLink.jsx
│   ├── PerformanceOptimizedList.jsx
│   ├── PrimaryButton.jsx
│   ├── ResponsiveNavLink.jsx
│   ├── SecondaryButton.jsx
│   └── TextInput.jsx
├── Layouts/                # Layout components
│   ├── AuthenticatedLayout.jsx
│   └── GuestLayout.jsx
├── hooks/                  # Shared hooks
│   └── useWebSocket.js
├── utils/                  # Utility functions
│   └── cn.js
├── app.jsx                 # Main app entry point
├── bootstrap.js
└── ziggy.js
```

## Key Changes

### 1. Feature-Based Organization
- All pages are now organized by feature in `resources/js/features/`
- Each feature has its own `components/`, `hooks/`, and `pages/` directories
- This makes it easier to find and maintain feature-specific code

### 2. Component Organization
- **Feature-specific components**: Moved to their respective feature directories
  - Dashboard components moved to `features/dashboard/components/`
- **Shared components**: Remain in `resources/js/Components/`
  - UI components (Button, Card, Badge, etc.)
  - Form components (InputLabel, TextInput, etc.)
  - Layout components remain in `resources/js/Layouts/`

### 3. Updated Import Paths
- Feature components: `@/features/{feature}/components/{Component}`
- Feature pages: `@/features/{feature}/pages/{Page}`
- Shared components: `@/Components/{Component}` (unchanged)
- Layouts: `@/Layouts/{Layout}` (unchanged)

### 4. App Configuration
- Updated `app.jsx` to resolve pages from the new feature-based structure
- Added a feature map for explicit component name to path mapping
- Added fallback logic for automatic path resolution

### 5. Removed Old Structure
- Removed empty `resources/js/Pages/` directory
- Updated `resources/views/app.blade.php` to remove old Pages reference

## Benefits

1. **Better Organization**: Related code is grouped together by feature
2. **Easier Navigation**: Find all code for a feature in one place
3. **Scalability**: Easy to add new features without cluttering the root
4. **Maintainability**: Clear separation of concerns
5. **Team Collaboration**: Multiple developers can work on different features without conflicts

## Migration Notes

- All import paths for dashboard components have been updated
- Inertia component names remain the same (e.g., "Dashboard", "Shipments/Index")
- The app.jsx resolver handles the mapping between Inertia names and file paths
- No changes needed to backend routes or Inertia render calls

## Next Steps

When adding new features:
1. Create a new directory under `resources/js/features/{feature-name}/`
2. Add `components/`, `hooks/`, and `pages/` subdirectories
3. Add the feature mapping to `app.jsx` if needed (or rely on fallback)
4. Use feature-specific components within the feature directory
5. Use shared components from `@/Components/` when appropriate

