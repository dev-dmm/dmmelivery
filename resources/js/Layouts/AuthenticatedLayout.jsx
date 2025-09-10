import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js'; // <-- import route
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
  const user = usePage().props.auth.user;
  const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

  const isSuperAdmin = () => {
    if (user.role === 'super_admin') return true;
    const superAdminEmails = ['admin@dmm.gr', 'dev@dmm.gr', 'super@dmm.gr'];
    return superAdminEmails.includes(user.email);
  };

  return (
    <div className="min-h-screen bg-gray-100">
      <nav className="border-b border-gray-100 bg-white">
        <div className="container">
          <div className="flex h-16 justify-between">
            <div className="flex">
              <div className="flex shrink-0 items-center">
                <Link href="/">
                  <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                </Link>
              </div>

              <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                  Dashboard
                </NavLink>
                <NavLink href={route('courier.performance')} active={route().current('courier.performance')}>
                  📊 Απόδοση Courier
                </NavLink>
                <NavLink href={route('shipments.index')} active={route().current('shipments.index')}>
                  Shipments Dashboard
                </NavLink>
                <NavLink href={route('orders.import.index')} active={route().current('orders.import.*')}>
                  📦 Order Import
                </NavLink>
                <NavLink href={route('settings.index')} active={route().current('settings.*')}>
                  ⚙️ Settings
                </NavLink>

                {isSuperAdmin() && (
                  <div className="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium text-gray-500">
                    <Dropdown>
                      <Dropdown.Trigger>
                        <button className="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300">
                          👑 Super Admin
                          <svg className="ml-1 -mr-0.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                          </svg>
                        </button>
                      </Dropdown.Trigger>
                      <Dropdown.Content>
                        <Dropdown.Link href={route('super-admin.dashboard')}>📊 Dashboard</Dropdown.Link>
                        <Dropdown.Link href={route('super-admin.orders')}>📦 All Orders</Dropdown.Link>
                        <Dropdown.Link href={route('super-admin.tenants')}>🏢 All Tenants</Dropdown.Link>
                        <Dropdown.Link href={route('super-admin.users')}>👥 All Users</Dropdown.Link>
                      </Dropdown.Content>
                    </Dropdown>
                  </div>
                )}

                <NavLink href={route('test.courier-api')} active={route().current('test.courier-api')}>
                  🧪 API Test
                </NavLink>
                <NavLink href={route('test.acs-credentials')} active={route().current('test.acs-credentials')}>
                  🔑 ACS Test
                </NavLink>
              </div>
            </div>

            <div className="hidden sm:ms-6 sm:flex sm:items-center">
              <div className="relative ms-3">
                <Dropdown>
                  <Dropdown.Trigger>
                    <span className="inline-flex rounded-md">
                      <button
                        type="button"
                        className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 hover:text-gray-700"
                      >
                        {user.name}
                        <svg className="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                      </button>
                    </span>
                  </Dropdown.Trigger>
                  <Dropdown.Content>
                    <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                    <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                  </Dropdown.Content>
                </Dropdown>
              </div>
            </div>

            <div className="-me-2 flex items-center sm:hidden">
              <button
                onClick={() => setShowingNavigationDropdown(prev => !prev)}
                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500"
              >
                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  {/* hamburger / close */}
                  <path className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                  <path className={showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
          <div className="space-y-1 pb-3 pt-2">
            <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>Dashboard</ResponsiveNavLink>

            {/* Match name with your real route */}
            <ResponsiveNavLink href={route('courier.performance')} active={route().current('courier.performance')}>
              📊 Απόδοση Courier
            </ResponsiveNavLink>

            <ResponsiveNavLink href={route('shipments.index')} active={route().current('shipments.*')}>
              Shipments
            </ResponsiveNavLink>

            <ResponsiveNavLink href={route('orders.import.index')} active={route().current('orders.import.*')}>
              📦 Order Import
            </ResponsiveNavLink>

            <ResponsiveNavLink href={route('settings.index')} active={route().current('settings.*')}>
              ⚙️ Settings
            </ResponsiveNavLink>

            {isSuperAdmin() && (
              <>
                <ResponsiveNavLink href={route('super-admin.dashboard')} active={route().current('super-admin.dashboard')}>📊 Super Admin Dashboard</ResponsiveNavLink>
                <ResponsiveNavLink href={route('super-admin.orders')} active={route().current('super-admin.orders')}>📦 All Orders</ResponsiveNavLink>
                <ResponsiveNavLink href={route('super-admin.tenants')} active={route().current('super-admin.tenants')}>🏢 All Tenants</ResponsiveNavLink>
                <ResponsiveNavLink href={route('super-admin.users')} active={route().current('super-admin.users')}>👥 All Users</ResponsiveNavLink>
              </>
            )}

            <ResponsiveNavLink href={route('test.courier-api')} active={route().current('test.courier-api')}>🧪 API Test</ResponsiveNavLink>
          </div>

          <div className="border-t border-gray-200 pb-1 pt-4">
            <div className="px-4">
              <div className="text-base font-medium text-gray-800">{user?.name}</div>
              <div className="text-sm font-medium text-gray-500">{user?.email}</div>
            </div>

            <div className="mt-3 space-y-1">
              <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
              <ResponsiveNavLink method="post" href={route('logout')} as="button">Log Out</ResponsiveNavLink>
            </div>
          </div>
        </div>
      </nav>

      {header && (
        <header className="bg-white shadow">
          <div className="mx-auto container px-4 py-6 sm:px-6 lg:px-8">{header}</div>
        </header>
      )}
      <main className="container">{children}</main>
    </div>
  );
}
