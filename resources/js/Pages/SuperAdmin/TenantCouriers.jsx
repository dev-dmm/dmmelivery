import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function TenantCouriers({ tenant, couriers, filters }) {
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [editingCourier, setEditingCourier] = useState(null);
    const [showEditDialog, setShowEditDialog] = useState(false);

    const { data: createData, setData: setCreateData, post: createCourier, processing: creating, errors: createErrors, reset: resetCreate } = useForm({
        name: '',
        code: '',
        api_endpoint: '',
        api_key: '',
        is_active: true,
        is_default: false,
        tracking_url_template: '',
    });

    const { data: editData, setData: setEditData, put: updateCourier, processing: updating, errors: editErrors, reset: resetEdit } = useForm({
        name: '',
        code: '',
        api_endpoint: '',
        api_key: '',
        is_active: true,
        is_default: false,
        tracking_url_template: '',
    });

    const { post: createACS, processing: creatingACS } = useForm();

    const handleCreateCourier = (e) => {
        e.preventDefault();
        createCourier(route('super-admin.tenants.couriers.create', tenant.id), {
            onSuccess: () => {
                setShowCreateDialog(false);
                resetCreate();
            }
        });
    };

    const handleUpdateCourier = (e) => {
        e.preventDefault();
        updateCourier(route('super-admin.tenants.couriers.update', [tenant.id, editingCourier.id]), {
            onSuccess: () => {
                setShowEditDialog(false);
                setEditingCourier(null);
                resetEdit();
            }
        });
    };

    const handleCreateACS = () => {
        createACS(route('super-admin.tenants.couriers.create-acs', tenant.id));
    };

    const handleEdit = (courier) => {
        setEditingCourier(courier);
        setEditData({
            name: courier.name,
            code: courier.code,
            api_endpoint: courier.api_endpoint || '',
            api_key: courier.api_key || '',
            is_active: courier.is_active,
            is_default: courier.is_default,
            tracking_url_template: courier.tracking_url_template || '',
        });
        setShowEditDialog(true);
    };

    const handleDelete = (courier) => {
        if (confirm('Are you sure you want to delete this courier?')) {
            router.delete(route('super-admin.tenants.couriers.delete', [tenant.id, courier.id]));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Couriers - ${tenant.name}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {/* Header */}
                            <div className="flex items-center justify-between mb-6">
                                <div className="flex items-center space-x-4">
                                    <Link
                                        href={route('super-admin.tenants.show', tenant.id)}
                                        className="text-gray-500 hover:text-gray-700"
                                    >
                                        ← Back
                                    </Link>
                                    <div>
                                        <h1 className="text-2xl font-bold text-gray-900">
                                            Couriers for {tenant.name}
                                        </h1>
                                        <p className="text-gray-600">
                                            Manage courier services for this tenant
                                        </p>
                                    </div>
                                </div>
                                
                                <div className="flex space-x-2">
                                    <button
                                        onClick={handleCreateACS}
                                        disabled={creatingACS}
                                        className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
                                    >
                                        {creatingACS ? 'Creating...' : 'Add ACS Courier'}
                                    </button>
                                    
                                    <button
                                        onClick={() => setShowCreateDialog(true)}
                                        className="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                                    >
                                        + Add Courier
                                    </button>
                                </div>
                            </div>

                            {/* Couriers List */}
                            <div className="space-y-4">
                                {couriers.data.length === 0 ? (
                                    <div className="text-center py-12 bg-gray-50 rounded-lg">
                                        <div className="text-gray-400 text-6xl mb-4">📦</div>
                                        <h3 className="text-lg font-medium text-gray-900 mb-2">No couriers found</h3>
                                        <p className="text-gray-600 mb-4">
                                            This tenant doesn't have any couriers configured yet.
                                        </p>
                                        <button 
                                            onClick={() => setShowCreateDialog(true)}
                                            className="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                                        >
                                            + Add First Courier
                                        </button>
                                    </div>
                                ) : (
                                    couriers.data.map((courier) => (
                                        <div key={courier.id} className="border rounded-lg p-4 bg-white shadow-sm">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h3 className="text-lg font-medium text-gray-900 flex items-center space-x-2">
                                                        <span>{courier.name}</span>
                                                        {courier.is_default && (
                                                            <span className="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Default</span>
                                                        )}
                                                        {!courier.is_active && (
                                                            <span className="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">Inactive</span>
                                                        )}
                                                    </h3>
                                                    <p className="text-gray-600">Code: {courier.code}</p>
                                                </div>
                                                <div className="flex space-x-2">
                                                    <button
                                                        onClick={() => handleEdit(courier)}
                                                        className="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(courier)}
                                                        className="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                            <div className="mt-3 grid grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <strong>API Endpoint:</strong>
                                                    <p className="text-gray-600">{courier.api_endpoint || 'Not configured'}</p>
                                                </div>
                                                <div>
                                                    <strong>Tracking URL:</strong>
                                                    <p className="text-gray-600">{courier.tracking_url_template || 'Not configured'}</p>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            {/* Pagination */}
                            {couriers.links && (
                                <div className="mt-6 flex justify-center">
                                    <nav className="flex space-x-1">
                                        {couriers.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`px-3 py-2 text-sm rounded-md ${
                                                    link.active
                                                        ? 'bg-blue-500 text-white'
                                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                                } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </nav>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Create Dialog */}
            {showCreateDialog && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 className="text-lg font-medium mb-4">Create New Courier</h2>
                        <form onSubmit={handleCreateCourier}>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Name</label>
                                    <input
                                        type="text"
                                        value={createData.name}
                                        onChange={(e) => setCreateData('name', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        placeholder="e.g., DHL, FedEx"
                                        required
                                    />
                                    {createErrors.name && (
                                        <p className="text-red-500 text-sm mt-1">{createErrors.name}</p>
                                    )}
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Code</label>
                                    <input
                                        type="text"
                                        value={createData.code}
                                        onChange={(e) => setCreateData('code', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        placeholder="e.g., dhl, fedex"
                                        required
                                    />
                                    {createErrors.code && (
                                        <p className="text-red-500 text-sm mt-1">{createErrors.code}</p>
                                    )}
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">API Endpoint</label>
                                    <input
                                        type="url"
                                        value={createData.api_endpoint}
                                        onChange={(e) => setCreateData('api_endpoint', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        placeholder="https://api.example.com"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">API Key</label>
                                    <input
                                        type="password"
                                        value={createData.api_key}
                                        onChange={(e) => setCreateData('api_key', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        placeholder="Enter API key"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Tracking URL Template</label>
                                    <input
                                        type="text"
                                        value={createData.tracking_url_template}
                                        onChange={(e) => setCreateData('tracking_url_template', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        placeholder="https://track.example.com/{tracking_number}"
                                    />
                                </div>
                                
                                <div className="flex items-center space-x-4">
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={createData.is_active}
                                            onChange={(e) => setCreateData('is_active', e.target.checked)}
                                            className="mr-2"
                                        />
                                        Active
                                    </label>
                                    
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={createData.is_default}
                                            onChange={(e) => setCreateData('is_default', e.target.checked)}
                                            className="mr-2"
                                        />
                                        Default
                                    </label>
                                </div>
                            </div>
                            
                            <div className="flex justify-end space-x-2 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateDialog(false)}
                                    className="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={creating}
                                    className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
                                >
                                    {creating ? 'Creating...' : 'Create Courier'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Edit Dialog */}
            {showEditDialog && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 className="text-lg font-medium mb-4">Edit Courier</h2>
                        <form onSubmit={handleUpdateCourier}>
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Name</label>
                                    <input
                                        type="text"
                                        value={editData.name}
                                        onChange={(e) => setEditData('name', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        required
                                    />
                                    {editErrors.name && (
                                        <p className="text-red-500 text-sm mt-1">{editErrors.name}</p>
                                    )}
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Code</label>
                                    <input
                                        type="text"
                                        value={editData.code}
                                        onChange={(e) => setEditData('code', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                        required
                                    />
                                    {editErrors.code && (
                                        <p className="text-red-500 text-sm mt-1">{editErrors.code}</p>
                                    )}
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">API Endpoint</label>
                                    <input
                                        type="url"
                                        value={editData.api_endpoint}
                                        onChange={(e) => setEditData('api_endpoint', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">API Key</label>
                                    <input
                                        type="password"
                                        value={editData.api_key}
                                        onChange={(e) => setEditData('api_key', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Tracking URL Template</label>
                                    <input
                                        type="text"
                                        value={editData.tracking_url_template}
                                        onChange={(e) => setEditData('tracking_url_template', e.target.value)}
                                        className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
                                    />
                                </div>
                                
                                <div className="flex items-center space-x-4">
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={editData.is_active}
                                            onChange={(e) => setEditData('is_active', e.target.checked)}
                                            className="mr-2"
                                        />
                                        Active
                                    </label>
                                    
                                    <label className="flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={editData.is_default}
                                            onChange={(e) => setEditData('is_default', e.target.checked)}
                                            className="mr-2"
                                        />
                                        Default
                                    </label>
                                </div>
                            </div>
                            
                            <div className="flex justify-end space-x-2 mt-6">
                                <button
                                    type="button"
                                    onClick={() => setShowEditDialog(false)}
                                    className="px-4 py-2 text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={updating}
                                    className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50"
                                >
                                    {updating ? 'Updating...' : 'Update Courier'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}