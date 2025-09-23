import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/Components/ui/dialog';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Plus, Edit, Trash2, Package, ArrowLeft } from 'lucide-react';

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
                                        <ArrowLeft className="h-5 w-5" />
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
                                    <Button
                                        onClick={handleCreateACS}
                                        disabled={creatingACS}
                                        variant="outline"
                                    >
                                        <Package className="h-4 w-4 mr-2" />
                                        Add ACS Courier
                                    </Button>
                                    
                                    <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                                        <DialogTrigger asChild>
                                            <Button>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Add Courier
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>Create New Courier</DialogTitle>
                                                <DialogDescription>
                                                    Add a new courier service for this tenant.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <form onSubmit={handleCreateCourier}>
                                                <div className="space-y-4">
                                                    <div>
                                                        <Label htmlFor="name">Name</Label>
                                                        <Input
                                                            id="name"
                                                            value={createData.name}
                                                            onChange={(e) => setCreateData('name', e.target.value)}
                                                            placeholder="e.g., DHL, FedEx"
                                                            required
                                                        />
                                                        {createErrors.name && (
                                                            <p className="text-red-500 text-sm mt-1">{createErrors.name}</p>
                                                        )}
                                                    </div>
                                                    
                                                    <div>
                                                        <Label htmlFor="code">Code</Label>
                                                        <Input
                                                            id="code"
                                                            value={createData.code}
                                                            onChange={(e) => setCreateData('code', e.target.value)}
                                                            placeholder="e.g., dhl, fedex"
                                                            required
                                                        />
                                                        {createErrors.code && (
                                                            <p className="text-red-500 text-sm mt-1">{createErrors.code}</p>
                                                        )}
                                                    </div>
                                                    
                                                    <div>
                                                        <Label htmlFor="api_endpoint">API Endpoint</Label>
                                                        <Input
                                                            id="api_endpoint"
                                                            type="url"
                                                            value={createData.api_endpoint}
                                                            onChange={(e) => setCreateData('api_endpoint', e.target.value)}
                                                            placeholder="https://api.example.com"
                                                        />
                                                        {createErrors.api_endpoint && (
                                                            <p className="text-red-500 text-sm mt-1">{createErrors.api_endpoint}</p>
                                                        )}
                                                    </div>
                                                    
                                                    <div>
                                                        <Label htmlFor="api_key">API Key</Label>
                                                        <Input
                                                            id="api_key"
                                                            type="password"
                                                            value={createData.api_key}
                                                            onChange={(e) => setCreateData('api_key', e.target.value)}
                                                            placeholder="Enter API key"
                                                        />
                                                        {createErrors.api_key && (
                                                            <p className="text-red-500 text-sm mt-1">{createErrors.api_key}</p>
                                                        )}
                                                    </div>
                                                    
                                                    <div>
                                                        <Label htmlFor="tracking_url_template">Tracking URL Template</Label>
                                                        <Input
                                                            id="tracking_url_template"
                                                            value={createData.tracking_url_template}
                                                            onChange={(e) => setCreateData('tracking_url_template', e.target.value)}
                                                            placeholder="https://track.example.com/{tracking_number}"
                                                        />
                                                        {createErrors.tracking_url_template && (
                                                            <p className="text-red-500 text-sm mt-1">{createErrors.tracking_url_template}</p>
                                                        )}
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
                                                
                                                <DialogFooter>
                                                    <Button type="button" variant="outline" onClick={() => setShowCreateDialog(false)}>
                                                        Cancel
                                                    </Button>
                                                    <Button type="submit" disabled={creating}>
                                                        {creating ? 'Creating...' : 'Create Courier'}
                                                    </Button>
                                                </DialogFooter>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </div>

                            {/* Couriers List */}
                            <div className="grid gap-4">
                                {couriers.data.length === 0 ? (
                                    <Card>
                                        <CardContent className="p-6 text-center">
                                            <Package className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <h3 className="text-lg font-medium text-gray-900 mb-2">No couriers found</h3>
                                            <p className="text-gray-600 mb-4">
                                                This tenant doesn't have any couriers configured yet.
                                            </p>
                                            <Button onClick={() => setShowCreateDialog(true)}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Add First Courier
                                            </Button>
                                        </CardContent>
                                    </Card>
                                ) : (
                                    couriers.data.map((courier) => (
                                        <Card key={courier.id}>
                                            <CardHeader>
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <CardTitle className="flex items-center space-x-2">
                                                            <span>{courier.name}</span>
                                                            {courier.is_default && (
                                                                <Badge variant="default">Default</Badge>
                                                            )}
                                                            {!courier.is_active && (
                                                                <Badge variant="secondary">Inactive</Badge>
                                                            )}
                                                        </CardTitle>
                                                        <CardDescription>
                                                            Code: {courier.code}
                                                        </CardDescription>
                                                    </div>
                                                    <div className="flex space-x-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => handleEdit(courier)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => handleDelete(courier)}
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid grid-cols-2 gap-4 text-sm">
                                                    <div>
                                                        <strong>API Endpoint:</strong>
                                                        <p className="text-gray-600">{courier.api_endpoint || 'Not configured'}</p>
                                                    </div>
                                                    <div>
                                                        <strong>Tracking URL:</strong>
                                                        <p className="text-gray-600">{courier.tracking_url_template || 'Not configured'}</p>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
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

            {/* Edit Dialog */}
            <Dialog open={showEditDialog} onOpenChange={setShowEditDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Courier</DialogTitle>
                        <DialogDescription>
                            Update the courier information.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUpdateCourier}>
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="edit_name">Name</Label>
                                <Input
                                    id="edit_name"
                                    value={editData.name}
                                    onChange={(e) => setEditData('name', e.target.value)}
                                    required
                                />
                                {editErrors.name && (
                                    <p className="text-red-500 text-sm mt-1">{editErrors.name}</p>
                                )}
                            </div>
                            
                            <div>
                                <Label htmlFor="edit_code">Code</Label>
                                <Input
                                    id="edit_code"
                                    value={editData.code}
                                    onChange={(e) => setEditData('code', e.target.value)}
                                    required
                                />
                                {editErrors.code && (
                                    <p className="text-red-500 text-sm mt-1">{editErrors.code}</p>
                                )}
                            </div>
                            
                            <div>
                                <Label htmlFor="edit_api_endpoint">API Endpoint</Label>
                                <Input
                                    id="edit_api_endpoint"
                                    type="url"
                                    value={editData.api_endpoint}
                                    onChange={(e) => setEditData('api_endpoint', e.target.value)}
                                />
                                {editErrors.api_endpoint && (
                                    <p className="text-red-500 text-sm mt-1">{editErrors.api_endpoint}</p>
                                )}
                            </div>
                            
                            <div>
                                <Label htmlFor="edit_api_key">API Key</Label>
                                <Input
                                    id="edit_api_key"
                                    type="password"
                                    value={editData.api_key}
                                    onChange={(e) => setEditData('api_key', e.target.value)}
                                />
                                {editErrors.api_key && (
                                    <p className="text-red-500 text-sm mt-1">{editErrors.api_key}</p>
                                )}
                            </div>
                            
                            <div>
                                <Label htmlFor="edit_tracking_url_template">Tracking URL Template</Label>
                                <Input
                                    id="edit_tracking_url_template"
                                    value={editData.tracking_url_template}
                                    onChange={(e) => setEditData('tracking_url_template', e.target.value)}
                                />
                                {editErrors.tracking_url_template && (
                                    <p className="text-red-500 text-sm mt-1">{editErrors.tracking_url_template}</p>
                                )}
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
                        
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowEditDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={updating}>
                                {updating ? 'Updating...' : 'Update Courier'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
