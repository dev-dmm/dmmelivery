import { useForm } from '@inertiajs/react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Transition } from '@headlessui/react';

export default function UpdateACSCredentialsForm({ tenant, className = '', status = '' }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        acs_api_key: tenant?.acs_api_key || '',
        acs_company_id: tenant?.acs_company_id || '',
        acs_company_password: tenant?.acs_company_password || '',
        acs_user_id: tenant?.acs_user_id || '',
        acs_user_password: tenant?.acs_user_password || '',
    });

    const isACSCredentialsUpdated = status === 'acs-credentials-updated';

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.acs-credentials.update'), {
            preserveScroll: true,
        });
    };

    const fillDemoCredentials = () => {
        setData({
            acs_api_key: 'demo',
            acs_company_id: 'demo',
            acs_company_password: 'demo',
            acs_user_id: 'demo',
            acs_user_password: 'demo',
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    ACS Courier API Credentials
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Update your ACS Courier API credentials to enable automatic shipment tracking and status updates.
                </p>
            </header>

            <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3 flex-1">
                        <h3 className="text-sm font-medium text-blue-800">
                            Demo Credentials Available
                        </h3>
                        <div className="mt-2 text-sm text-blue-700">
                            <p>You can use demo credentials for testing purposes.</p>
                        </div>
                        <div className="mt-3">
                            <button
                                type="button"
                                onClick={fillDemoCredentials}
                                className="text-sm bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded-md transition-colors"
                            >
                                Fill Demo Credentials
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="acs_api_key" value="API Key" />
                        <TextInput
                            id="acs_api_key"
                            className="mt-1 block w-full"
                            value={data.acs_api_key}
                            onChange={(e) => setData('acs_api_key', e.target.value)}
                            placeholder="Your ACS API Key"
                        />
                        <InputError className="mt-2" message={errors.acs_api_key} />
                        <p className="mt-1 text-xs text-gray-500">
                            Obtained from ACS Courier API portal
                        </p>
                    </div>

                    <div>
                        <InputLabel htmlFor="acs_company_id" value="Company ID" />
                        <TextInput
                            id="acs_company_id"
                            className="mt-1 block w-full"
                            value={data.acs_company_id}
                            onChange={(e) => setData('acs_company_id', e.target.value)}
                            placeholder="Your Company ID"
                        />
                        <InputError className="mt-2" message={errors.acs_company_id} />
                        <p className="mt-1 text-xs text-gray-500">
                            Your company identifier with ACS
                        </p>
                    </div>

                    <div>
                        <InputLabel htmlFor="acs_company_password" value="Company Password" />
                        <TextInput
                            id="acs_company_password"
                            type="password"
                            className="mt-1 block w-full"
                            value={data.acs_company_password}
                            onChange={(e) => setData('acs_company_password', e.target.value)}
                            placeholder="Your Company Password"
                        />
                        <InputError className="mt-2" message={errors.acs_company_password} />
                        <p className="mt-1 text-xs text-gray-500">
                            Company password for ACS API access
                        </p>
                    </div>

                    <div>
                        <InputLabel htmlFor="acs_user_id" value="User ID" />
                        <TextInput
                            id="acs_user_id"
                            className="mt-1 block w-full"
                            value={data.acs_user_id}
                            onChange={(e) => setData('acs_user_id', e.target.value)}
                            placeholder="Your User ID"
                        />
                        <InputError className="mt-2" message={errors.acs_user_id} />
                        <p className="mt-1 text-xs text-gray-500">
                            Your user identifier with ACS
                        </p>
                    </div>

                    <div className="sm:col-span-2">
                        <InputLabel htmlFor="acs_user_password" value="User Password" />
                        <TextInput
                            id="acs_user_password"
                            type="password"
                            className="mt-1 block w-full"
                            value={data.acs_user_password}
                            onChange={(e) => setData('acs_user_password', e.target.value)}
                            placeholder="Your User Password"
                        />
                        <InputError className="mt-2" message={errors.acs_user_password} />
                        <p className="mt-1 text-xs text-gray-500">
                            User password for ACS API access
                        </p>
                    </div>
                </div>

                {tenant?.has_acs_credentials && (
                    <div className="p-4 bg-green-50 border border-green-200 rounded-md">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-green-800">
                                    ACS Credentials Configured
                                </h3>
                                <div className="mt-2 text-sm text-green-700">
                                    <p>Your ACS API credentials are properly configured and ready for use.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Saving...' : 'Save ACS Credentials'}
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful || isACSCredentialsUpdated}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-green-600 flex items-center">
                            <svg className="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            ACS credentials saved successfully!
                        </p>
                    </Transition>
                </div>

                <div className="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-md">
                    <h4 className="text-sm font-medium text-gray-900 mb-2">
                        🔒 Security Note
                    </h4>
                    <div className="text-xs text-gray-600 space-y-1">
                        <p>• Your ACS credentials are encrypted and stored securely</p>
                        <p>• Only authorized personnel can access this information</p>
                        <p>• Use demo credentials for testing purposes only</p>
                        <p>• Contact ACS support to obtain your production API credentials</p>
                    </div>
                </div>
            </form>
        </section>
    );
} 