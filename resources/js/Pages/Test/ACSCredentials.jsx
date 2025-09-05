import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

export default function ACSCredentials() {
    const [credentials, setCredentials] = useState({
        acs_api_key: '',
        acs_company_id: '',
        acs_company_password: '',
        acs_user_id: '',
        acs_user_password: '',
    });
    
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const [currentCreds, setCurrentCreds] = useState(null);

    // Load current credentials
    useEffect(() => {
        fetchCurrentCredentials();
    }, []);

    const fetchCurrentCredentials = async () => {
        try {
            const response = await fetch('/api/acs/get-credentials');
            if (response.ok) {
                const data = await response.json();
                setCurrentCreds(data);
            }
        } catch (error) {
            console.error('Error fetching credentials:', error);
        }
    };

    const fillDemoCredentials = () => {
        setCredentials({
            acs_api_key: 'demo',
            acs_company_id: 'demo',
            acs_company_password: 'demo',
            acs_user_id: 'demo',
            acs_user_password: 'demo',
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setMessage('');

        try {
            const response = await fetch('/api/acs/update-credentials', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(credentials),
            });

            const result = await response.json();
            
            if (response.ok) {
                setMessage(`✅ ${result.message}`);
                fetchCurrentCredentials(); // Refresh current credentials
            } else {
                setMessage(`❌ Error: ${result.message || 'Failed to update credentials'}`);
            }
        } catch (error) {
            setMessage(`❌ Network Error: ${error.message}`);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    🧪 ACS Credentials Test (No CSRF)
                </h2>
            }
        >
            <Head title="ACS Credentials Test" />

            <div className="py-12">
                <div className="mx-auto">
                    
                    {/* Current Credentials Display */}
                    {currentCreds && (
                        <div className="mb-6 bg-white overflow-hidden shadow-sm rounded-lg">
                            <div className="p-6 bg-gray-50">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Current ACS Credentials
                                </h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span className="font-medium">Tenant:</span> {currentCreds.tenant_name}
                                    </div>
                                    <div>
                                        <span className="font-medium">API Key:</span> {currentCreds.credentials.acs_api_key || 'Not set'}
                                    </div>
                                    <div>
                                        <span className="font-medium">Company ID:</span> {currentCreds.credentials.acs_company_id || 'Not set'}
                                    </div>
                                    <div>
                                        <span className="font-medium">User ID:</span> {currentCreds.credentials.acs_user_id || 'Not set'}
                                    </div>
                                </div>
                                <div className="mt-3">
                                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                        currentCreds.credentials.has_credentials 
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-red-100 text-red-800'
                                    }`}>
                                        {currentCreds.credentials.has_credentials ? '✅ Configured' : '❌ Not Configured'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Update Form */}
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Update ACS Credentials
                                </h3>
                                <button
                                    onClick={fillDemoCredentials}
                                    className="bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded-md text-sm transition-colors"
                                >
                                    Fill Demo Credentials
                                </button>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel htmlFor="acs_api_key" value="API Key" />
                                        <TextInput
                                            id="acs_api_key"
                                            className="mt-1 block w-full"
                                            value={credentials.acs_api_key}
                                            onChange={(e) => setCredentials({...credentials, acs_api_key: e.target.value})}
                                            placeholder="Your ACS API Key"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="acs_company_id" value="Company ID" />
                                        <TextInput
                                            id="acs_company_id"
                                            className="mt-1 block w-full"
                                            value={credentials.acs_company_id}
                                            onChange={(e) => setCredentials({...credentials, acs_company_id: e.target.value})}
                                            placeholder="Your Company ID"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="acs_company_password" value="Company Password" />
                                        <TextInput
                                            id="acs_company_password"
                                            type="password"
                                            className="mt-1 block w-full"
                                            value={credentials.acs_company_password}
                                            onChange={(e) => setCredentials({...credentials, acs_company_password: e.target.value})}
                                            placeholder="Your Company Password"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="acs_user_id" value="User ID" />
                                        <TextInput
                                            id="acs_user_id"
                                            className="mt-1 block w-full"
                                            value={credentials.acs_user_id}
                                            onChange={(e) => setCredentials({...credentials, acs_user_id: e.target.value})}
                                            placeholder="Your User ID"
                                        />
                                    </div>

                                    <div className="md:col-span-2">
                                        <InputLabel htmlFor="acs_user_password" value="User Password" />
                                        <TextInput
                                            id="acs_user_password"
                                            type="password"
                                            className="mt-1 block w-full"
                                            value={credentials.acs_user_password}
                                            onChange={(e) => setCredentials({...credentials, acs_user_password: e.target.value})}
                                            placeholder="Your User Password"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center gap-4">
                                    <PrimaryButton disabled={loading}>
                                        {loading ? 'Saving...' : 'Save ACS Credentials'}
                                    </PrimaryButton>

                                    {message && (
                                        <div className={`text-sm font-medium ${
                                            message.includes('✅') ? 'text-green-600' : 'text-red-600'
                                        }`}>
                                            {message}
                                        </div>
                                    )}
                                </div>
                            </form>

                            <div className="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-md">
                                <h4 className="text-sm font-medium text-blue-900 mb-2">
                                    🧪 Test Information
                                </h4>
                                <div className="text-xs text-blue-700 space-y-1">
                                    <p>• This page uses API endpoints without CSRF protection for testing</p>
                                    <p>• Regular profile form should work normally after server restart</p>
                                    <p>• Demo credentials work for testing ACS API integration</p>
                                    <p>• Routes: POST /api/acs/update-credentials, GET /api/acs/get-credentials</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 