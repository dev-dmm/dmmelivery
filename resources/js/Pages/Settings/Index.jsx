import React, { useMemo, useState, useCallback, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import {
  BuildingOfficeIcon,
  TruckIcon,
  GlobeAltIcon,
  KeyIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClipboardDocumentIcon,
  ArrowPathIcon,
} from '@heroicons/react/24/outline';
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/react';

/* ---------------------- Api Service ---------------------- */
class ApiService {
  constructor() {
    // In Inertia/Laravel, route('...') returns absolute URLs. Keep base optional.
    this.baseURL = typeof window !== 'undefined' ? window.location.origin : '';
    this.csrfToken = null;
    this.initCSRFToken();
  }

  initCSRFToken() {
    if (typeof document !== 'undefined') {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      if (!token) {
        console.warn('CSRF token not found in meta tags');
        // Try to get it from the page props or other sources
        const pageToken = document.querySelector('input[name="_token"]')?.value;
        this.csrfToken = pageToken || null;
      } else {
        this.csrfToken = token;
      }
    }
  }

  getHeaders(additionalHeaders = {}) {
    const headers = { 'Content-Type': 'application/json', ...additionalHeaders };
    if (this.csrfToken) headers['X-CSRF-TOKEN'] = this.csrfToken;
    return headers;
  }

  // If endpoint is absolute (starts with http), don't prefix baseURL
  resolveUrl(endpoint) {
    if (typeof endpoint === 'string' && /^https?:\/\//i.test(endpoint)) return endpoint;
    return `${this.baseURL}${endpoint}`;
  }

  async post(endpoint, data = {}, additionalHeaders = {}) {
    try {
      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {}),
        ...additionalHeaders,
      };
      
      console.log('API Post Headers:', headers);
      console.log('CSRF Token:', this.csrfToken);
      
      const response = await fetch(this.resolveUrl(endpoint), {
        method: 'POST',
        credentials: 'same-origin', // ✅ send your session cookie (fixes 419)
        headers,
        body: JSON.stringify(data),
      });

      // Try parsing JSON even when not OK, so we can surface server messages
      const maybeJson = await response.clone().json().catch(() => null);

      if (!response.ok) {
        const errText = maybeJson?.message || (await response.text().catch(() => 'Unknown error'));
        throw new Error(`HTTP ${response.status}: ${errText}`);
      }

      return maybeJson ?? (await response.json());
    } catch (error) {
      if (error instanceof TypeError) {
        throw new Error('Network error: Please check your connection');
      }
      throw error;
    }
  }
}

/* ---------------------- Rate Limiter ---------------------- */
const useRateLimit = (limit = 10, windowMs = 60000) => {
  const [calls, setCalls] = useState([]);

  const canMakeCall = useCallback(() => {
    const now = Date.now();
    const validCalls = calls.filter((time) => now - time < windowMs);
    setCalls(validCalls);
    return validCalls.length < limit;
  }, [calls, limit, windowMs]);

  const recordCall = useCallback(() => {
    setCalls((prev) => [...prev, Date.now()]);
  }, []);

  return { canMakeCall, recordCall };
};

/* ---------------------- Sanitizer ---------------------- */
const sanitizeInput = (input) =>
  String(input ?? '')
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/javascript:/gi, '')
    .replace(/on\w+\s*=/gi, '')
    .trim();

/* ---------------------- Component ---------------------- */
export default function SettingsIndex({
  auth,
  tenant,
  courier_options,
  business_types,
  currency_options,
}) {
  const [activeTab, setActiveTab] = useState(0);
  const [loading, setLoadingState] = useState({});
  const [messages, setMessages] = useState({});

  const couriers = courier_options || {};
  const hasAcsCredentials = Boolean(tenant?.has_acs_credentials);
  const tenantId = tenant?.id || '';
  const apiToken = tenant?.api_token || '';
  // holds the FULL token only when we just generated one
  const [unmaskedApiToken, setUnmaskedApiToken] = useState('');

  // 👇 local token used ONLY by the Quick Test (must be plain ASCII, no •)
  const [testApiKey, setTestApiKey] = useState(() => {
    // Don't auto-populate with 'configured' - leave empty so user knows to generate/paste a token
    return '';
  });

  const [formData, setFormData] = useState({
    // Business Settings
    business_name: tenant?.business_name || '',
    business_type: tenant?.business_type || 'retail',
    contact_email: tenant?.contact_email || '',
    contact_phone: tenant?.contact_phone || '',
    business_address: tenant?.business_address || '',
    website_url: tenant?.website_url || '',
    default_currency: tenant?.default_currency || 'EUR',
    tax_rate: tenant?.tax_rate ?? 24.0,
    shipping_cost: tenant?.shipping_cost ?? 0.0,
    auto_create_shipments: tenant?.auto_create_shipments || false,
    send_notifications: tenant?.send_notifications ?? true,

    // ACS Credentials
    acs_api_key: tenant?.acs_api_key || '',
    acs_company_id: tenant?.acs_company_id || '',
    acs_company_password: '',
    acs_user_id: tenant?.acs_user_id || '',
    acs_user_password: '',

    // Webhook Settings
    webhook_url: tenant?.webhook_url || '',
    webhook_secret: '',
  });

  const apiService = useMemo(() => new ApiService(), []);
  const { canMakeCall, recordCall } = useRateLimit(20, 60000);

  const updateFormData = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const showMessage = useCallback((section, message, type = 'success') => {
    setMessages((prev) => ({ ...prev, [section]: { message: sanitizeInput(message), type } }));
    const timer = setTimeout(() => {
      setMessages((prev) => ({ ...prev, [section]: null }));
    }, 5000);
    return () => clearTimeout(timer);
  }, []);

  const setLoading = useCallback((section, isLoading) => {
    setLoadingState((prev) => ({ ...prev, [section]: isLoading }));
  }, []);

  const copyToClipboard = useCallback(
    async (text, section = 'general') => {
      try {
        if (!text) throw new Error('No text to copy');

        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
        } else {
          // Fallback
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.left = '-999999px';
          ta.style.top = '-999999px';
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          try {
            document.execCommand('copy');
          } finally {
            document.body.removeChild(ta);
          }
        }
        showMessage(section, 'Copied to clipboard ✅', 'success');
      } catch (err) {
        console.error('Copy failed:', err);
        showMessage(section, 'Failed to copy to clipboard', 'error');
      }
    },
    [showMessage]
  );

  /* --------- Actions --------- */
  const handleBusinessUpdate = useCallback(async () => {
    if (!canMakeCall()) {
      showMessage('business', 'Too many requests. Please wait a moment.', 'error');
      return;
    }

    setLoading('business', true);
    recordCall();

    try {
      const payload = {
        business_name: sanitizeInput(formData.business_name),
        business_type: formData.business_type,
        contact_email: sanitizeInput(formData.contact_email),
        contact_phone: formData.contact_phone ? sanitizeInput(formData.contact_phone) : '',
        business_address: formData.business_address ? sanitizeInput(formData.business_address) : '',
        website_url: formData.website_url || '',
        default_currency: formData.default_currency,
        tax_rate: formData.tax_rate,
        shipping_cost: formData.shipping_cost,
        auto_create_shipments: formData.auto_create_shipments,
        send_notifications: formData.send_notifications,
      };

      const routeUrl = route('settings.business.update');
      console.log('Posting to:', routeUrl, 'with payload:', payload);
      const result = await apiService.post(routeUrl, payload);

      if (result?.success) {
        showMessage('business', result.message || 'Business settings updated successfully', 'success');
      } else {
        showMessage('business', result?.message || 'Update failed', 'error');
      }
    } catch (error) {
      console.error('Business update error:', error);
      showMessage('business', error instanceof Error ? error.message : 'Update failed', 'error');
    } finally {
      setLoading('business', false);
    }
  }, [apiService, canMakeCall, recordCall, setLoading, showMessage, formData]);

  const handleACSCredentialsUpdate = useCallback(async () => {
    if (!canMakeCall()) {
      showMessage('acs', 'Too many requests. Please wait a moment.', 'error');
      return;
    }

    setLoading('acs', true);
    recordCall();

    try {
      const payload = {
        acs_api_key: sanitizeInput(formData.acs_api_key),
        acs_company_id: sanitizeInput(formData.acs_company_id),
        acs_company_password: formData.acs_company_password,
        acs_user_id: sanitizeInput(formData.acs_user_id),
        acs_user_password: formData.acs_user_password,
      };

      const result = await apiService.post(route('settings.courier.acs.update'), payload);

      if (result?.success) {
        showMessage('acs', result.message || 'ACS credentials updated successfully', 'success');
        updateFormData('acs_company_password', '');
        updateFormData('acs_user_password', '');
      } else {
        showMessage('acs', result?.message || 'Update failed', 'error');
      }
    } catch (error) {
      console.error('ACS update error:', error);
      showMessage('acs', error instanceof Error ? error.message : 'Update failed', 'error');
    } finally {
      setLoading('acs', false);
    }
  }, [apiService, formData, canMakeCall, recordCall, setLoading, showMessage]);

  const fillDemoCredentials = useCallback(() => {
    updateFormData('acs_api_key', 'demo');
    updateFormData('acs_company_id', 'demo');
    updateFormData('acs_company_password', 'demo');
    updateFormData('acs_user_id', 'demo');
    updateFormData('acs_user_password', 'demo');
    showMessage('acs', 'Demo credentials filled', 'success');
  }, [showMessage]);

  const testCourierConnection = useCallback(
    async (courier) => {
      if (!canMakeCall()) {
        showMessage(`test_${courier}`, 'Too many requests. Please wait a moment.', 'error');
        return;
      }

      setLoading(`test_${courier}`, true);
      recordCall();

      try {
        const result = await apiService.post(route('settings.courier.test'), { courier });

        if (result?.success) {
          showMessage(`test_${courier}`, result.message || 'Connection test successful', 'success');
        } else {
          showMessage(`test_${courier}`, result?.message || 'Test failed', 'error');
        }
      } catch (error) {
        console.error('Courier test error:', error);
        showMessage(`test_${courier}`, error instanceof Error ? error.message : 'Test failed', 'error');
      } finally {
        setLoading(`test_${courier}`, false);
      }
    },
    [apiService, canMakeCall, recordCall, setLoading, showMessage]
  );

  const generateApiToken = useCallback(async () => {
    if (!canMakeCall()) {
      showMessage('api_token', 'Too many requests. Please wait a moment.', 'error');
      return;
    }
  
    setLoading('api_token', true);
    recordCall();
  
    try {
      const result = await apiService.post(route('settings.api.generate'), {});
      if (result?.success && result.api_token) {
        setUnmaskedApiToken(result.api_token);        // ← store full token locally
        setTestApiKey(result.api_token);              // keep Quick Test in sync
        await copyToClipboard(result.api_token, 'api_token'); // copy the real one
        showMessage('api_token', 'New API token generated and copied to clipboard', 'success');
      } else {
        showMessage('api_token', result?.message || 'Generation failed', 'error');
      }
    } catch (error) {
      console.error('Token generation error:', error);
      showMessage('api_token', error instanceof Error ? error.message : 'Generation failed', 'error');
    } finally {
      setLoading('api_token', false);
    }
  }, [apiService, canMakeCall, recordCall, setLoading, showMessage, copyToClipboard]);
  

  /* --------- Woo Bridge --------- */
  const wooEndpoint = useMemo(() => {
    const baseUrl = typeof window !== 'undefined' ? window.location.origin : '';
    return `${baseUrl}/api/woocommerce/order`;
  }, []);

  const testWooBridge = useCallback(async () => {
    // validate token & tenant id
    if (!testApiKey || !/^[\x20-\x7E]+$/.test(testApiKey)) {
      showMessage('woo', 'Paste a valid API token (no • characters) in the Quick Test input.', 'error');
      return;
    }
    if (!tenantId) {
      showMessage('woo', 'Tenant ID is required', 'error');
      return;
    }

    if (!canMakeCall()) {
      showMessage('woo', 'Too many requests. Please wait a moment.', 'error');
      return;
    }

    setLoading('woo_test', true);
    recordCall();

    const testPayload = {
      source: 'woocommerce',
      order: {
        external_order_id: `TEST-${Date.now()}`,
        order_number: `TEST-${Date.now()}`,
        status: 'pending',
        total_amount: 12.34,
        subtotal: 10.0,
        tax_amount: 2.34,
        shipping_cost: 0.0,
        currency: 'EUR',
        payment_status: 'pending',
        payment_method: 'cod',
      },
      customer: {
        first_name: 'Test',
        last_name: 'Customer',
        email: 'test@example.com',
        phone: '6999999999',
      },
      shipping: {
        address: {
          first_name: 'Test',
          last_name: 'Customer',
          address_1: 'Patision 1',
          address_2: '',
          city: 'Athina',
          postcode: '10434',
          country: 'GR',
          phone: '6999999999',
          email: 'test@example.com',
        },
      },
      create_shipment: true,
    };

    try {
      const response = await fetch(wooEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Api-Key': testApiKey,
          'X-Tenant-Id': tenantId,
        },
        body: JSON.stringify(testPayload),
      });

      const data = await response.json().catch(() => ({}));

      if (response.ok && data?.success) {
        const message = `Bridge test successful: order_id ${data.order_id}${
          data.shipment_id ? `, shipment_id ${data.shipment_id}` : ''
        }`;
        showMessage('woo', message, 'success');
      } else {
        const errorMessage = data?.message || `HTTP ${response.status}`;
        showMessage('woo', `Bridge test failed: ${errorMessage}`, 'error');
      }
    } catch (error) {
      console.error('WooCommerce test error:', error);
      showMessage('woo', 'Network error during bridge test', 'error');
    } finally {
      setLoading('woo_test', false);
    }
  }, [testApiKey, tenantId, wooEndpoint, canMakeCall, recordCall, setLoading, showMessage]);

  const downloadPlugin = useCallback(async () => {
    if (!canMakeCall()) {
      showMessage('download', 'Too many requests. Please wait a moment.', 'error');
      return;
    }

    setLoading('download', true);
    recordCall();

    try {
      const result = await apiService.post(route('settings.download.plugin'), {});

      if (result?.success && result.download_url) {
        // Create a temporary link to trigger download
        const link = document.createElement('a');
        link.href = result.download_url;
        link.download = 'dmm-delivery-bridge.zip';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showMessage('download', 'Plugin download started! Check your downloads folder.', 'success');
      } else {
        showMessage('download', result?.message || 'Download failed', 'error');
      }
    } catch (error) {
      console.error('Download error:', error);
      showMessage('download', error instanceof Error ? error.message : 'Download failed', 'error');
    } finally {
      setLoading('download', false);
    }
  }, [apiService, canMakeCall, recordCall, setLoading, showMessage]);

  /* --------- UI Helpers --------- */
  const getCourierStatusBadge = useCallback((status) => {
    const statusConfig = {
      active: { label: 'Active', color: 'bg-green-100 text-green-800' },
      inactive: { label: 'Inactive', color: 'bg-gray-100 text-gray-800' },
      configured: { label: 'Configured', color: 'bg-blue-100 text-blue-800' },
      pending: { label: 'Pending Setup', color: 'bg-yellow-100 text-yellow-800' },
      error: { label: 'Error', color: 'bg-red-100 text-red-800' },
    };
    const config = statusConfig[status] || statusConfig.pending;
    return <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${config.color}`}>{config.label}</span>;
  }, []);

  const getMessageAlert = useCallback(
    (section) => {
      const msg = messages[section];
      if (!msg) return null;

      const bgColor = msg.type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
      const textColor = msg.type === 'success' ? 'text-green-800' : 'text-red-800';
      const Icon = msg.type === 'success' ? CheckCircleIcon : ExclamationTriangleIcon;

      return (
        <div className={`rounded-md border p-3 ${bgColor} ${textColor} mb-4`}>
          <div className="flex items-center">
            <Icon className="h-5 w-5 mr-2 flex-shrink-0" />
            <span className="text-sm font-medium">{msg.message}</span>
          </div>
        </div>
      );
    },
    [messages]
  );

  useEffect(() => {
    return () => setMessages({});
  }, []);

  const tabs = [
    { name: '🏢 Business', icon: BuildingOfficeIcon },
    { name: '🚚 Couriers', icon: TruckIcon },
    { name: '🔗 API & Webhooks', icon: GlobeAltIcon },
    { name: '📦 Download Plugin', icon: ClipboardDocumentIcon },
  ];

  const maskedToken = apiToken === 'configured' ? '••••••••••••••••' : (apiToken ? `${apiToken.slice(0, 4)}••••${apiToken.slice(-4)}` : '—');

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-lg lg:text-xl text-gray-800 leading-tight">Settings</h2>}>
      <Head title="Settings" />

      <div className="py-4 lg:py-12">
        <div className="mx-auto">
          <div className="bg-white overflow-hidden shadow-sm rounded-lg">
            <TabGroup selectedIndex={activeTab} onChange={setActiveTab}>
              <TabList className="flex flex-col sm:flex-row border-b border-gray-200 overflow-x-auto">
                {tabs.map((tab) => (
                  <Tab
                    key={tab.name}
                    className={({ selected }) =>
                      `flex-1 px-2 sm:px-3 lg:px-6 py-2 sm:py-3 lg:py-4 text-xs sm:text-sm font-medium text-center border-b-2 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-inset whitespace-nowrap ${
                        selected ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      }`
                    }
                  >
                    {tab.name}
                  </Tab>
                ))}
              </TabList>

              <TabPanels>
                {/* Business Settings Tab */}
                <TabPanel className="p-4 lg:p-6">
                  {getMessageAlert('business')}

                  <div className="space-y-4 lg:space-y-6">
                    <div>
                      <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-3 lg:mb-4">Business Information</h3>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
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
                            required
                          >
                            {Object.entries(business_types || {}).map(([key, label]) => (
                              <option key={key} value={key}>
                                {label}
                              </option>
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

                    <div className="border-t pt-4 lg:pt-6">
                      <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-3 lg:mb-4">Order Defaults</h3>

                      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">
                        <div>
                          <InputLabel htmlFor="default_currency" value="Default Currency" />
                          <select
                            id="default_currency"
                            value={formData.default_currency}
                            onChange={(e) => updateFormData('default_currency', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                          >
                            {Object.entries(currency_options ?? {}).map(([key, label]) => (
                              <option key={key} value={key}>
                                {label}
                              </option>
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

                      <div className="mt-3 lg:mt-4 space-y-2 lg:space-y-3">
                        <label className="flex items-center">
                          <input
                            type="checkbox"
                            checked={formData.auto_create_shipments}
                            onChange={(e) => updateFormData('auto_create_shipments', e.target.checked)}
                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                          />
                          <span className="ml-2 text-xs lg:text-sm text-gray-600">Auto-create shipments for new orders</span>
                        </label>

                        <label className="flex items-center">
                          <input
                            type="checkbox"
                            checked={formData.send_notifications}
                            onChange={(e) => updateFormData('send_notifications', e.target.checked)}
                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                          />
                          <span className="ml-2 text-xs lg:text-sm text-gray-600">Send notifications to customers</span>
                        </label>
                      </div>
                    </div>

                    <div className="flex justify-end">
                      <PrimaryButton onClick={handleBusinessUpdate} disabled={loading.business}>
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
                <TabPanel className="p-4 lg:p-6">
                  <div className="space-y-4 lg:space-y-6">
                    <div>
                      <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-3 lg:mb-4">Courier Integrations</h3>
                      <p className="text-xs lg:text-sm text-gray-600 mb-4 lg:mb-6">
                        Configure API credentials for your courier partners to enable real-time tracking and automated processing.
                      </p>
                    </div>

                    {Object.entries(couriers).length > 0 ? (
                      Object.entries(couriers).map(([key, courier]) => (
                        <div key={key} className="border rounded-lg p-4 lg:p-6">
                          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-3 lg:mb-4 gap-3">
                            <div className="flex items-center space-x-2 lg:space-x-3">
                              <span className="text-xl lg:text-2xl flex-shrink-0" aria-hidden="true">
                                {courier.logo}
                              </span>
                              <div className="min-w-0 flex-1">
                                <h4 className="text-base lg:text-lg font-medium text-gray-900">{courier.name}</h4>
                                <p className="text-xs lg:text-sm text-gray-500">{courier.description}</p>
                              </div>
                            </div>
                            <div className="flex-shrink-0 self-start sm:self-auto">
                              {getCourierStatusBadge(courier.status)}
                            </div>
                          </div>

                          {key === 'acs' ? (
                            <div className="space-y-4">
                              {getMessageAlert('acs')}

                              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                                <div>
                                  <InputLabel htmlFor="acs_api_key" value="ACS API Key" />
                                  <TextInput
                                    id="acs_api_key"
                                    value={formData.acs_api_key}
                                    onChange={(e) => updateFormData('acs_api_key', e.target.value)}
                                    className="mt-1 block w-full"
                                    placeholder="Enter your ACS API key"
                                  />
                                </div>

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
                                    placeholder={tenant?.acs_company_id ? '••••••••' : 'Enter password'}
                                    autoComplete="new-password"
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
                                    placeholder={tenant?.acs_user_id ? '••••••••' : 'Enter password'}
                                    autoComplete="new-password"
                                  />
                                </div>
                              </div>

                              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-4 lg:mt-6 gap-3 lg:gap-4">
                                <SecondaryButton onClick={fillDemoCredentials} className="w-full sm:w-auto text-xs lg:text-sm">
                                  Fill Demo Credentials
                                </SecondaryButton>

                                <div className="flex flex-col sm:flex-row gap-2 lg:gap-3 w-full sm:w-auto">
                                  {hasAcsCredentials && (
                                    <SecondaryButton 
                                      onClick={() => testCourierConnection('acs')} 
                                      disabled={loading.test_acs}
                                      className="w-full sm:w-auto"
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
                                    className="w-full sm:w-auto"
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
                          ) : (
                            <div className="text-center py-8 text-gray-500">
                              <p className="text-sm">{courier.name} integration coming soon!</p>
                            </div>
                          )}
                        </div>
                      ))
                    ) : (
                      <div className="text-center py-8 text-gray-500">
                        <p className="text-sm">No couriers configured yet.</p>
                      </div>
                    )}
                  </div>
                </TabPanel>

                {/* API & Webhooks Tab */}
                <TabPanel className="p-4 lg:p-6">
                  <div className="space-y-4 lg:space-y-6">
                    {/* API Token */}
                    <div>
                      <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-3 lg:mb-4">API Access</h3>

                      <div className="bg-gray-50 rounded-lg p-3 lg:p-4">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 lg:gap-4">
                          <div className="min-w-0 flex-1">
                            <h4 className="text-xs lg:text-sm font-medium text-gray-900">API Token</h4>
                            <p className="text-xs lg:text-sm text-gray-500">{apiToken ? 'Token is configured' : 'No token generated'}</p>
                            {apiToken && (
                              <code className="text-xs bg-white px-2 py-1 rounded border break-all block mt-2">
                                Token: {maskedToken}
                              </code>
                            )}
                          </div>

                          <div className="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                            {apiToken && (
                              <SecondaryButton
                                onClick={() => {
                                  if (unmaskedApiToken) {
                                    copyToClipboard(unmaskedApiToken, 'api_token');
                                  } else {
                                    showMessage(
                                      'api_token',
                                      'For security, the full token is only shown right after generation. Click "Generate New Token" to get a copyable value.',
                                      'error'
                                    );
                                  }
                                }}
                                aria-label="Copy API token to clipboard"
                                disabled={!unmaskedApiToken}
                                title={!unmaskedApiToken ? 'Generate a new token to copy the full value' : ''}
                                className="w-full sm:w-auto"
                              >
                                <ClipboardDocumentIcon className="-ml-1 mr-2 h-4 w-4" />
                                Copy token
                              </SecondaryButton>
                            )}
                            <SecondaryButton 
                              onClick={generateApiToken} 
                              disabled={loading.api_token}
                              className="w-full sm:w-auto"
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
                        </div>

                        {getMessageAlert('api_token')}
                      </div>
                    </div>

                    {/* WooCommerce Bridge */}
                    <div className="border-t pt-4 lg:pt-6">
                      <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-3 lg:mb-4">WooCommerce Bridge</h3>

                      {getMessageAlert('woo')}

                      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                        <div className="bg-white border rounded-lg p-3 lg:p-4">
                          <h4 className="text-xs lg:text-sm font-medium text-gray-900 mb-2">Endpoint (POST)</h4>
                          <div className="flex items-center gap-2 mb-4">
                            <code className="text-xs bg-gray-50 px-2 py-1 rounded break-all flex-1">{wooEndpoint}</code>
                            <SecondaryButton onClick={() => copyToClipboard(wooEndpoint, 'woo')} aria-label="Copy WooCommerce endpoint">
                              <ClipboardDocumentIcon className="-ml-1 mr-2 h-4 w-4" />
                              Copy
                            </SecondaryButton>
                          </div>

                          <div className="space-y-3">
                            <h5 className="text-sm font-medium text-gray-900">Required Headers</h5>

                            <div className="space-y-2">
                              <div className="flex items-center gap-2">
                                <code className="text-xs bg-gray-50 px-2 py-1 rounded break-all flex-1">X-Api-Key: {maskedToken}</code>
                                {apiToken && (
                                  <SecondaryButton 
                                    onClick={() => {
                                      if (unmaskedApiToken) {
                                        copyToClipboard(unmaskedApiToken, 'woo');
                                      } else {
                                        showMessage('woo', 'Generate a new token to get a copyable value', 'error');
                                      }
                                    }} 
                                    aria-label="Copy API key"
                                  >
                                    <ClipboardDocumentIcon className="-ml-1 mr-2 h-4 w-4" />
                                    Copy
                                  </SecondaryButton>
                                )}
                              </div>

                              <div className="flex items-center gap-2">
                                <code className="text-xs bg-gray-50 px-2 py-1 rounded break-all flex-1">X-Tenant-Id: {tenantId || '—'}</code>
                                {tenantId && (
                                  <SecondaryButton onClick={() => copyToClipboard(tenantId, 'woo')} aria-label="Copy tenant ID">
                                    <ClipboardDocumentIcon className="-ml-1 mr-2 h-4 w-4" />
                                    Copy
                                  </SecondaryButton>
                                )}
                              </div>
                            </div>

                            {/* NEW: Quick Test token input (unmasked, ASCII only) */}
                            <div className="mt-3">
                              <label className="text-xs text-gray-600">API key for Quick Test</label>
                              <input
                                type="text"
                                value={testApiKey}
                                onChange={(e) => setTestApiKey(e.target.value.trim())}
                                placeholder="Generate a new token or paste your full API token here"
                                className="mt-1 w-full rounded border px-2 py-1 text-xs"
                                autoComplete="off"
                              />
                              <p className="text-[11px] text-gray-500 mt-1">
                                Must be plain ASCII (no • characters). Use “Copy token” or paste the token you generated.
                              </p>
                            </div>

                            <p className="text-xs text-gray-500 mt-2">
                              The WooCommerce plugin should send orders to this endpoint using these headers. Your Laravel controller accepts either
                              the tenant token or a global bridge key.
                            </p>
                          </div>
                        </div>

                        <div className="bg-white border rounded-lg p-4">
                          <h4 className="text-sm font-medium text-gray-900 mb-2">Quick Test</h4>
                          <p className="text-xs text-gray-600 mb-3">
                            Sends a minimal WooCommerce-style payload from your browser to verify the endpoint works correctly.
                          </p>
                          <PrimaryButton onClick={testWooBridge} disabled={loading.woo_test || !testApiKey || !tenantId}>
                            {loading.woo_test ? (
                              <>
                                <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                Testing...
                              </>
                            ) : (
                              'Send Test Order'
                            )}
                          </PrimaryButton>
                          {(!testApiKey || !tenantId) && (
                            <p className="text-xs text-red-600 mt-2">
                              {!testApiKey && !tenantId
                                ? 'Paste an API token and ensure tenant ID is available.'
                                : !testApiKey
                                ? 'Paste an API token first.'
                                : 'Tenant ID is required.'}
                            </p>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* Webhooks */}
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
                          <p className="mt-1 text-sm text-gray-500">Receive real-time notifications about shipment status changes</p>
                        </div>

                        <div>
                          <InputLabel htmlFor="webhook_secret" value="Webhook Secret" />
                          <TextInput
                            id="webhook_secret"
                            type="password"
                            value={formData.webhook_secret}
                            onChange={(e) => updateFormData('webhook_secret', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder={tenant?.webhook_url ? '••••••••' : 'Enter secret key'}
                            autoComplete="new-password"
                          />
                          <p className="mt-1 text-sm text-gray-500">Optional secret key for webhook verification</p>
                        </div>
                      </div>
                    </div>

                    {/* Usage */}
                    <div className="border-t pt-6">
                      <h3 className="text-lg font-medium text-gray-900 mb-4">Usage Information</h3>

                      <div className="bg-blue-50 rounded-lg p-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                          <div>
                            <span className="font-medium text-blue-900">Subscription Plan:</span>
                            <span className="ml-2 text-blue-700">{tenant?.subscription_plan || 'Free'}</span>
                          </div>
                          <div>
                            <span className="font-medium text-blue-900">Monthly Usage:</span>
                            <span className="ml-2 text-blue-700">
                              {tenant?.current_month_shipments || 0} / {tenant?.monthly_shipment_limit || '∞'} shipments
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </TabPanel>

                {/* Download Plugin Tab */}
                <TabPanel className="p-4 lg:p-6">
                  <div className="space-y-4 lg:space-y-6">
                    <div>
                      <h3 className="text-base lg:text-lg font-medium text-gray-900 mb-3 lg:mb-4">Download WordPress Plugin</h3>
                      <p className="text-xs lg:text-sm text-gray-600 mb-4 lg:mb-6">
                        Download the complete DMM Delivery Bridge plugin as a zip file for installation on other WordPress sites.
                      </p>
                    </div>

                    {getMessageAlert('download')}

                    <div className="bg-green-50 border border-green-200 rounded-lg p-4 lg:p-6">
                      <div className="flex items-start space-x-3">
                        <div className="flex-shrink-0">
                          <ClipboardDocumentIcon className="h-6 w-6 text-green-600" />
                        </div>
                        <div className="flex-1">
                          <h4 className="text-sm font-medium text-green-900 mb-2">Plugin Package</h4>
                          <p className="text-sm text-green-700 mb-4">
                            The downloaded zip file contains the complete WordPress plugin that can be installed on any WordPress site with WooCommerce.
                          </p>
                          
                          <div className="bg-white rounded-md p-3 mb-4">
                            <h5 className="text-xs font-medium text-gray-900 mb-2">What's included:</h5>
                            <ul className="text-xs text-gray-600 space-y-1">
                              <li>• Complete WordPress plugin file (dm-delivery-bridge.php)</li>
                              <li>• Automatic order synchronization with DMM Delivery</li>
                              <li>• WooCommerce integration</li>
                              <li>• Admin interface for configuration</li>
                              <li>• Bulk order processing tools</li>
                              <li>• Debug and logging features</li>
                            </ul>
                          </div>

                          <div className="flex flex-col sm:flex-row gap-3">
                            <PrimaryButton 
                              onClick={downloadPlugin} 
                              disabled={loading.download}
                              className="w-full sm:w-auto"
                            >
                              {loading.download ? (
                                <>
                                  <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                  Creating Zip...
                                </>
                              ) : (
                                <>
                                  <ClipboardDocumentIcon className="-ml-1 mr-2 h-4 w-4" />
                                  Download Plugin Zip
                                </>
                              )}
                            </PrimaryButton>
                            
                            <SecondaryButton 
                              onClick={() => copyToClipboard(wooEndpoint, 'download')}
                              className="w-full sm:w-auto"
                            >
                              <ClipboardDocumentIcon className="-ml-1 mr-2 h-4 w-4" />
                              Copy API Endpoint
                            </SecondaryButton>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                      <h4 className="text-sm font-medium text-blue-900 mb-2">Installation Instructions</h4>
                      <div className="text-sm text-blue-700 space-y-2">
                        <p>1. Download the plugin zip file using the button above</p>
                        <p>2. Go to your WordPress admin → Plugins → Add New → Upload Plugin</p>
                        <p>3. Upload the downloaded zip file and activate the plugin</p>
                        <p>4. Configure the plugin with your API endpoint and credentials</p>
                        <p>5. The plugin will automatically sync WooCommerce orders to DMM Delivery</p>
                      </div>
                    </div>

                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                      <h4 className="text-sm font-medium text-gray-900 mb-2">API Configuration</h4>
                      <div className="space-y-2">
                        <div className="flex items-center gap-2">
                          <code className="text-xs bg-white px-2 py-1 rounded border break-all flex-1">
                            API Endpoint: {wooEndpoint}
                          </code>
                          <SecondaryButton onClick={() => copyToClipboard(wooEndpoint, 'download')} size="sm">
                            <ClipboardDocumentIcon className="h-3 w-3" />
                          </SecondaryButton>
                        </div>
                        <div className="flex items-center gap-2">
                          <code className="text-xs bg-white px-2 py-1 rounded border break-all flex-1">
                            API Key: {maskedToken}
                          </code>
                          {apiToken && (
                            <SecondaryButton 
                              onClick={() => {
                                if (unmaskedApiToken) {
                                  copyToClipboard(unmaskedApiToken, 'download');
                                } else {
                                  showMessage('download', 'Generate a new token to get a copyable value', 'error');
                                }
                              }} 
                              size="sm"
                              disabled={!unmaskedApiToken}
                            >
                              <ClipboardDocumentIcon className="h-3 w-3" />
                            </SecondaryButton>
                          )}
                        </div>
                        <div className="flex items-center gap-2">
                          <code className="text-xs bg-white px-2 py-1 rounded border break-all flex-1">
                            Tenant ID: {tenantId || '—'}
                          </code>
                          {tenantId && (
                            <SecondaryButton onClick={() => copyToClipboard(tenantId, 'download')} size="sm">
                              <ClipboardDocumentIcon className="h-3 w-3" />
                            </SecondaryButton>
                          )}
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
