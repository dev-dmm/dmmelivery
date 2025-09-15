import { useEffect, useMemo } from 'react';
import { Inertia } from '@inertiajs/inertia';
import { usePage, Link, Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminDashboard({ auth }) {
  const { stats, recentOrders, topTenants } = usePage().props;

  // Trigger partial reload only if a lazy prop is missing
  useEffect(() => {
    const missing = [];
    if (recentOrders === undefined) missing.push('recentOrders');
    if (topTenants   === undefined) missing.push('topTenants');
    if (missing.length) Inertia.reload({ only: missing });
  }, [recentOrders, topTenants]);

  // Safe helpers
  const num = (v) => (typeof v === 'number' ? v : Number(v) || 0);
  const fmt = (v) => num(v).toLocaleString();
  const orders = useMemo(() => Array.isArray(recentOrders) ? recentOrders : [], [recentOrders]);
  const tenants = useMemo(() => Array.isArray(topTenants) ? topTenants : [], [topTenants]);

  const LoadingCard = () => (
    <div className="animate-pulse p-4 border border-gray-200 rounded-lg">
      <div className="h-4 bg-gray-200 rounded w-1/3 mb-2" />
      <div className="h-3 bg-gray-100 rounded w-1/2" />
    </div>
  );

  const getStatusBadge = (status) => ({
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-green-100 text-green-800',
  }[status] || 'bg-gray-100 text-gray-800');

  return (
    <AuthenticatedLayout user={auth?.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin Dashboard</h2>}
    >
      <Head title="Super Admin Dashboard" />

      <div className="py-12">
        <div className="mx-auto">

          {/* Stats (eager, always present) */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {/* Total Orders */}
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Total Orders</div>
              <div className="mt-1 text-2xl font-bold text-gray-900">{fmt(stats?.total_orders)}</div>
            </div>
            {/* Total Tenants */}
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Total Tenants</div>
              <div className="mt-1 text-2xl font-bold text-gray-900">{fmt(stats?.total_tenants)}</div>
            </div>
            {/* Total Shipments */}
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Total Shipments</div>
              <div className="mt-1 text-2xl font-bold text-gray-900">{fmt(stats?.total_shipments)}</div>
            </div>
            {/* Active Tenants */}
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Active Tenants</div>
              <div className="mt-1 text-2xl font-bold text-gray-900">{fmt(stats?.active_tenants)}</div>
            </div>
          </div>

          {/* Time-based */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Orders Today</div>
              <div className="mt-2 text-3xl font-bold">{fmt(stats?.orders_today)}</div>
            </div>
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Orders This Week</div>
              <div className="mt-2 text-3xl font-bold">{fmt(stats?.orders_this_week)}</div>
            </div>
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <div className="text-sm text-gray-500">Orders This Month</div>
              <div className="mt-2 text-3xl font-bold">{fmt(stats?.orders_this_month)}</div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Recent Orders */}
            <div className="bg-white shadow-sm sm:rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 className="text-lg font-medium">Recent Orders</h3>
                <Link href={route('super-admin.orders')} className="text-indigo-600 text-sm">View All →</Link>
              </div>
              <div className="p-6 space-y-4">
                {recentOrders === undefined ? (
                  <>
                    <LoadingCard /><LoadingCard /><LoadingCard />
                  </>
                ) : orders.length ? (
                  orders.map((order) => {
                    const shipments = Array.isArray(order?.shipments) ? order.shipments : [];
                    const total = Number(order?.total_amount ?? 0);
                    return (
                      <div key={order?.id} className="p-4 border border-gray-200 rounded-lg">
                        <div className="flex items-center justify-between">
                          <div>
                            <div className="text-sm font-medium">Order #{order?.order_number ?? '—'}</div>
                            <div className="text-sm text-gray-500">
                              {(order?.tenant?.business_name ?? order?.tenant?.name ?? '—')} (@{order?.tenant?.subdomain ?? '—'})
                            </div>
                            <div className="text-sm text-gray-500">
                              {(order?.customer?.first_name ?? '')} {(order?.customer?.last_name ?? '')}
                            </div>
                          </div>
                          <div className="text-right">
                            <div className="text-sm font-medium">€{total.toFixed(2)}</div>
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadge(order?.status)}`}>
                              {order?.status ?? 'unknown'}
                            </span>
                          </div>
                        </div>
                        {shipments.length > 0 && (
                          <div className="mt-2 text-xs text-gray-500">
                            Shipments: {shipments.map(s => s?.tracking_number).filter(Boolean).join(', ')}
                          </div>
                        )}
                      </div>
                    );
                  })
                ) : (
                  <p className="text-gray-500 text-center py-8">No recent orders</p>
                )}
              </div>
            </div>

            {/* Top Tenants */}
            <div className="bg-white shadow-sm sm:rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 className="text-lg font-medium">Top Tenants (30 days)</h3>
                <Link href={route('super-admin.tenants')} className="text-indigo-600 text-sm">View All →</Link>
              </div>
              <div className="p-6 space-y-4">
                {topTenants === undefined ? (
                  <>
                    <LoadingCard /><LoadingCard /><LoadingCard />
                  </>
                ) : tenants.length ? (
                  tenants.map((t, i) => (
                    <div key={t?.id ?? i} className="p-4 border border-gray-200 rounded-lg flex items-center justify-between">
                      <div className="flex items-center gap-4">
                        <div className="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                          <span className="text-sm font-medium text-indigo-800">#{i + 1}</span>
                        </div>
                        <div>
                          <div className="text-sm font-medium">{t?.name ?? '—'}</div>
                          <div className="text-sm text-gray-500">@{t?.subdomain ?? '—'}</div>
                        </div>
                      </div>
                      <div className="text-right text-sm">
                        {num(t?.orders_count)} orders
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
      </div>
    </AuthenticatedLayout>
  );
}
