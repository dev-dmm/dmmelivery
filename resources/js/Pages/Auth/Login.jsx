import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [loginAttempted, setLoginAttempted] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        setLoginAttempted(true);

        // Get CSRF token from meta tag
        const token = document.head.querySelector('meta[name="csrf-token"]');
        if (!token) {
            console.error('CSRF token not found for login');
            setLoginAttempted(false);
            return;
        }

        post(route('login'), {
            headers: {
                'X-CSRF-TOKEN': token.content,
            },
            onStart: () => {
                console.log('Login attempt starting...');
            },
            onSuccess: () => {
                console.log('Login successful!');
            },
            onError: (errors) => {
                console.log('Login errors:', errors);
                setLoginAttempted(false);
            },
            onFinish: () => {
                reset('password');
                console.log('Login attempt finished');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-6">
                <div className="flex items-center justify-center mb-4">
                    <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                <h2 className="text-center text-3xl font-bold text-gray-900">
                    Sign in to your account
                </h2>
                <p className="mt-2 text-center text-sm text-gray-600">
                    Access your shipment tracking dashboard
                </p>
            </div>

            {/* Demo Credentials Helper */}
            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 className="text-sm font-medium text-blue-900 mb-2">🎯 Demo Credentials:</h3>
                <div className="text-xs text-blue-800 space-y-1">
                    <div><code className="bg-blue-100 px-1 rounded">electroshop@demo.com</code> / <code className="bg-blue-100 px-1 rounded">password</code></div>
                    <div><code className="bg-blue-100 px-1 rounded">fashionboutique@demo.com</code> / <code className="bg-blue-100 px-1 rounded">password</code></div>
                    <div><code className="bg-blue-100 px-1 rounded">bookstoreplus@demo.com</code> / <code className="bg-blue-100 px-1 rounded">password</code></div>
                </div>
            </div>

            {status && (
                <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <p className="text-sm font-medium text-green-800">{status}</p>
                        </div>
                    </div>
                </div>
            )}

            {processing && (
                <div className="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div className="flex items-center">
                        <div className="flex-shrink-0">
                            <svg className="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <div className="ml-3">
                            <p className="text-sm font-medium text-blue-800">Signing you in...</p>
                        </div>
                    </div>
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="Enter your email address"
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="Enter your password"
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex items-center justify-between">
                    <div className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ml-2 text-sm text-gray-600">
                            Remember me
                        </span>
                    </div>

                    {canResetPassword && (
                        <div className="text-sm">
                            <Link
                                href={route('password.request')}
                                className="font-medium text-blue-600 hover:text-blue-500"
                            >
                                Forgot your password?
                            </Link>
                        </div>
                    )}
                </div>

                <div>
                    <PrimaryButton 
                        className="w-full justify-center" 
                        disabled={processing}
                    >
                        {processing ? (
                            <div className="flex items-center">
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Signing in...
                            </div>
                        ) : (
                            'Sign in'
                        )}
                    </PrimaryButton>
                </div>

                {/* Debug Information (remove in production) */}
                {process.env.NODE_ENV === 'development' && (
                    <div className="mt-6 p-3 bg-gray-100 rounded text-xs text-gray-600">
                        <p><strong>Debug Info:</strong></p>
                        <p>Processing: {processing ? 'Yes' : 'No'}</p>
                        <p>Login Attempted: {loginAttempted ? 'Yes' : 'No'}</p>
                        <p>Errors: {Object.keys(errors).length > 0 ? JSON.stringify(errors) : 'None'}</p>
                    </div>
                )}
            </form>
        </GuestLayout>
    );
}
