import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';

export default function EShopRegister({ businessTypes, subscriptionPlans }) {
    const [currentStep, setCurrentStep] = useState(1);
    const [subdomainStatus, setSubdomainStatus] = useState(null);
    const [isCheckingSubdomain, setIsCheckingSubdomain] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        // Business Information
        business_name: '',
        business_type: 'eshop',
        description: '',
        website_url: '',
        subdomain: '',
        
        // Contact Information
        contact_name: '',
        contact_email: '',
        contact_phone: '',
        
        // Address Information
        business_address: '',
        city: '',
        postal_code: '',
        country: 'GR',
        
        // Tax Information
        vat_number: '',
        tax_office: '',
        
        // Admin User Information
        admin_first_name: '',
        admin_last_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
        
        // Note: ACS credentials are now managed through WordPress plugin
        
        // Subscription Plan
        subscription_plan: 'starter',
        
        // Terms & Conditions
        terms_accepted: false,
        privacy_accepted: false,
    });

    // Check subdomain availability
    const checkSubdomain = async (subdomain) => {
        if (!subdomain || subdomain.length < 3) return;
        
        setIsCheckingSubdomain(true);
        
        try {
            const response = await fetch('/register/check-subdomain', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ subdomain }),
            });
            
            const result = await response.json();
            setSubdomainStatus(result);
        } catch (error) {
            setSubdomainStatus({ available: false, message: 'Error checking subdomain' });
        } finally {
            setIsCheckingSubdomain(false);
        }
    };

    // Debounced subdomain checking
    useEffect(() => {
        const timer = setTimeout(() => {
            if (data.subdomain) {
                checkSubdomain(data.subdomain);
            }
        }, 500);
        
        return () => clearTimeout(timer);
    }, [data.subdomain]);

    const submit = (e) => {
        e.preventDefault();
        
        // Get CSRF token from meta tag
        const token = document.head.querySelector('meta[name="csrf-token"]');
        if (!token) {
            console.error('CSRF token not found for eShop registration');
            return;
        }
        
        post(route('registration.submit'), {
            headers: {
                'X-CSRF-TOKEN': token.content,
            },
        });
    };

    const nextStep = () => {
        setCurrentStep(currentStep + 1);
    };

    const prevStep = () => {
        setCurrentStep(currentStep - 1);
    };

    const renderBusinessInfo = () => (
        <div className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Business Information</h2>
                <p className="mt-2 text-gray-600">Tell us about your eShop</p>
            </div>

            {/* Business Name */}
            <div>
                <InputLabel htmlFor="business_name" value="Business Name *" />
                <TextInput
                    id="business_name"
                    type="text"
                    className="mt-1 block w-full"
                    value={data.business_name}
                    onChange={(e) => setData('business_name', e.target.value)}
                    placeholder="Your Business Name"
                    required
                />
                <InputError message={errors.business_name} className="mt-2" />
            </div>

            {/* Business Type */}
            <div>
                <InputLabel htmlFor="business_type" value="Business Type *" />
                <select
                    id="business_type"
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.business_type}
                    onChange={(e) => setData('business_type', e.target.value)}
                    required
                >
                    {Object.entries(businessTypes).map(([key, label]) => (
                        <option key={key} value={key}>{label}</option>
                    ))}
                </select>
                <InputError message={errors.business_type} className="mt-2" />
            </div>

            {/* Subdomain */}
            <div>
                <InputLabel htmlFor="subdomain" value="Subdomain *" />
                <div className="mt-1 flex rounded-md shadow-sm">
                    <TextInput
                        id="subdomain"
                        type="text"
                        className="flex-1 rounded-l-md border-r-0"
                        value={data.subdomain}
                        onChange={(e) => setData('subdomain', e.target.value.toLowerCase())}
                        placeholder="myshop"
                        required
                    />
                    <span className="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                        .eshoptracker.gr
                    </span>
                </div>
                
                {/* Subdomain Status */}
                {isCheckingSubdomain && (
                    <p className="mt-1 text-sm text-gray-500">Checking availability...</p>
                )}
                {subdomainStatus && !isCheckingSubdomain && (
                    <p className={`mt-1 text-sm ${subdomainStatus.available ? 'text-green-600' : 'text-red-600'}`}>
                        {subdomainStatus.message}
                        {subdomainStatus.available && subdomainStatus.preview_url && (
                            <span className="block text-gray-500">
                                Your store will be available at: https://{subdomainStatus.preview_url}
                            </span>
                        )}
                    </p>
                )}
                <InputError message={errors.subdomain} className="mt-2" />
            </div>

            {/* Description */}
            <div>
                <InputLabel htmlFor="description" value="Description (Optional)" />
                <textarea
                    id="description"
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="Brief description of your business..."
                />
                <InputError message={errors.description} className="mt-2" />
            </div>

            {/* Website URL */}
            <div>
                <InputLabel htmlFor="website_url" value="Website URL (Optional)" />
                <TextInput
                    id="website_url"
                    type="url"
                    className="mt-1 block w-full"
                    value={data.website_url}
                    onChange={(e) => setData('website_url', e.target.value)}
                    placeholder="https://yourwebsite.com"
                />
                <InputError message={errors.website_url} className="mt-2" />
            </div>
        </div>
    );

    const renderContactInfo = () => (
        <div className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Contact & Address Information</h2>
                <p className="mt-2 text-gray-600">How can we reach you?</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Contact Name */}
                <div>
                    <InputLabel htmlFor="contact_name" value="Contact Name *" />
                    <TextInput
                        id="contact_name"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.contact_name}
                        onChange={(e) => setData('contact_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.contact_name} className="mt-2" />
                </div>

                {/* Contact Email */}
                <div>
                    <InputLabel htmlFor="contact_email" value="Contact Email *" />
                    <TextInput
                        id="contact_email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.contact_email}
                        onChange={(e) => setData('contact_email', e.target.value)}
                        required
                    />
                    <InputError message={errors.contact_email} className="mt-2" />
                </div>
            </div>

            {/* Contact Phone */}
            <div>
                <InputLabel htmlFor="contact_phone" value="Contact Phone (Optional)" />
                <TextInput
                    id="contact_phone"
                    type="tel"
                    className="mt-1 block w-full"
                    value={data.contact_phone}
                    onChange={(e) => setData('contact_phone', e.target.value)}
                    placeholder="+30 210 1234567"
                />
                <InputError message={errors.contact_phone} className="mt-2" />
            </div>

            {/* Business Address */}
            <div>
                <InputLabel htmlFor="business_address" value="Business Address *" />
                <textarea
                    id="business_address"
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={2}
                    value={data.business_address}
                    onChange={(e) => setData('business_address', e.target.value)}
                    placeholder="Street address..."
                    required
                />
                <InputError message={errors.business_address} className="mt-2" />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {/* City */}
                <div>
                    <InputLabel htmlFor="city" value="City *" />
                    <TextInput
                        id="city"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.city}
                        onChange={(e) => setData('city', e.target.value)}
                        required
                    />
                    <InputError message={errors.city} className="mt-2" />
                </div>

                {/* Postal Code */}
                <div>
                    <InputLabel htmlFor="postal_code" value="Postal Code *" />
                    <TextInput
                        id="postal_code"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.postal_code}
                        onChange={(e) => setData('postal_code', e.target.value)}
                        required
                    />
                    <InputError message={errors.postal_code} className="mt-2" />
                </div>

                {/* Country */}
                <div>
                    <InputLabel htmlFor="country" value="Country *" />
                    <select
                        id="country"
                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.country}
                        onChange={(e) => setData('country', e.target.value)}
                        required
                    >
                        <option value="GR">Greece</option>
                        <option value="CY">Cyprus</option>
                    </select>
                    <InputError message={errors.country} className="mt-2" />
                </div>
            </div>

            {/* Tax Information */}
            <div className="pt-6 border-t border-gray-200">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Tax Information (Optional)</h3>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="vat_number" value="VAT Number" />
                        <TextInput
                            id="vat_number"
                            type="text"
                            className="mt-1 block w-full"
                            value={data.vat_number}
                            onChange={(e) => setData('vat_number', e.target.value)}
                            placeholder="EL123456789"
                        />
                        <InputError message={errors.vat_number} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="tax_office" value="Tax Office" />
                        <TextInput
                            id="tax_office"
                            type="text"
                            className="mt-1 block w-full"
                            value={data.tax_office}
                            onChange={(e) => setData('tax_office', e.target.value)}
                            placeholder="DOY Athens"
                        />
                        <InputError message={errors.tax_office} className="mt-2" />
                    </div>
                </div>
            </div>
        </div>
    );

    const renderAdminInfo = () => (
        <div className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Admin Account</h2>
                <p className="mt-2 text-gray-600">Create your admin user account</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel htmlFor="admin_first_name" value="First Name *" />
                    <TextInput
                        id="admin_first_name"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.admin_first_name}
                        onChange={(e) => setData('admin_first_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.admin_first_name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="admin_last_name" value="Last Name *" />
                    <TextInput
                        id="admin_last_name"
                        type="text"
                        className="mt-1 block w-full"
                        value={data.admin_last_name}
                        onChange={(e) => setData('admin_last_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.admin_last_name} className="mt-2" />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="admin_email" value="Email Address *" />
                <TextInput
                    id="admin_email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.admin_email}
                    onChange={(e) => setData('admin_email', e.target.value)}
                    required
                />
                <InputError message={errors.admin_email} className="mt-2" />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel htmlFor="admin_password" value="Password *" />
                    <TextInput
                        id="admin_password"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.admin_password}
                        onChange={(e) => setData('admin_password', e.target.value)}
                        required
                    />
                    <InputError message={errors.admin_password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="admin_password_confirmation" value="Confirm Password *" />
                    <TextInput
                        id="admin_password_confirmation"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.admin_password_confirmation}
                        onChange={(e) => setData('admin_password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.admin_password_confirmation} className="mt-2" />
                </div>
            </div>
        </div>
    );

    const renderSubscriptionPlan = () => (
        <div className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Choose Your Plan</h2>
                <p className="mt-2 text-gray-600">Select the plan that fits your needs</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {Object.entries(subscriptionPlans).map(([key, plan]) => (
                    <div
                        key={key}
                        className={`relative border-2 rounded-lg p-4 cursor-pointer ${
                            data.subscription_plan === key
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-300 hover:border-gray-400'
                        } ${plan.recommended ? 'ring-2 ring-blue-500 ring-opacity-50' : ''}`}
                        onClick={() => setData('subscription_plan', key)}
                    >
                        {plan.recommended && (
                            <span className="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-blue-500 text-white px-3 py-1 text-xs font-medium rounded-full">
                                Recommended
                            </span>
                        )}
                        
                        <div className="text-center">
                            <h3 className="text-lg font-semibold text-gray-900">{plan.name}</h3>
                            <div className="mt-2">
                                <span className="text-3xl font-bold text-gray-900">€{plan.price}</span>
                                <span className="text-gray-600">/month</span>
                            </div>
                            <div className="mt-2">
                                <span className="text-sm text-gray-600">
                                    {typeof plan.shipments === 'number' ? `${plan.shipments} shipments` : plan.shipments}
                                </span>
                            </div>
                            <ul className="mt-4 space-y-2 text-sm text-gray-600">
                                {plan.features.map((feature, index) => (
                                    <li key={index} className="flex items-center">
                                        <svg className="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                        </svg>
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        
                        <input
                            type="radio"
                            name="subscription_plan"
                            value={key}
                            checked={data.subscription_plan === key}
                            onChange={() => setData('subscription_plan', key)}
                            className="sr-only"
                        />
                    </div>
                ))}
            </div>
            <InputError message={errors.subscription_plan} className="mt-2" />
        </div>
    );


    const renderTerms = () => (
        <div className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">Terms & Conditions</h2>
                <p className="mt-2 text-gray-600">Almost done! Please review and accept our terms.</p>
            </div>

            <div className="space-y-4">
                <div className="flex items-start">
                    <div className="flex items-center h-5">
                        <Checkbox
                            id="terms_accepted"
                            name="terms_accepted"
                            checked={data.terms_accepted}
                            onChange={(e) => setData('terms_accepted', e.target.checked)}
                        />
                    </div>
                    <div className="ml-3 text-sm">
                        <label htmlFor="terms_accepted" className="font-medium text-gray-700">
                            I accept the{' '}
                            <a href="#" className="text-indigo-600 hover:text-indigo-500">
                                Terms and Conditions
                            </a>
                        </label>
                    </div>
                </div>

                <div className="flex items-start">
                    <div className="flex items-center h-5">
                        <Checkbox
                            id="privacy_accepted"
                            name="privacy_accepted"
                            checked={data.privacy_accepted}
                            onChange={(e) => setData('privacy_accepted', e.target.checked)}
                        />
                    </div>
                    <div className="ml-3 text-sm">
                        <label htmlFor="privacy_accepted" className="font-medium text-gray-700">
                            I accept the{' '}
                            <a href="#" className="text-indigo-600 hover:text-indigo-500">
                                Privacy Policy
                            </a>
                        </label>
                    </div>
                </div>
            </div>

            <InputError message={errors.terms_accepted} className="mt-2" />
            <InputError message={errors.privacy_accepted} className="mt-2" />

            {/* Summary */}
            <div className="bg-gray-50 rounded-lg p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Registration Summary</h3>
                <dl className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt className="font-medium text-gray-500">Business Name</dt>
                        <dd className="text-gray-900">{data.business_name}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Subdomain</dt>
                        <dd className="text-gray-900">{data.subdomain}.eshoptracker.gr</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Contact Email</dt>
                        <dd className="text-gray-900">{data.contact_email}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Subscription Plan</dt>
                        <dd className="text-gray-900">{subscriptionPlans[data.subscription_plan]?.name}</dd>
                    </div>
                </dl>
            </div>
        </div>
    );

    const steps = [
        { number: 1, title: 'Business Info', component: renderBusinessInfo },
        { number: 2, title: 'Contact & Address', component: renderContactInfo },
        { number: 3, title: 'Admin Account', component: renderAdminInfo },
        { number: 4, title: 'Subscription', component: renderSubscriptionPlan },
        { number: 5, title: 'Terms & Conditions', component: renderTerms },
    ];

    return (
        <GuestLayout>
            <Head title="eShop Registration" />
            
            <div className="min-h-screen bg-gray-50 py-12">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Progress Steps */}
                    <div className="mb-8">
                        <nav aria-label="Progress">
                            <ol className="flex items-center justify-between">
                                {steps.map((step) => (
                                    <li key={step.number} className="relative">
                                        <div className="flex items-center">
                                            <div
                                                className={`flex items-center justify-center w-10 h-10 rounded-full border-2 ${
                                                    currentStep >= step.number
                                                        ? 'bg-indigo-600 border-indigo-600 text-white'
                                                        : 'border-gray-300 text-gray-500'
                                                }`}
                                            >
                                                {currentStep > step.number ? (
                                                    <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                ) : (
                                                    <span className="text-sm font-medium">{step.number}</span>
                                                )}
                                            </div>
                                            <span className="ml-2 text-sm font-medium text-gray-500 hidden sm:block">
                                                {step.title}
                                            </span>
                                        </div>
                                        {step.number < steps.length && (
                                            <div className="absolute top-5 left-10 w-full h-px bg-gray-300 -z-10 hidden sm:block" />
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </nav>
                    </div>

                    {/* Form */}
                    <div className="bg-white shadow rounded-lg">
                        <form onSubmit={submit} className="p-8">
                            {steps[currentStep - 1].component()}
                            
                            {/* Navigation Buttons */}
                            <div className="mt-8 flex justify-between">
                                <div>
                                    {currentStep > 1 && (
                                        <SecondaryButton onClick={prevStep} type="button">
                                            Previous
                                        </SecondaryButton>
                                    )}
                                </div>
                                
                                <div className="flex space-x-3">
                                    {currentStep < steps.length && (
                                        <PrimaryButton onClick={nextStep} type="button">
                                            Next
                                        </PrimaryButton>
                                    )}
                                    
                                    {currentStep === steps.length && (
                                        <PrimaryButton type="submit" disabled={processing}>
                                            {processing ? 'Creating Account...' : 'Create eShop Account'}
                                        </PrimaryButton>
                                    )}
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
} 