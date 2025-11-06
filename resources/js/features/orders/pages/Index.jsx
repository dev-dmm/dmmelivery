import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { route } from 'ziggy-js';

export default function OrdersIndex({ orders, stats, statusOptions, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || '');
    const [perPage, setPerPage] = useState(filters.per_page || 15);

    const handleSearch = (e) => {
        e.preventDefault();
        
        const params = new URLSearchParams();
        if (searchTerm) params.append('search', searchTerm);
        if (selectedStatus) params.append('status', selectedStatus);
        if (perPage !== 15) params.append('per_page', perPage);

        router.get(route('orders.index'), Object.fromEntries(params));
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedStatus('');
        setPerPage(15);
        router.get(route('orders.index'));
    };

    const getStatusBadge = (status) => {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'ready_to_ship': 'bg-purple-100 text-purple-800',
            'shipped': 'bg-indigo-100 text-indigo-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800',
            'failed': 'bg-red-100 text-red-800',
            'returned': 'bg-gray-100 text-gray-800'
        };
        
        return statusColors[status] || 'bg-gray-100 text-gray-800';
    };

    const getStatusIcon = (status) => {
        const icons = {
            'pending': '⏳',
            'processing': '⚙️',
            'ready_to_ship': '📦',
            'shipped': '🚚',
            'delivered': '✅',
            'cancelled': '❌',
            'failed': '⚠️',
            'returned': '↩️'
        };
        return icons[status] || '📋';
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-lg lg:text-xl font-semibold leading-tight text-gray-800">
                    📦 Παραγγελίες
                </h2>
            }
        >
            <Head title="Παραγγελίες" />

            <div className="py-4 lg:py-12">
                <div className="mx-auto space-y-4 lg:space-y-6">
                    
                    {/* Stats Cards */}
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 lg:gap-6">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-3 lg:p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="w-6 h-6 lg:w-8 lg:h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span className="text-blue-600 font-bold text-xs lg:text-sm">📋</span>
                                        </div>
                                    </div>
                                    <div className="ml-3 lg:ml-5 w-0 flex-1 min-w-0">
                                        <dl>
                                            <dt className="text-xs lg:text-sm font-medium text-gray-500 truncate">Σύνολο Παραγγελιών</dt>
                                            <dd className="text-sm lg:text-lg font-medium text-gray-900">{stats.total}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-3 lg:p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="w-6 h-6 lg:w-8 lg:h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <span className="text-yellow-600 font-bold text-xs lg:text-sm">⏳</span>
                                        </div>
                                    </div>
                                    <div className="ml-3 lg:ml-5 w-0 flex-1 min-w-0">
                                        <dl>
                                            <dt className="text-xs lg:text-sm font-medium text-gray-500 truncate">Εκκρεμεί</dt>
                                            <dd className="text-sm lg:text-lg font-medium text-gray-900">{stats.pending}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-3 lg:p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="w-6 h-6 lg:w-8 lg:h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span className="text-blue-600 font-bold text-xs lg:text-sm">⚙️</span>
                                        </div>
                                    </div>
                                    <div className="ml-3 lg:ml-5 w-0 flex-1 min-w-0">
                                        <dl>
                                            <dt className="text-xs lg:text-sm font-medium text-gray-500 truncate">Επεξεργασία</dt>
                                            <dd className="text-sm lg:text-lg font-medium text-gray-900">{stats.processing}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-3 lg:p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="w-6 h-6 lg:w-8 lg:h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                            <span className="text-indigo-600 font-bold text-xs lg:text-sm">🚚</span>
                                        </div>
                                    </div>
                                    <div className="ml-3 lg:ml-5 w-0 flex-1 min-w-0">
                                        <dl>
                                            <dt className="text-xs lg:text-sm font-medium text-gray-500 truncate">Απεστάλη</dt>
                                            <dd className="text-sm lg:text-lg font-medium text-gray-900">{stats.shipped}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-3 lg:p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <div className="w-6 h-6 lg:w-8 lg:h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <span className="text-green-600 font-bold text-xs lg:text-sm">✅</span>
                                        </div>
                                    </div>
                                    <div className="ml-3 lg:ml-5 w-0 flex-1 min-w-0">
                                        <dl>
                                            <dt className="text-xs lg:text-sm font-medium text-gray-500 truncate">Ολοκληρώθηκε</dt>
                                            <dd className="text-sm lg:text-lg font-medium text-gray-900">{stats.completed}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-4 lg:p-6">
                            <form onSubmit={handleSearch} className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                                    <div>
                                        <label htmlFor="search" className="block text-xs lg:text-sm font-medium text-gray-700">Αναζήτηση</label>
                                        <input
                                            type="text"
                                            id="search"
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            placeholder="ID παραγγελίας, αριθμός παραγγελίας, όνομα πελάτη, email..."
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs lg:text-sm"
                                        />
                                    </div>

                                    <div>
                                        <label htmlFor="status" className="block text-xs lg:text-sm font-medium text-gray-700">Κατάσταση</label>
                                        <select
                                            id="status"
                                            value={selectedStatus}
                                            onChange={(e) => setSelectedStatus(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs lg:text-sm"
                                        >
                                            <option value="">Όλες οι Καταστάσεις</option>
                                            {Object.entries(statusOptions).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label htmlFor="per_page" className="block text-xs lg:text-sm font-medium text-gray-700">Ανά Σελίδα</label>
                                        <select
                                            id="per_page"
                                            value={perPage}
                                            onChange={(e) => setPerPage(e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs lg:text-sm"
                                        >
                                            <option value="15">15</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </div>

                                    <div className="flex flex-col sm:flex-row items-stretch sm:items-end space-y-2 sm:space-y-0 sm:space-x-2">
                                        <button
                                            type="submit"
                                            className="bg-indigo-600 hover:bg-indigo-700 text-white px-3 lg:px-4 py-2 rounded-md text-xs lg:text-sm font-medium"
                                        >
                                            Αναζήτηση
                                        </button>
                                        <button
                                            type="button"
                                            onClick={clearFilters}
                                            className="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 lg:px-4 py-2 rounded-md text-xs lg:text-sm font-medium"
                                        >
                                            Καθαρισμός
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {/* Orders Table */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Παραγγελία
                                        </th>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Πελάτης
                                        </th>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Κατάσταση
                                        </th>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Σύνολο
                                        </th>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Προϊόντα
                                        </th>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Δημιουργήθηκε
                                        </th>
                                        <th className="px-3 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ενέργειες
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {orders.data.length > 0 ? (
                                        orders.data.map((order) => (
                                            <tr key={order.id} className="hover:bg-gray-50">
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                                                    <div className="text-xs lg:text-sm font-medium text-gray-900">
                                                        #{order.order_number}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        Εξωτερικό: {order.external_order_id}
                                                    </div>
                                                    <div className="text-xs text-gray-400 font-mono">
                                                        Εσωτερικό: {order.id}
                                                    </div>
                                                </td>
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                                                    <div className="text-xs lg:text-sm font-medium text-gray-900 truncate">
                                                        {order.customer_name}
                                                    </div>
                                                    <div className="text-xs text-gray-500 truncate">
                                                        {order.customer_email}
                                                    </div>
                                                </td>
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                                                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadge(order.status)}`}>
                                                        {getStatusIcon(order.status)} {statusOptions[order.status] || order.status}
                                                    </span>
                                                </td>
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap text-xs lg:text-sm text-gray-900">
                                                    €{parseFloat(order.total_amount).toFixed(2)}
                                                </td>
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap text-xs lg:text-sm text-gray-500">
                                                    {order.items?.length || 0} προϊόντα
                                                </td>
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap text-xs lg:text-sm text-gray-500">
                                                    {new Date(order.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-3 lg:px-6 py-3 lg:py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                                                    <Link
                                                        href={route('orders.show', order.id)}
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        Προβολή
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="7" className="px-3 lg:px-6 py-8 lg:py-12 text-center">
                                                <div className="text-gray-500">
                                                    <div className="text-3xl lg:text-4xl mb-4">📦</div>
                                                    <h3 className="text-base lg:text-lg font-medium">Δεν βρέθηκαν παραγγελίες</h3>
                                                    <p className="text-xs lg:text-sm mt-2">
                                                        {filters.search || filters.status 
                                                            ? 'Προσπαθήστε να προσαρμόσετε τα φίλτρα ή τους όρους αναζήτησης.'
                                                            : 'Οι παραγγελίες θα εμφανιστούν εδώ όταν δημιουργηθούν ή εισαχθούν.'
                                                        }
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {orders.data.length > 0 && (
                            <div className="bg-white px-3 lg:px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                <div className="flex-1 flex justify-between sm:hidden">
                                    {orders.prev_page_url && (
                                        <Link
                                            href={orders.prev_page_url}
                                            className="relative inline-flex items-center px-3 lg:px-4 py-2 border border-gray-300 text-xs lg:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                        >
                                            Προηγούμενη
                                        </Link>
                                    )}
                                    {orders.next_page_url && (
                                        <Link
                                            href={orders.next_page_url}
                                            className="ml-3 relative inline-flex items-center px-3 lg:px-4 py-2 border border-gray-300 text-xs lg:text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                        >
                                            Επόμενη
                                        </Link>
                                    )}
                                </div>
                                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p className="text-xs lg:text-sm text-gray-700">
                                            Εμφάνιση <span className="font-medium">{orders.from}</span> έως{' '}
                                            <span className="font-medium">{orders.to}</span> από{' '}
                                            <span className="font-medium">{orders.total}</span> αποτελέσματα
                                        </p>
                                    </div>
                                    <div>
                                        <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                            {orders.links.map((link, index) => (
                                                <Link
                                                    key={index}
                                                    href={link.url || '#'}
                                                    className={`relative inline-flex items-center px-2 lg:px-4 py-2 border text-xs lg:text-sm font-medium ${
                                                        link.active
                                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                                            : link.url
                                                            ? 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                            : 'bg-white border-gray-300 text-gray-300 cursor-not-allowed'
                                                    } ${index === 0 ? 'rounded-l-md' : ''} ${
                                                        index === orders.links.length - 1 ? 'rounded-r-md' : ''
                                                    }`}
                                                    preserveState
                                                    preserveScroll
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
