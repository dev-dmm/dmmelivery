import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SuperAdminOrders({ auth, orders, tenants, orderStatuses, filters, stats }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedTenant, setSelectedTenant] = useState(filters.tenant || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [perPage, setPerPage] = useState(filters.per_page || 25);

    const handleSearch = (e) => {
        e.preventDefault();
        
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (selectedTenant) params.append('tenant', selectedTenant);
        if (selectedStatus) params.append('status', selectedStatus);
        if (perPage !== 25) params.append('per_page', perPage);

        router.get(route('super-admin.orders'), Object.fromEntries(params));
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedTenant('');
        setSelectedStatus('');
        setPerPage(25);
        router.get(route('super-admin.orders'));
    };

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
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Super Admin - All Orders</h2>}
        >
            <Head title="Super Admin - Orders" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total Orders</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.total_orders.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Total Tenants</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.total_tenants.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Orders Today</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.orders_today.toLocaleString()}</div>
                        </div>
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-sm font-medium text-gray-500">Active Tenants</div>
                            <div className="mt-2 text-3xl font-bold text-gray-900">{stats.active_tenants.toLocaleString()}</div>
                        </div>
                    </div>

                    {/* Search and Filters */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="p-6">
                            <form onSubmit={handleSearch} className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label htmlFor="search" className="block text-sm font-medium text-gray-700">
                                            Search Orders
                                        </label>
                                        <input
                                            type="text"
                                            id="search"
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            placeholder="Order ID, customer name, email..."
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                    </div>
                                    
                                    <div>
                                        <label htmlFor="tenant" className="block text-sm font-medium text-gray-700">
                                            Tenant
                                        </label>
                                        <select
                                            id="tenant"
                                            value={selectedTenant}
                                            onChange={(e) => setSelectedTenant(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">All Tenants</option>
                                            {tenants.map((tenant) => (
                                                <option key={tenant.id} value={tenant.id}>
                                                    {tenant.name} ({tenant.subdomain})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label htmlFor="status" className="block text-sm font-medium text-gray-700">
                                            Status
                                        </label>
                                        <select
                                            id="status"
                                            value={selectedStatus}
                                            onChange={(e) => setSelectedStatus(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">All Statuses</option>
                                            {orderStatuses.map((status) => (
                                                <option key={status} value={status}>
                                                    {status.charAt(0).toUpperCase() + status.slice(1)}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label htmlFor="per_page" className="block text-sm font-medium text-gray-700">
                                            Per Page
                                        </label>
                                        <select
                                            id="per_page"
                                            value={perPage}
                                            onChange={(e) => setPerPage(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="10">10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div className="flex space-x-3">
                                    <button
                                        type="submit"
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded"
                                    >
                                        Search
                                    </button>
                                    <button
                                        type="button"
                                        onClick={clearFilters}
                                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
                                    >
                                        Clear Filters
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Orders Table */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Order
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tenant/Company
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
                                                Shipment
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Created
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {orders.data.map((order) => (
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
                                                        {order.tenant?.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        @{order.tenant?.subdomain}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {order.customer?.name}
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
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {order.shipment ? (
                                                        <div className="text-sm">
                                                            <div className="mb-1">
                                                                <span className="font-medium">{order.shipment.tracking_number}</span>
                                                                <span className={`ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadge(order.shipment.status)}`}>
                                                                    {order.shipment.status}
                                                                </span>
                                                                {order.shipment.courier && (
                                                                    <div className="text-xs text-gray-500">
                                                                        {order.shipment.courier.name} ({order.shipment.courier.code})
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-gray-500">No shipment</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(order.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {orders.links && (
                                <div className="mt-6 flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Showing {orders.from} to {orders.to} of {orders.total} results
                                    </div>
                                    <div className="flex space-x-1">
                                            {orders.links.map((link, index) => {
                                                if (!link.url) {
                                                    return (
                                                        <span
                                                            key={index}
                                                            className="px-3 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-400 cursor-not-allowed"
                                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                                        />
                                                    );
                                                }
                                                return (
                                                    <Link
                                                        key={index}
                                                        href={link.url}
                                                        className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                            link.active
                                                                ? 'bg-indigo-600 text-white'
                                                                : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                                        }`}
                                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                                    />
                                                );
                                            })
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
