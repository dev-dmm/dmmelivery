import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminTenantDetails({ auth, tenant, stats }) {
    const getStatusBadge = (status) => {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'completed': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800',
            'shipped': 'bg-purple-100 text-purple-800',
            'delivered': 'bg-green-100 text-green-800'
        };
        
        return statusColors[status] || 'bg-gray-100 text-gray-800';
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Tenant Details - {tenant.name}
                    </h2>
                    <Link
                        href={route('super-admin.tenants')}
                        className="text-indigo-600 hover:text-indigo-500 text-sm font-medium"
                    >
                        ← Back to Tenants
                    </Link>
                </div>
            }
        >
            <Head title={`Tenant Details - ${tenant.name}`} />

            <div className="py-12">
                <div className="mx-auto">
                    
                    {/* Tenant Information */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900">Tenant Information</h3>
                        </div>
                        <div className="p-6">
                            <dl className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Company Name</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{tenant.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Subdomain</dt>
                                    <dd className="mt-1 text-sm text-gray-900">@{tenant.subdomain}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Contact Email</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{tenant.contact_email || 'Not provided'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Contact Phone</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{tenant.contact_phone || 'Not provided'}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Created</dt>
                                    <dd className="mt-1 text-sm text-gray-900">{new Date(tenant.created_at).toLocaleDateString()}</dd>
                                </div>
                                <div>
                                    <dt className="text-sm font-medium text-gray-500">Tenant ID</dt>
                                    <dd className="mt-1 text-sm text-gray-900 font-mono">{tenant.id}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    {/* Statistics */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total Orders</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.total_orders.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total Shipments</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.total_shipments.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Orders This Month</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.orders_this_month.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Pending Shipments</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.pending_shipments.toLocaleString()}</div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {/* Users */}
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h3 className="text-lg font-medium text-gray-900">Users ({tenant.users?.length || 0})</h3>
                            </div>
                            <div className="p-6">
                                {tenant.users && tenant.users.length > 0 ? (
                                    <div className="space-y-4">
                                        {tenant.users.map((user) => (
                                            <div key={user.id} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                                <div>
                                                    <div className="text-sm font-medium text-gray-900">{user.first_name} {user.last_name}</div>
                                                    <div className="text-sm text-gray-500">{user.email}</div>
                                                    <div className="text-xs text-gray-400">
                                                        Joined: {new Date(user.created_at).toLocaleDateString()}
                                                    </div>
                                                </div>
                                                <div>
                                                    {user.email_verified_at ? (
                                                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                            Verified
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Unverified
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-gray-500">No users found.</p>
                                )}
                            </div>
                        </div>

                        {/* Couriers */}
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="px-6 py-4 border-b border-gray-200">
                                <h3 className="text-lg font-medium text-gray-900">Couriers ({tenant.couriers?.length || 0})</h3>
                            </div>
                            <div className="p-6">
                                {tenant.couriers && tenant.couriers.length > 0 ? (
                                    <div className="space-y-4">
                                        {tenant.couriers.map((courier) => (
                                            <div key={courier.id} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                                <div>
                                                    <div className="text-sm font-medium text-gray-900">{courier.name}</div>
                                                    <div className="text-sm text-gray-500">Code: {courier.code}</div>
                                                </div>
                                                <div className="flex space-x-2">
                                                    {courier.is_default && (
                                                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                            Default
                                                        </span>
                                                    )}
                                                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                        courier.is_active 
                                                            ? 'bg-green-100 text-green-800' 
                                                            : 'bg-red-100 text-red-800'
                                                    }`}>
                                                        {courier.is_active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-gray-500">No couriers configured.</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Recent Orders */}
                    <div className="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-medium text-gray-900">Recent Orders (Last 20)</h3>
                                <Link
                                    href={route('super-admin.orders', { tenant: tenant.id })}
                                    className="text-indigo-600 hover:text-indigo-500 text-sm font-medium"
                                >
                                    View All Orders →
                                </Link>
                            </div>
                        </div>
                        <div className="p-6">
                            {tenant.orders && tenant.orders.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Order
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Customer
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Total
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Created
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {tenant.orders.map((order) => (
                                                <tr key={order.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            #{order.order_number}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            ID: {order.external_order_id}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {order.customer?.first_name} {order.customer?.last_name}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {order.customer?.email}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadge(order.status)}`}>
                                                            {order.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        €{parseFloat(order.total_amount).toFixed(2)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {new Date(order.created_at).toLocaleDateString()}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="text-gray-500">No orders found.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
