import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { 
    CogIcon, 
    BuildingOfficeIcon, 
    TruckIcon, 
    GlobeAltIcon,
    KeyIcon,
    CheckCircleIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
    ClipboardDocumentIcon,
    ArrowPathIcon
} from '@heroicons/react/24/outline';
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/react';

export default function SettingsIndex({ 
    auth, 
    tenant, 
    courier_options, 
    business_types, 
    currency_options 
}) {
    const [activeTab, setActiveTab] = useState(0);
    const [loading, setLoadingState] = useState({});
    const [messages, setMessages] = useState({});
    const [formData, setFormData] = useState({
        // Business Settings
        business_name: tenant.business_name || '',
        business_type: tenant.business_type || 'retail',
        contact_email: tenant.contact_email || '',
        contact_phone: tenant.contact_phone || '',
        business_address: tenant.business_address || '',
        website_url: tenant.website_url || '',
        default_currency: tenant.default_currency || 'EUR',
        tax_rate: tenant.tax_rate || 24.0,
        shipping_cost: tenant.shipping_cost || 0.0,
        auto_create_shipments: tenant.auto_create_shipments || false,
        send_notifications: tenant.send_notifications || true,
        
        // ACS Credentials
        acs_company_id: tenant.acs_company_id || '',
        acs_company_password: '',
        acs_user_id: tenant.acs_user_id || '',
        acs_user_password: '',
        
        // Other Courier APIs
        speedex_api_key: '',
        elta_api_key: '',
        geniki_api_key: '',
        
        // Webhook Settings
        webhook_url: tenant.webhook_url || '',
        webhook_secret: '',
    });

    const showMessage = (section, message, type = 'success') => {
        setMessages(prev => ({ ...prev, [section]: { message, type } }));
        setTimeout(() => {
            setMessages(prev => ({ ...prev, [section]: null }));
        }, 3000);
    };

    const setLoading = (section, isLoading) => {
        setLoadingState(prev => ({ ...prev, [section]: isLoading }));
    };

    const updateFormData = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const handleBusinessUpdate = async () => {
        setLoading('business', true);
        
        try {
            const response = await fetch(route('settings.business.update'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    business_name: formData.business_name,
                    business_type: formData.business_type,
                    contact_email: formData.contact_email,
                    contact_phone: formData.contact_phone,
                    business_address: formData.business_address,
                    website_url: formData.website_url,
                    default_currency: formData.default_currency,
                    tax_rate: formData.tax_rate,
                    shipping_cost: formData.shipping_cost,
                    auto_create_shipments: formData.auto_create_shipments,
                    send_notifications: formData.send_notifications,
                }),
            });

            const result = await response.json();
            
            if (result.success) {
                showMessage('business', result.message, 'success');
            } else {
                showMessage('business', result.message || 'Update failed', 'error');
            }
        } catch (error) {
            showMessage('business', 'Network error occurred', 'error');
        } finally {
            setLoading('business', false);
        }
    };

    const handleACSCredentialsUpdate = async () => {
        setLoading('acs', true);
        
        try {
            const response = await fetch(route('settings.courier.acs.update'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    acs_company_id: formData.acs_company_id,
                    acs_company_password: formData.acs_company_password,
                    acs_user_id: formData.acs_user_id,
                    acs_user_password: formData.acs_user_password,
                }),
            });

            const result = await response.json();
            
            if (result.success) {
                showMessage('acs', result.message, 'success');
                // Clear password fields after successful update
                setFormData(prev => ({
                    ...prev,
                    acs_company_password: '',
                    acs_user_password: ''
                }));
            } else {
                showMessage('acs', result.message || 'Update failed', 'error');
            }
        } catch (error) {
            showMessage('acs', 'Network error occurred', 'error');
        } finally {
            setLoading('acs', false);
        }
    };

    const fillDemoCredentials = () => {
        setFormData(prev => ({
            ...prev,
            acs_company_id: 'demo',
            acs_company_password: 'demo',
            acs_user_id: 'demo',
            acs_user_password: 'demo'
        }));
    };

    const testCourierConnection = async (courier) => {
        setLoading(`test_${courier}`, true);
        
        try {
            const response = await fetch(route('settings.courier.test'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ courier }),
            });

            const result = await response.json();
            
            if (result.success) {
                showMessage(`test_${courier}`, result.message, 'success');
            } else {
                showMessage(`test_${courier}`, result.message || 'Test failed', 'error');
            }
        } catch (error) {
            showMessage(`test_${courier}`, 'Network error occurred', 'error');
        } finally {
            setLoading(`test_${courier}`, false);
        }
    };

    const generateApiToken = async () => {
        setLoading('api_token', true);
        
        try {
            const response = await fetch(route('settings.api.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
            });

            const result = await response.json();
            
            if (result.success) {
                showMessage('api_token', 'New API token generated', 'success');
                // Copy to clipboard
                navigator.clipboard.writeText(result.api_token);
            } else {
                showMessage('api_token', result.message || 'Generation failed', 'error');
            }
        } catch (error) {
            showMessage('api_token', 'Network error occurred', 'error');
        } finally {
            setLoading('api_token', false);
        }
    };

    const getCourierStatusBadge = (status) => {
        if (status === 'configured') {
            return (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <CheckCircleIcon className="w-4 h-4 mr-1" />
                    Configured
                </span>
            );
        } else {
            return (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    <XCircleIcon className="w-4 h-4 mr-1" />
                    Not Configured
                </span>
            );
        }
    };

    const getMessageAlert = (section) => {
        const msg = messages[section];
        if (!msg) return null;

        const bgColor = msg.type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
        const textColor = msg.type === 'success' ? 'text-green-800' : 'text-red-800';
        const icon = msg.type === 'success' ? CheckCircleIcon : ExclamationTriangleIcon;
        const Icon = icon;

        return (
            <div className={`rounded-md border p-3 ${bgColor} ${textColor} mb-4`}>
                <div className="flex items-center">
                    <Icon className="h-5 w-5 mr-2" />
                    <span className="text-sm font-medium">{msg.message}</span>
                </div>
            </div>
        );
    };

    const tabs = [
        { name: '🏢 Business', icon: BuildingOfficeIcon },
        { name: '🚚 Couriers', icon: TruckIcon },
        { name: '🔗 API & Webhooks', icon: GlobeAltIcon },
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Settings</h2>}
        >
            <Head title="Settings" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <TabGroup>
                            <TabList className="flex border-b border-gray-200">
                                {tabs.map((tab, index) => (
                                    <Tab
                                        key={tab.name}
                                        className={({ selected }) =>
                                            `flex-1 px-6 py-4 text-sm font-medium text-center border-b-2 transition-colors ${
                                                selected
                                                    ? 'border-blue-500 text-blue-600'
                                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                            }`
                                        }
                                    >
                                        {tab.name}
                                    </Tab>
                                ))}
                            </TabList>

                            <TabPanels>
                                {/* Business Settings Tab */}
                                <TabPanel className="p-6">
                                    {getMessageAlert('business')}
                                    
                                    <div className="space-y-6">
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                                            
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <InputLabel htmlFor="business_name" value="Business Name *" />
                                                    <TextInput
                                                        id="business_name"
                                                        value={formData.business_name}
                                                        onChange={(e) => updateFormData('business_name', e.target.value)}
                                                        className="mt-1 block w-full"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="business_type" value="Business Type *" />
                                                    <select
                                                        id="business_type"
                                                        value={formData.business_type}
                                                        onChange={(e) => updateFormData('business_type', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                    >
                                                        {Object.entries(business_types).map(([key, label]) => (
                                                            <option key={key} value={key}>{label}</option>
                                                        ))}
                                                    </select>
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="contact_email" value="Contact Email *" />
                                                    <TextInput
                                                        id="contact_email"
                                                        type="email"
                                                        value={formData.contact_email}
                                                        onChange={(e) => updateFormData('contact_email', e.target.value)}
                                                        className="mt-1 block w-full"
                                                        required
                                                    />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="contact_phone" value="Contact Phone" />
                                                    <TextInput
                                                        id="contact_phone"
                                                        value={formData.contact_phone}
                                                        onChange={(e) => updateFormData('contact_phone', e.target.value)}
                                                        className="mt-1 block w-full"
                                                    />
                                                </div>
                                            </div>

                                            <div className="mt-4">
                                                <InputLabel htmlFor="business_address" value="Business Address" />
                                                <textarea
                                                    id="business_address"
                                                    value={formData.business_address}
                                                    onChange={(e) => updateFormData('business_address', e.target.value)}
                                                    rows={3}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                />
                                            </div>

                                            <div>
                                                <InputLabel htmlFor="website_url" value="Website URL" />
                                                <TextInput
                                                    id="website_url"
                                                    type="url"
                                                    value={formData.website_url}
                                                    onChange={(e) => updateFormData('website_url', e.target.value)}
                                                    className="mt-1 block w-full"
                                                    placeholder="https://example.com"
                                                />
                                            </div>
                                        </div>

                                        <div className="border-t pt-6">
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Order Defaults</h3>
                                            
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <InputLabel htmlFor="default_currency" value="Default Currency" />
                                                    <select
                                                        id="default_currency"
                                                        value={formData.default_currency}
                                                        onChange={(e) => updateFormData('default_currency', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                    >
                                                        {Object.entries(currency_options).map(([key, label]) => (
                                                            <option key={key} value={key}>{label}</option>
                                                        ))}
                                                    </select>
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="tax_rate" value="Tax Rate (%)" />
                                                    <TextInput
                                                        id="tax_rate"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        value={formData.tax_rate}
                                                        onChange={(e) => updateFormData('tax_rate', parseFloat(e.target.value))}
                                                        className="mt-1 block w-full"
                                                    />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="shipping_cost" value="Default Shipping Cost" />
                                                    <TextInput
                                                        id="shipping_cost"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={formData.shipping_cost}
                                                        onChange={(e) => updateFormData('shipping_cost', parseFloat(e.target.value))}
                                                        className="mt-1 block w-full"
                                                    />
                                                </div>
                                            </div>

                                            <div className="mt-4 space-y-3">
                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.auto_create_shipments}
                                                        onChange={(e) => updateFormData('auto_create_shipments', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2 text-sm text-gray-600">
                                                        Auto-create shipments for new orders
                                                    </span>
                                                </label>

                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={formData.send_notifications}
                                                        onChange={(e) => updateFormData('send_notifications', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2 text-sm text-gray-600">
                                                        Send notifications to customers
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div className="flex justify-end">
                                            <PrimaryButton
                                                onClick={handleBusinessUpdate}
                                                disabled={loading.business}
                                            >
                                                {loading.business ? (
                                                    <>
                                                        <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                                        Saving...
                                                    </>
                                                ) : (
                                                    'Save Business Settings'
                                                )}
                                            </PrimaryButton>
                                        </div>
                                    </div>
                                </TabPanel>

                                {/* Couriers Tab */}
                                <TabPanel className="p-6">
                                    <div className="space-y-6">
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Courier Integrations</h3>
                                            <p className="text-sm text-gray-600 mb-6">
                                                Configure API credentials for your courier partners to enable real-time tracking and automated processing.
                                            </p>
                                        </div>

                                        {Object.entries(courier_options).map(([key, courier]) => (
                                            <div key={key} className="border rounded-lg p-6">
                                                <div className="flex items-center justify-between mb-4">
                                                    <div className="flex items-center space-x-3">
                                                        <span className="text-2xl">{courier.logo}</span>
                                                        <div>
                                                            <h4 className="text-lg font-medium text-gray-900">{courier.name}</h4>
                                                            <p className="text-sm text-gray-500">{courier.description}</p>
                                                        </div>
                                                    </div>
                                                    {getCourierStatusBadge(courier.status)}
                                                </div>

                                                {key === 'acs' && (
                                                    <div className="space-y-4">
                                                        {getMessageAlert('acs')}
                                                        
                                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                            <div>
                                                                <InputLabel htmlFor="acs_company_id" value="Company ID" />
                                                                <TextInput
                                                                    id="acs_company_id"
                                                                    value={formData.acs_company_id}
                                                                    onChange={(e) => updateFormData('acs_company_id', e.target.value)}
                                                                    className="mt-1 block w-full"
                                                                />
                                                            </div>

                                                            <div>
                                                                <InputLabel htmlFor="acs_company_password" value="Company Password" />
                                                                <TextInput
                                                                    id="acs_company_password"
                                                                    type="password"
                                                                    value={formData.acs_company_password}
                                                                    onChange={(e) => updateFormData('acs_company_password', e.target.value)}
                                                                    className="mt-1 block w-full"
                                                                    placeholder={tenant.acs_company_password ? "••••••••" : "Enter password"}
                                                                />
                                                            </div>

                                                            <div>
                                                                <InputLabel htmlFor="acs_user_id" value="User ID" />
                                                                <TextInput
                                                                    id="acs_user_id"
                                                                    value={formData.acs_user_id}
                                                                    onChange={(e) => updateFormData('acs_user_id', e.target.value)}
                                                                    className="mt-1 block w-full"
                                                                />
                                                            </div>

                                                            <div>
                                                                <InputLabel htmlFor="acs_user_password" value="User Password" />
                                                                <TextInput
                                                                    id="acs_user_password"
                                                                    type="password"
                                                                    value={formData.acs_user_password}
                                                                    onChange={(e) => updateFormData('acs_user_password', e.target.value)}
                                                                    className="mt-1 block w-full"
                                                                    placeholder={tenant.acs_user_password ? "••••••••" : "Enter password"}
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center justify-between">
                                                            <SecondaryButton onClick={fillDemoCredentials}>
                                                                Fill Demo Credentials
                                                            </SecondaryButton>

                                                            <div className="flex space-x-3">
                                                                {tenant.has_acs_credentials && (
                                                                    <SecondaryButton
                                                                        onClick={() => testCourierConnection('acs')}
                                                                        disabled={loading.test_acs}
                                                                    >
                                                                        {loading.test_acs ? (
                                                                            <>
                                                                                <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                                                                Testing...
                                                                            </>
                                                                        ) : (
                                                                            'Test Connection'
                                                                        )}
                                                                    </SecondaryButton>
                                                                )}

                                                                <PrimaryButton
                                                                    onClick={handleACSCredentialsUpdate}
                                                                    disabled={loading.acs}
                                                                >
                                                                    {loading.acs ? (
                                                                        <>
                                                                            <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                                                            Saving...
                                                                        </>
                                                                    ) : (
                                                                        'Save ACS Credentials'
                                                                    )}
                                                                </PrimaryButton>
                                                            </div>
                                                        </div>

                                                        {getMessageAlert('test_acs')}
                                                    </div>
                                                )}

                                                {key !== 'acs' && (
                                                    <div className="text-center py-8 text-gray-500">
                                                        <p className="text-sm">
                                                            {courier.name} integration coming soon!
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </TabPanel>

                                {/* API & Webhooks Tab */}
                                <TabPanel className="p-6">
                                    <div className="space-y-6">
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">API Access</h3>
                                            
                                            <div className="bg-gray-50 rounded-lg p-4">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900">API Token</h4>
                                                        <p className="text-sm text-gray-500">
                                                            {tenant.api_token ? 'Token is configured' : 'No token generated'}
                                                        </p>
                                                    </div>
                                                    
                                                    <SecondaryButton
                                                        onClick={generateApiToken}
                                                        disabled={loading.api_token}
                                                    >
                                                        {loading.api_token ? (
                                                            <>
                                                                <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                                                Generating...
                                                            </>
                                                        ) : (
                                                            <>
                                                                <KeyIcon className="-ml-1 mr-2 h-4 w-4" />
                                                                Generate New Token
                                                            </>
                                                        )}
                                                    </SecondaryButton>
                                                </div>

                                                {getMessageAlert('api_token')}
                                            </div>
                                        </div>

                                        <div className="border-t pt-6">
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Webhook Configuration</h3>
                                            
                                            <div className="space-y-4">
                                                <div>
                                                    <InputLabel htmlFor="webhook_url" value="Webhook URL" />
                                                    <TextInput
                                                        id="webhook_url"
                                                        type="url"
                                                        value={formData.webhook_url}
                                                        onChange={(e) => updateFormData('webhook_url', e.target.value)}
                                                        className="mt-1 block w-full"
                                                        placeholder="https://your-site.com/webhook"
                                                    />
                                                    <p className="mt-1 text-sm text-gray-500">
                                                        Receive real-time notifications about shipment status changes
                                                    </p>
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="webhook_secret" value="Webhook Secret" />
                                                    <TextInput
                                                        id="webhook_secret"
                                                        type="password"
                                                        value={formData.webhook_secret}
                                                        onChange={(e) => updateFormData('webhook_secret', e.target.value)}
                                                        className="mt-1 block w-full"
                                                        placeholder={tenant.webhook_secret ? "••••••••" : "Enter secret key"}
                                                    />
                                                    <p className="mt-1 text-sm text-gray-500">
                                                        Optional secret key for webhook verification
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="border-t pt-6">
                                            <h3 className="text-lg font-medium text-gray-900 mb-4">Usage Information</h3>
                                            
                                            <div className="bg-blue-50 rounded-lg p-4">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                    <div>
                                                        <span className="font-medium text-blue-900">Subscription Plan:</span>
                                                        <span className="ml-2 text-blue-700">{tenant.subscription_plan || 'Free'}</span>
                                                    </div>
                                                    <div>
                                                        <span className="font-medium text-blue-900">Monthly Limit:</span>
                                                        <span className="ml-2 text-blue-700">
                                                            {tenant.current_month_shipments || 0} / {tenant.monthly_shipment_limit || '∞'} shipments
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </TabPanel>
                            </TabPanels>
                        </TabGroup>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 