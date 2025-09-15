import React, { useEffect, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Inertia } from '@inertiajs/inertia';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminDashboard({ auth, stats, recentOrders, topTenants }) {
  // ---- Lazy props: trigger fetch once if missing ----
  useEffect(() => {
    const missing = [];
    if (typeof recentOrders === 'undefined') missing.push('recentOrders');
    if (typeof topTenants === 'undefined') missing.push('topTenants');
    if (missing.length) Inertia.reload({ only: missing });
  }, [recentOrders, topTenants]);

  // ---- Safe defaults / helpers ----
  const s = stats ?? {};
  const num = (v) => (typeof v === 'number' ? v : Number(v) || 0);
  const fmt = (v) => num(v).toLocaleString();

  const isLoadingRecent = typeof recentOrders === 'undefined';
  const isLoadingTop = typeof topTenants === 'undefined';

  const safeRecentOrders = useMemo(
    () => (Array.isArray(recentOrders) ? recentOrders : []),
    [recentOrders]
  );
  const safeTopTenants = useMemo(
    () => (Array.isArray(topTenants) ? topTenants : []),
    [topTenants]
  );

  const hasRoute = typeof route === 'function';
  const r = (name, ...args) => (hasRoute ? route(name, ...args) : '#');

  const getStatusBadge = (status) => {
    const statusColors = {
      pending: 'bg-yellow-100 text-yellow-800',
      processing: 'bg-blue-100 text-blue-800',
      completed: 'bg-green-100 text-green-800',
      cancelled: 'bg-red-100 text-red-800',
      shipped: 'bg-purple-100 text-purple-800',
      delivered: 'bg-green-100 text-green-800',
    };
    return statusColors[status ?? ''] || 'bg-gray-100 text-gray-800';
  };

  const LoadingCard = () => (
    <div className="animate-pulse p-4 border border-gray-200 rounded-lg">
      <div className="h-4 bg-gray-200 rounded w-1/3 mb-2" />
      <div className="h-3 bg-gray-100 rounded w-1/2" />
    </div>
  );

  return (
    <AuthenticatedLayout
      user={auth?.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin Dashboard</h2>}
    >
      <Head title="Super Admin Dashboard" />

      <div className="py-12">
        <div className="mx-auto">

          {/* Statistics Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                  </div>
                </div>
                <div className="ml-4">
                  <div className="text-sm font-medium text-gray-500">Total Orders</div>
                  <div className="text-2xl font-bold text-gray-900">{fmt(s.total_orders)}</div>
                </div>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                  </div>
                </div>
                <div className="ml-4">
                  <div className="text-sm font-medium text-gray-500">Total Tenants</div>
                  <div className="text-2xl font-bold text-gray-900">{fmt(s.total_tenants)}</div>
                </div>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                  </div>
                </div>
                <div className="ml-4">
                  <div className="text-sm font-medium text-gray-500">Total Shipments</div>
                  <div className="text-2xl font-bold text-gray-900">{fmt(s.total_shipments)}</div>
                </div>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="flex items-center">
                <div className="flex-shrink-0">
                  <div className="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                    <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                  </div>
                </div>
                <div className="ml-4">
                  <div className="text-sm font-medium text-gray-500">Active Tenants</div>
                  <div className="text-2xl font-bold text-gray-900">{fmt(s.active_tenants)}</div>
                </div>
              </div>
            </div>
          </div>

          {/* Time-based Statistics */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="text-sm font-medium text-gray-500">Orders Today</div>
              <div className="mt-2 text-3xl font-bold text-gray-900">{fmt(s.orders_today)}</div>
            </div>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="text-sm font-medium text-gray-500">Orders This Week</div>
              <div className="mt-2 text-3xl font-bold text-gray-900">{fmt(s.orders_this_week)}</div>
            </div>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
              <div className="text-sm font-medium text-gray-500">Orders This Month</div>
              <div className="mt-2 text-3xl font-bold text-gray-900">{fmt(s.orders_this_month)}</div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Recent Orders */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-medium text-gray-900">Recent Orders</h3>
                  <Link href={r('super-admin.orders')} className="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                    View All →
                  </Link>
                </div>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  {isLoadingRecent ? (
                    <>
                      <LoadingCard /><LoadingCard /><LoadingCard />
                    </>
                  ) : safeRecentOrders.length ? (
                    safeRecentOrders.map((order, index) => {
                      const shipments = Array.isArray(order?.shipments) ? order.shipments : [];
                      const total = Number(order?.total_amount ?? 0);
                      return (
                        <div key={order?.id ?? `order-${index}`} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                          <div className="flex-1">
                            <div className="flex items-center justify-between">
                              <div>
                                <div className="text-sm font-medium text-gray-900">
                                  Order #{order?.order_number ?? '—'}
                                </div>
                                <div className="text-sm text-gray-500">
                                  {(order?.tenant?.business_name ?? order?.tenant?.name ?? '—')} @{order?.tenant?.subdomain ?? '—'}
                                </div>
                                <div className="text-sm text-gray-500">
                                  {(order?.customer?.first_name ?? '')} {(order?.customer?.last_name ?? '')}
                                </div>
                              </div>
                              <div className="text-right">
                                <div className="text-sm font-medium text-gray-900">
                                  €{total.toFixed(2)}
                                </div>
                                <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadge(order?.status)}`}>
                                  {order?.status ?? 'unknown'}
                                </span>
                              </div>
                            </div>

                            {shipments.length > 0 && (
                              <div className="mt-2 text-xs text-gray-500">
                                Shipments: {shipments.map((s) => s?.tracking_number).filter(Boolean).join(', ')}
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })
                  ) : (
                    <p className="text-gray-500 text-center py-8">No recent orders</p>
                  )}
                </div>
              </div>
            </div>

            {/* Top Tenants */}
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-medium text-gray-900">Top Tenants (30 days)</h3>
                  <Link href={r('super-admin.tenants')} className="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                    View All →
                  </Link>
                </div>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  {isLoadingTop ? (
                    <>
                      <LoadingCard /><LoadingCard /><LoadingCard />
                    </>
                  ) : safeTopTenants.length ? (
                    safeTopTenants.map((tenant, index) => (
                      <div key={tenant?.id ?? `${index}-tenant`} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div className="flex items-center">
                          <div className="flex-shrink-0 w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                            <span className="text-sm font-medium text-indigo-800">#{index + 1}</span>
                          </div>
                          <div className="ml-4">
                            <div className="text-sm font-medium text-gray-900">
                              {tenant?.name ?? '—'}
                            </div>
                            <div className="text-sm text-gray-500">
                              @{tenant?.subdomain ?? '—'}
                            </div>
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="text-sm font-medium text-gray-900">
                            {num(tenant?.orders_count)} orders
                          </div>
                          <Link href={r('super-admin.tenants.show', tenant?.id)} className="text-indigo-600 hover:text-indigo-500 text-xs">
                            View Details →
                          </Link>
                        </div>
                      </div>
                    ))
                  ) : (
                    <p className="text-gray-500 text-center py-8">No tenants data</p>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Quick Actions</h3>
            </div>
            <div className="p-6">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Link href={r('super-admin.orders')} className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                  <div className="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg className="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                  </div>
                  <div className="ml-4">
                    <div className="text-sm font-medium text-gray-900">View All Orders</div>
                    <div className="text-sm text-gray-500">Browse and search all orders</div>
                  </div>
                </Link>

                <Link href={r('super-admin.tenants')} className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                  <div className="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg className="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                  </div>
                  <div className="ml-4">
                    <div className="text-sm font-medium text-gray-900">Manage Tenants</div>
                    <div className="text-sm text-gray-500">View tenant details and stats</div>
                  </div>
                </Link>

                <div className="flex items-center p-4 border border-gray-200 rounded-lg bg-gray-50">
                  <div className="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg className="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                  </div>
                  <div className="ml-4">
                    <div className="text-sm font-medium text-gray-900">Analytics</div>
                    <div className="text-sm text-gray-500">Coming soon...</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </AuthenticatedLayout>
  );
}
