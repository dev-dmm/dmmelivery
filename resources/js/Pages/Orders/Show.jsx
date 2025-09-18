import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { route } from 'ziggy-js';

export default function OrdersShow({ order }) {
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

    const getPaymentStatusBadge = (status) => {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'paid': 'bg-green-100 text-green-800',
            'failed': 'bg-red-100 text-red-800',
            'refunded': 'bg-gray-100 text-gray-800'
        };
        
        return statusColors[status] || 'bg-gray-100 text-gray-800';
    };

    const getFulfillmentStatusBadge = (status) => {
        const statusColors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'allocated': 'bg-blue-100 text-blue-800',
            'picked': 'bg-purple-100 text-purple-800',
            'packed': 'bg-indigo-100 text-indigo-800',
            'shipped': 'bg-green-100 text-green-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800',
            'returned': 'bg-gray-100 text-gray-800'
        };
        
        return statusColors[status] || 'bg-gray-100 text-gray-800';
    };

    const formatCurrency = (amount, currency = 'EUR') => {
        return new Intl.NumberFormat('en-EU', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-EU', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        📦 Order #{order.order_number}
                    </h2>
                    <Link
                        href={route('orders.index')}
                        className="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium"
                    >
                        ← Back to Orders
                    </Link>
                </div>
            }
        >
            <Head title={`Order #${order.order_number}`} />

            <div className="py-12">
                <div className="mx-auto space-y-6">
                    
                    {/* Order Status & Payment Status */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Order Status</h3>
                                <div className="flex items-center space-x-3">
                                    <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getStatusBadge(order.status)}`}>
                                        {getStatusIcon(order.status)} {order.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                                <div className="mt-4 text-sm text-gray-600">
                                    <p><strong>Created:</strong> {formatDate(order.created_at)}</p>
                                    {order.shipped_at && (
                                        <p><strong>Shipped:</strong> {formatDate(order.shipped_at)}</p>
                                    )}
                                    {order.delivered_at && (
                                        <p><strong>Delivered:</strong> {formatDate(order.delivered_at)}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Payment Status</h3>
                                <div className="flex items-center space-x-3">
                                    <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getPaymentStatusBadge(order.payment_status)}`}>
                                        {order.payment_status?.toUpperCase() || 'UNKNOWN'}
                                    </span>
                                </div>
                                <div className="mt-4 text-sm text-gray-600">
                                    {order.payment_method && (
                                        <p><strong>Method:</strong> {order.payment_method}</p>
                                    )}
                                    {order.payment_reference && (
                                        <p><strong>Reference:</strong> {order.payment_reference}</p>
                                    )}
                                    {order.payment_date && (
                                        <p><strong>Date:</strong> {formatDate(order.payment_date)}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Customer Information */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Customer Information</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 className="text-sm font-medium text-gray-700 mb-2">Contact Details</h4>
                                    <div className="space-y-1 text-sm text-gray-600">
                                        <p><strong>Name:</strong> {order.customer_name}</p>
                                        <p><strong>Email:</strong> {order.customer_email}</p>
                                        {order.customer_phone && (
                                            <p><strong>Phone:</strong> {order.customer_phone}</p>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <h4 className="text-sm font-medium text-gray-700 mb-2">External Order ID</h4>
                                    <p className="text-sm text-gray-600">{order.external_order_id}</p>
                                    <h4 className="text-sm font-medium text-gray-700 mb-2">Internal Order ID</h4>
                                    <p className="text-sm text-gray-600">{order.id}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Shipping & Billing Addresses */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Shipping Address</h3>
                                <div className="text-sm text-gray-600 space-y-1">
                                    <p>{order.shipping_address}</p>
                                    <p>{order.shipping_city}, {order.shipping_postal_code}</p>
                                    <p>{order.shipping_country}</p>
                                    {order.shipping_notes && (
                                        <div className="mt-2 p-2 bg-yellow-50 rounded">
                                            <p className="text-xs text-yellow-800"><strong>Notes:</strong> {order.shipping_notes}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Billing Address</h3>
                                <div className="text-sm text-gray-600 space-y-1">
                                    {order.billing_address ? (
                                        <>
                                            <p>{order.billing_address}</p>
                                            <p>{order.billing_city}, {order.billing_postal_code}</p>
                                            <p>{order.billing_country}</p>
                                        </>
                                    ) : (
                                        <p className="text-gray-500 italic">Same as shipping address</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Order Items */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Order Items ({order.items?.length || 0})</h3>
                            {order.items && order.items.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Product
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    SKU
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Quantity
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Unit Price
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Total
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {order.items.map((item) => (
                                                <tr key={item.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {item.product_name}
                                                        </div>
                                                        {item.product_description && (
                                                            <div className="text-sm text-gray-500 mt-1">
                                                                {item.product_description}
                                                            </div>
                                                        )}
                                                        {item.product_brand && (
                                                            <div className="text-xs text-gray-400 mt-1">
                                                                Brand: {item.product_brand}
                                                            </div>
                                                        )}
                                                        {item.is_digital && (
                                                            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                                                                💾 Digital
                                                            </span>
                                                        )}
                                                        {item.is_fragile && (
                                                            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 mt-1 ml-1">
                                                                ⚠️ Fragile
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {item.product_sku || item.external_product_id || 'N/A'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {item.quantity}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {formatCurrency(item.final_unit_price, order.currency)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {formatCurrency(item.total_price, order.currency)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getFulfillmentStatusBadge(item.fulfillment_status)}`}>
                                                            {item.fulfillment_status?.replace('_', ' ').toUpperCase() || 'PENDING'}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-gray-500">
                                        <div className="text-4xl mb-4">📦</div>
                                        <h3 className="text-lg font-medium">No items found</h3>
                                        <p className="text-sm mt-2">This order has no items associated with it.</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Order Summary */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Order Summary</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Subtotal:</span>
                                        <span className="text-gray-900">{formatCurrency(order.subtotal, order.currency)}</span>
                                    </div>
                                    {order.tax_amount > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Tax:</span>
                                            <span className="text-gray-900">{formatCurrency(order.tax_amount, order.currency)}</span>
                                        </div>
                                    )}
                                    {order.shipping_cost > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Shipping:</span>
                                            <span className="text-gray-900">{formatCurrency(order.shipping_cost, order.currency)}</span>
                                        </div>
                                    )}
                                    {order.discount_amount > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Discount:</span>
                                            <span className="text-green-600">-{formatCurrency(order.discount_amount, order.currency)}</span>
                                        </div>
                                    )}
                                    <div className="border-t pt-2">
                                        <div className="flex justify-between text-lg font-semibold">
                                            <span className="text-gray-900">Total:</span>
                                            <span className="text-gray-900">{formatCurrency(order.total_amount, order.currency)}</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    {order.total_weight > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Total Weight:</span>
                                            <span className="text-gray-900">{order.total_weight} kg</span>
                                        </div>
                                    )}
                                    {order.requires_signature && (
                                        <div className="text-sm text-yellow-600">
                                            ⚠️ Signature Required
                                        </div>
                                    )}
                                    {order.fragile_items && (
                                        <div className="text-sm text-red-600">
                                            ⚠️ Contains Fragile Items
                                        </div>
                                    )}
                                    {order.special_instructions && (
                                        <div className="text-sm text-gray-600">
                                            <strong>Special Instructions:</strong> {order.special_instructions}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Shipments */}
                    {order.shipments && order.shipments.length > 0 && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Shipments ({order.shipments.length})</h3>
                                <div className="space-y-4">
                                    {order.shipments.map((shipment) => (
                                        <div key={shipment.id} className="border border-gray-200 rounded-lg p-4">
                                            <div className="flex items-center justify-between mb-2">
                                                <div className="flex items-center space-x-3">
                                                    <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadge(shipment.status)}`}>
                                                        {getStatusIcon(shipment.status)} {shipment.status?.toUpperCase()}
                                                    </span>
                                                    <span className="text-sm font-medium text-gray-900">
                                                        #{shipment.tracking_number}
                                                    </span>
                                                </div>
                                                {shipment.courier && (
                                                    <span className="text-sm text-gray-600">
                                                        via {shipment.courier.name}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-sm text-gray-600">
                                                <p><strong>Courier Tracking ID:</strong> {shipment.courier_tracking_id}</p>
                                                <p><strong>Weight:</strong> {shipment.weight} kg</p>
                                                <p><strong>Created:</strong> {formatDate(shipment.created_at)}</p>
                                                {shipment.estimated_delivery && (
                                                    <p><strong>Estimated Delivery:</strong> {formatDate(shipment.estimated_delivery)}</p>
                                                )}
                                            </div>
                                            
                                            {/* Shipment Status History */}
                                            {shipment.status_history && shipment.status_history.length > 0 && (
                                                <div className="mt-4">
                                                    <h4 className="text-sm font-medium text-gray-700 mb-2">Status History</h4>
                                                    <div className="space-y-2">
                                                        {shipment.status_history.map((history, index) => (
                                                            <div key={index} className="flex items-center space-x-3 text-sm">
                                                                <div className="w-2 h-2 bg-gray-400 rounded-full"></div>
                                                                <div className="flex-1">
                                                                    <span className="font-medium">{history.status}</span>
                                                                    {history.note && (
                                                                        <span className="text-gray-600 ml-2">- {history.note}</span>
                                                                    )}
                                                                </div>
                                                                <div className="text-gray-500">
                                                                    {formatDate(history.happened_at)}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Import Information */}
                    {order.import_log_id && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Import Information</h3>
                                <div className="text-sm text-gray-600 space-y-1">
                                    <p><strong>Source:</strong> {order.import_source}</p>
                                    <p><strong>Import Log ID:</strong> {order.import_log_id}</p>
                                    {order.import_notes && (
                                        <p><strong>Notes:</strong> {order.import_notes}</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
