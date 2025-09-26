export default function UpdateACSCredentialsForm({ tenant, className = '', status = '' }) {
    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    ACS Courier API Credentials
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Courier API credentials are now managed through the WordPress plugin.
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
                            WordPress Plugin Integration
                        </h3>
                        <div className="mt-2 text-sm text-blue-700">
                            <p>ACS Courier credentials are now configured through the DMM Delivery Bridge WordPress plugin.</p>
                            <p className="mt-1">This provides better security and centralized management of courier API credentials.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                <div className="flex">
                    <div className="flex-shrink-0">
                        <svg className="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                    </div>
                    <div className="ml-3">
                        <h3 className="text-sm font-medium text-green-800">
                            Benefits of WordPress Integration
                        </h3>
                        <div className="mt-2 text-sm text-green-700 space-y-1">
                            <p>• Centralized credential management in WordPress</p>
                            <p>• Enhanced security with WordPress authentication</p>
                            <p>• Automatic order synchronization</p>
                            <p>• Real-time tracking updates</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-md">
                <h4 className="text-sm font-medium text-gray-900 mb-2">
                    📋 Next Steps
                </h4>
                <div className="text-xs text-gray-600 space-y-1">
                    <p>1. Install the DMM Delivery Bridge plugin on your WordPress site</p>
                    <p>2. Configure your ACS credentials in the plugin settings</p>
                    <p>3. Orders will automatically sync to this application</p>
                    <p>4. Track shipments and receive status updates automatically</p>
                </div>
            </div>
        </section>
    );
} 