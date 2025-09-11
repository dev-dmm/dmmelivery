// resources/js/Layouts/AuthenticatedLayout.jsx
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, router, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
  const { props } = usePage();
  const user   = props?.auth?.user;
  const tenant = props?.auth?.tenant ?? props?.tenant; // support both shapes
  const ziggyRoutes = props?.ziggy?.routes || {};
  const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

  // Safe route-existence check using routes shared via HandleInertiaRequests
  const hasRoute = (name) => !!ziggyRoutes[name];

  const isSuperAdmin = () => {
    if (!user) return false;
    return user.role === 'super_admin';
  };

  function handleLogout(e) {
    e.preventDefault();
    router.post(route('logout')); // sends CSRF + session cookie
  }

  return (
    <div className="min-h-screen bg-gray-100">
      <nav className="border-b border-gray-100 bg-white">
        <div className="container">
          <div className="flex h-16 justify-between">
            {/* Left: brand + primary nav */}
            <div className="flex">
              <div className="flex shrink-0 items-center">
                <Link href={hasRoute('dashboard') ? route('dashboard') : '/'}>
                  <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                </Link>
              </div>

              <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                {hasRoute('dashboard') && (
                  <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                    Dashboard
                  </NavLink>
                )}

                {hasRoute('courier.performance') && (
                  <NavLink href={route('courier.performance')} active={route().current('courier.performance')}>
                    📊 Απόδοση Courier
                  </NavLink>
                )}

                {hasRoute('orders.index') && (
                  <NavLink href={route('orders.index')} active={route().current('orders.*')}>
                    📦 Orders
                  </NavLink>
                )}

                {hasRoute('shipments.index') && (
                  <NavLink href={route('shipments.index')} active={route().current('shipments.*')}>
                    Shipments Dashboard
                  </NavLink>
                )}

                {hasRoute('orders.import.index') && (
                  <NavLink href={route('orders.import.index')} active={route().current('orders.import.*')}>
                    📥 Order Import
                  </NavLink>
                )}

                {hasRoute('settings.index') && (
                  <NavLink href={route('settings.index')} active={route().current('settings.*')}>
                    ⚙️ Settings
                  </NavLink>
                )}

                {isSuperAdmin() && (
                  <div className="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium text-gray-500">
                    <Dropdown>
                      <Dropdown.Trigger>
                        <button className="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300">
                          👑 Super Admin
                          <svg className="ml-1 -mr-0.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path
                              fillRule="evenodd"
                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                              clipRule="evenodd"
                            />
                          </svg>
                        </button>
                      </Dropdown.Trigger>
                      <Dropdown.Content>
                        {hasRoute('super-admin.dashboard') && (
                          <Dropdown.Link href={route('super-admin.dashboard')}>📊 Dashboard</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.orders') && (
                          <Dropdown.Link href={route('super-admin.orders')}>📦 All Orders</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.tenants') && (
                          <Dropdown.Link href={route('super-admin.tenants')}>🏢 All Tenants</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.users') && (
                          <Dropdown.Link href={route('super-admin.users')}>👥 All Users</Dropdown.Link>
                        )}
                      </Dropdown.Content>
                    </Dropdown>
                  </div>
                )}

                {hasRoute('test.courier-api') && (
                  <NavLink href={route('test.courier-api')} active={route().current('test.courier-api')}>
                    🧪 API Test
                  </NavLink>
                )}
                {hasRoute('test.acs-credentials') && (
                  <NavLink href={route('test.acs-credentials')} active={route().current('test.acs-credentials')}>
                    🔑 ACS Test
                  </NavLink>
                )}
              </div>
            </div>

            {/* Right: user / tenant menu */}
            <div className="hidden sm:ms-6 sm:flex sm:items-center">
              <div className="relative ms-3">
                <Dropdown>
                  <Dropdown.Trigger>
                    <span className="inline-flex rounded-md">
                      <button
                        type="button"
                        className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 hover:text-gray-700"
                      >
                        {tenant?.name ?? user?.name ?? 'Account'}
                        <svg className="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path
                            fillRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clipRule="evenodd"
                          />
                        </svg>
                      </button>
                    </span>
                  </Dropdown.Trigger>
                  <Dropdown.Content>
                    {hasRoute('profile.edit') && (
                      <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                    )}
                    <button
                      onClick={handleLogout}
                      className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                    >
                      Log Out
                    </button>
                  </Dropdown.Content>
                </Dropdown>
              </div>
            </div>

            {/* Mobile hamburger */}
            <div className="-me-2 flex items-center sm:hidden">
              <button
                onClick={() => setShowingNavigationDropdown((prev) => !prev)}
                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500"
              >
                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path
                    className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        {/* Mobile menu */}
        <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
          <div className="space-y-1 pb-3 pt-2">
            {hasRoute('dashboard') && (
              <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                Dashboard
              </ResponsiveNavLink>
            )}

            {hasRoute('courier.performance') && (
              <ResponsiveNavLink href={route('courier.performance')} active={route().current('courier.performance')}>
                📊 Απόδοση Courier
              </ResponsiveNavLink>
            )}

            {hasRoute('orders.index') && (
              <ResponsiveNavLink href={route('orders.index')} active={route().current('orders.*')}>
                📦 Orders
              </ResponsiveNavLink>
            )}

            {hasRoute('shipments.index') && (
              <ResponsiveNavLink href={route('shipments.index')} active={route().current('shipments.*')}>
                Shipments
              </ResponsiveNavLink>
            )}

            {hasRoute('orders.import.index') && (
              <ResponsiveNavLink href={route('orders.import.index')} active={route().current('orders.import.*')}>
                📥 Order Import
              </ResponsiveNavLink>
            )}

            {hasRoute('settings.index') && (
              <ResponsiveNavLink href={route('settings.index')} active={route().current('settings.*')}>
                ⚙️ Settings
              </ResponsiveNavLink>
            )}

            {isSuperAdmin() && (
              <>
                {hasRoute('super-admin.dashboard') && (
                  <ResponsiveNavLink href={route('super-admin.dashboard')} active={route().current('super-admin.dashboard')}>
                    📊 Super Admin Dashboard
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.orders') && (
                  <ResponsiveNavLink href={route('super-admin.orders')} active={route().current('super-admin.orders')}>
                    📦 All Orders
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.tenants') && (
                  <ResponsiveNavLink href={route('super-admin.tenants')} active={route().current('super-admin.tenants')}>
                    🏢 All Tenants
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.users') && (
                  <ResponsiveNavLink href={route('super-admin.users')} active={route().current('super-admin.users')}>
                    👥 All Users
                  </ResponsiveNavLink>
                )}
              </>
            )}

            {hasRoute('test.courier-api') && (
              <ResponsiveNavLink href={route('test.courier-api')} active={route().current('test.courier-api')}>
                🧪 API Test
              </ResponsiveNavLink>
            )}
          </div>

          <div className="border-t border-gray-200 pb-1 pt-4">
            <div className="px-4">
              <div className="text-base font-medium text-gray-800">{tenant?.name ?? user?.name}</div>
              <div className="text-sm font-medium text-gray-500">{user?.email}</div>
            </div>

            <div className="mt-3 space-y-1">
              {hasRoute('profile.edit') && <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>}
              <button
                onClick={handleLogout}
                className="block w-full px-4 py-2 text-start text-base font-medium text-gray-500 transition duration-150 ease-in-out hover:bg-gray-50 hover:text-gray-700 focus:bg-gray-50 focus:text-gray-700 focus:outline-none"
              >
                Log Out
              </button>
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
