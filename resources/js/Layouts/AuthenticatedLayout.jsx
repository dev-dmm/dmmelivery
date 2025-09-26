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
  const user   = props?.auth?.user?.data || props?.auth?.user;
  const tenant = props?.auth?.tenant?.data || props?.auth?.tenant || props?.tenant?.data || props?.tenant;
  const ziggyRoutes = props?.ziggy?.routes || {};
  const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

  // Safe route-existence check using routes shared via HandleInertiaRequests
  const hasRoute = (name) => {
    try {
      if (typeof route().has === 'function') return route().has(name);
    } catch (_) {
      /* fall through */
    }
    return !!ziggyRoutes[name]; // ziggyRoutes = usePage().props?.ziggy?.routes || {}
  };
  const isSuperAdmin = () => {
    if (!user) return false;
    return user.role === 'super_admin';
  };

  function handleLogout(e) {
    e.preventDefault();
    
    // Get CSRF token from meta tag
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (!token) {
      console.error('CSRF token not found');
      return;
    }
    
    router.post(route('logout'), {}, {
      headers: {
        'X-CSRF-TOKEN': token.content,
      },
      onError: (errors) => {
        console.error('Logout error:', errors);
      },
      onSuccess: () => {
        console.log('Logout successful');
      }
    });
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

              <div className="hidden space-x-2 lg:space-x-8 sm:-my-px sm:ms-10 sm:flex">
                {hasRoute('dashboard') && (
                  <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                    Πίνακας Ελέγχου
                  </NavLink>
                )}

                {hasRoute('courier.performance') && (
                  <NavLink href={route('courier.performance')} active={route().current('courier.performance')}>
                    <span className="hidden lg:inline">📊 Απόδοση Courier</span>
                    <span className="lg:hidden">📊 Courier</span>
                  </NavLink>
                )}

                {hasRoute('orders.index') && (
                  <NavLink href={route('orders.index')} active={route().current('orders.*')}>
                    <span className="hidden lg:inline">📦 Παραγγελίες</span>
                    <span className="lg:hidden">📦 Παραγγελίες</span>
                  </NavLink>
                )}

                {hasRoute('shipments.index') && (
                  <NavLink href={route('shipments.index')} active={route().current('shipments.*')}>
                    <span className="hidden lg:inline">Πίνακας Αποστολών</span>
                    <span className="lg:hidden">Αποστολές</span>
                  </NavLink>
                )}

                {hasRoute('predictive-eta.index') && (
                  <NavLink href={route('predictive-eta.index')} active={route().current('predictive-eta.*')}>
                    <span className="hidden lg:inline">🤖 Προγνωστικά ETA</span>
                    <span className="lg:hidden">🤖 ETA</span>
                  </NavLink>
                )}

                {hasRoute('alerts.index') && (
                  <NavLink href={route('alerts.index')} active={route().current('alerts.*')}>
                    <span className="hidden lg:inline">🚨 Ειδοποιήσεις</span>
                    <span className="lg:hidden">🚨 Ειδοποιήσεις</span>
                  </NavLink>
                )}

                {hasRoute('chatbot.index') && (
                  <NavLink href={route('chatbot.index')} active={route().current('chatbot.*')}>
                    <span className="hidden lg:inline">💬 AI Chatbot</span>
                    <span className="lg:hidden">💬 Chatbot</span>
                  </NavLink>
                )}


                {hasRoute('courier-reports.import.index') && (
                  <NavLink href={route('courier-reports.import.index')} active={route().current('courier-reports.import.*')}>
                    <span className="hidden lg:inline">📊 Αναφορές Courier</span>
                    <span className="lg:hidden">📊 Αναφορές</span>
                  </NavLink>
                )}

                {hasRoute('settings.index') && (
                  <NavLink href={route('settings.index')} active={route().current('settings.*')}>
                    <span className="hidden lg:inline">⚙️ Ρυθμίσεις</span>
                    <span className="lg:hidden">⚙️</span>
                  </NavLink>
                )}

                {isSuperAdmin() && (
                  <div className="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium text-gray-500">
                    <Dropdown>
                      <Dropdown.Trigger>
                        <button className="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300">
                          <span className="hidden lg:inline">👑 Super Admin</span>
                          <span className="lg:hidden">👑</span>
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
                          <Dropdown.Link href={route('super-admin.dashboard')}>📊 Πίνακας Ελέγχου</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.orders') && (
                          <Dropdown.Link href={route('super-admin.orders')}>📦 Όλες οι Παραγγελίες</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.order-items') && (
                          <Dropdown.Link href={route('super-admin.order-items')}>🛍️ Προϊόντα E-Shop</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.tenants') && (
                          <Dropdown.Link href={route('super-admin.tenants')}>🏢 Όλοι οι Ενοικιαστές</Dropdown.Link>
                        )}
                        {hasRoute('super-admin.users') && (
                          <Dropdown.Link href={route('super-admin.users')}>👥 Όλοι οι Χρήστες</Dropdown.Link>
                        )}
                      </Dropdown.Content>
                    </Dropdown>
                  </div>
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
                        {tenant?.business_name ?? tenant?.name ?? user?.name ?? 'Account'}
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
                      <Dropdown.Link href={route('profile.edit')}>Προφίλ</Dropdown.Link>
                    )}
                    <button
                      onClick={handleLogout}
                      className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                    >
                      Αποσύνδεση
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
                Πίνακας Ελέγχου
              </ResponsiveNavLink>
            )}

            {hasRoute('courier.performance') && (
              <ResponsiveNavLink href={route('courier.performance')} active={route().current('courier.performance')}>
                📊 Απόδοση Courier
              </ResponsiveNavLink>
            )}

            {hasRoute('orders.index') && (
              <ResponsiveNavLink href={route('orders.index')} active={route().current('orders.*')}>
                📦 Παραγγελίες
              </ResponsiveNavLink>
            )}

            {hasRoute('shipments.index') && (
              <ResponsiveNavLink href={route('shipments.index')} active={route().current('shipments.*')}>
                Αποστολές
              </ResponsiveNavLink>
            )}

            {hasRoute('predictive-eta.index') && (
              <ResponsiveNavLink href={route('predictive-eta.index')} active={route().current('predictive-eta.*')}>
                🤖 Προγνωστικά ETA
              </ResponsiveNavLink>
            )}

            {hasRoute('alerts.index') && (
              <ResponsiveNavLink href={route('alerts.index')} active={route().current('alerts.*')}>
                🚨 Ειδοποιήσεις
              </ResponsiveNavLink>
            )}

            {hasRoute('chatbot.index') && (
              <ResponsiveNavLink href={route('chatbot.index')} active={route().current('chatbot.*')}>
                💬 AI Chatbot
              </ResponsiveNavLink>
            )}


            {hasRoute('courier-reports.import.index') && (
              <ResponsiveNavLink href={route('courier-reports.import.index')} active={route().current('courier-reports.import.*')}>
                📊 Αναφορές Courier
              </ResponsiveNavLink>
            )}

            {hasRoute('settings.index') && (
              <ResponsiveNavLink href={route('settings.index')} active={route().current('settings.*')}>
                ⚙️ Ρυθμίσεις
              </ResponsiveNavLink>
            )}

            {isSuperAdmin() && (
              <>
                {hasRoute('super-admin.dashboard') && (
                  <ResponsiveNavLink href={route('super-admin.dashboard')} active={route().current('super-admin.dashboard')}>
                    📊 Super Admin Πίνακας Ελέγχου
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.orders') && (
                  <ResponsiveNavLink href={route('super-admin.orders')} active={route().current('super-admin.orders')}>
                    📦 Όλες οι Παραγγελίες
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.order-items') && (
                  <ResponsiveNavLink href={route('super-admin.order-items')} active={route().current('super-admin.order-items')}>
                    🛍️ Προϊόντα E-Shop
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.tenants') && (
                  <ResponsiveNavLink href={route('super-admin.tenants')} active={route().current('super-admin.tenants')}>
                    🏢 Όλοι οι Ενοικιαστές
                  </ResponsiveNavLink>
                )}
                {hasRoute('super-admin.users') && (
                  <ResponsiveNavLink href={route('super-admin.users')} active={route().current('super-admin.users')}>
                    👥 Όλοι οι Χρήστες
                  </ResponsiveNavLink>
                )}
              </>
            )}

          </div>

          <div className="border-t border-gray-200 pb-1 pt-4">
            <div className="px-4">
              <div className="text-base font-medium text-gray-800">{tenant?.business_name ?? tenant?.name ?? user?.name}</div>
              <div className="text-sm font-medium text-gray-500">{user?.email}</div>
            </div>

            <div className="mt-3 space-y-1">
              {hasRoute('profile.edit') && <ResponsiveNavLink href={route('profile.edit')}>Προφίλ</ResponsiveNavLink>}
              <button
                onClick={handleLogout}
                className="block w-full px-4 py-2 text-start text-base font-medium text-gray-500 transition duration-150 ease-in-out hover:bg-gray-50 hover:text-gray-700 focus:bg-gray-50 focus:text-gray-700 focus:outline-none"
              >
                Αποσύνδεση
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
