import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { 
  Plus, 
  ArrowLeft,
  User,
  MapPin,
  Package,
  Truck,
  CreditCard,
  Save,
  X
} from 'lucide-react';

export default function Create({ couriers, customers }) {
  const { data, setData, post, processing, errors } = useForm({
    customer_id: '',
    courier_id: '',
    tracking_number: '',
    weight: '',
    shipping_address: '',
    billing_address: '',
    shipping_cost: '',
    estimated_delivery: '',
    notes: ''
  });

  const [currentStep, setCurrentStep] = useState(1);
  const totalSteps = 4;

  const handleSubmit = (e) => {
    e.preventDefault();
    post('/shipments', {
      onSuccess: () => {
        // Redirect to shipments index after successful creation
        window.location.href = '/shipments';
      }
    });
  };

  const nextStep = () => {
    if (currentStep < totalSteps) {
      setCurrentStep(currentStep + 1);
    }
  };

  const prevStep = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  };

  const steps = [
    { number: 1, title: 'Στοιχεία Αποστολέα', icon: <User className="w-5 h-5" /> },
    { number: 2, title: 'Στοιχεία Παραλήπτη', icon: <MapPin className="w-5 h-5" /> },
    { number: 3, title: 'Προϊόν & Courier', icon: <Package className="w-5 h-5" /> },
    { number: 4, title: 'Επιβεβαίωση', icon: <CreditCard className="w-5 h-5" /> }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Δημιουργία Νέας Αποστολής" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="bg-blue-100 rounded-full p-3 mr-4">
                <Plus className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Δημιουργία Νέας Αποστολής</h1>
                <p className="text-gray-600 mt-1">Δημιουργήστε μια νέα αποστολή στο σύστημα</p>
              </div>
            </div>
            <a
              href="/shipments"
              className="flex items-center px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Επιστροφή
            </a>
          </div>
        </div>

        {/* Progress Steps */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            {steps.map((step, index) => (
              <div key={step.number} className="flex items-center">
                <div className={`flex items-center justify-center w-10 h-10 rounded-full ${
                  currentStep >= step.number 
                    ? 'bg-blue-600 text-white' 
                    : 'bg-gray-200 text-gray-600'
                }`}>
                  {step.icon}
                </div>
                <div className="ml-3">
                  <p className={`text-sm font-medium ${
                    currentStep >= step.number ? 'text-blue-600' : 'text-gray-500'
                  }`}>
                    {step.title}
                  </p>
                </div>
                {index < steps.length - 1 && (
                  <div className={`w-16 h-0.5 mx-4 ${
                    currentStep > step.number ? 'bg-blue-600' : 'bg-gray-200'
                  }`} />
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            {/* Step 1: Sender Information */}
            {currentStep === 1 && (
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Στοιχεία Αποστολέα</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Πελάτης *
                    </label>
                    <select
                      value={data.customer_id}
                      onChange={(e) => setData('customer_id', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      required
                    >
                      <option value="">Επιλέξτε πελάτη</option>
                      {customers.map((customer) => (
                        <option key={customer.id} value={customer.id}>
                          {customer.name} - {customer.email}
                        </option>
                      ))}
                    </select>
                    {errors.customer_id && (
                      <p className="text-red-500 text-sm mt-1">{errors.customer_id}</p>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* Step 2: Receiver Information */}
            {currentStep === 2 && (
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Στοιχεία Παραλήπτη</h3>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Διεύθυνση Παράδοσης *
                    </label>
                    <textarea
                      value={data.shipping_address}
                      onChange={(e) => setData('shipping_address', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      rows="3"
                      placeholder="Συμπληρώστε τη διεύθυνση παράδοσης"
                      required
                    />
                    {errors.shipping_address && (
                      <p className="text-red-500 text-sm mt-1">{errors.shipping_address}</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Διεύθυνση Χρέωσης
                    </label>
                    <textarea
                      value={data.billing_address}
                      onChange={(e) => setData('billing_address', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      rows="3"
                      placeholder="Συμπληρώστε τη διεύθυνση χρέωσης (προαιρετικά)"
                    />
                  </div>
                </div>
              </div>
            )}

            {/* Step 3: Product & Courier */}
            {currentStep === 3 && (
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Προϊόν & Courier</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Courier *
                    </label>
                    <select
                      value={data.courier_id}
                      onChange={(e) => setData('courier_id', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      required
                    >
                      <option value="">Επιλέξτε courier</option>
                      {couriers.map((courier) => (
                        <option key={courier.id} value={courier.id}>
                          {courier.name} ({courier.code})
                        </option>
                      ))}
                    </select>
                    {errors.courier_id && (
                      <p className="text-red-500 text-sm mt-1">{errors.courier_id}</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Αριθμός Παρακολούθησης
                    </label>
                    <input
                      type="text"
                      value={data.tracking_number}
                      onChange={(e) => setData('tracking_number', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Αυτόματη δημιουργία αν αφεθεί κενό"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Βάρος (kg)
                    </label>
                    <input
                      type="number"
                      step="0.1"
                      value={data.weight}
                      onChange={(e) => setData('weight', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="0.0"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Κόστος Αποστολής (€)
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      value={data.shipping_cost}
                      onChange={(e) => setData('shipping_cost', e.target.value)}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="0.00"
                    />
                  </div>
                </div>
              </div>
            )}

            {/* Step 4: Confirmation */}
            {currentStep === 4 && (
              <div>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Επιβεβαίωση Αποστολής</h3>
                <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                  <div className="flex justify-between">
                    <span className="font-medium">Πελάτης:</span>
                    <span>{customers.find(c => c.id == data.customer_id)?.name || 'Δεν επιλέχθηκε'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="font-medium">Courier:</span>
                    <span>{couriers.find(c => c.id == data.courier_id)?.name || 'Δεν επιλέχθηκε'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="font-medium">Διεύθυνση Παράδοσης:</span>
                    <span className="text-right max-w-xs">{data.shipping_address || 'Δεν συμπληρώθηκε'}</span>
                  </div>
                  {data.weight && (
                    <div className="flex justify-between">
                      <span className="font-medium">Βάρος:</span>
                      <span>{data.weight} kg</span>
                    </div>
                  )}
                  {data.shipping_cost && (
                    <div className="flex justify-between">
                      <span className="font-medium">Κόστος:</span>
                      <span>€{data.shipping_cost}</span>
                    </div>
                  )}
                </div>
                <div className="mt-4">
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Σημειώσεις
                  </label>
                  <textarea
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    rows="3"
                    placeholder="Προαιρετικές σημειώσεις για την αποστολή"
                  />
                </div>
              </div>
            )}

            {/* Navigation Buttons */}
            <div className="flex justify-between mt-6">
              <button
                type="button"
                onClick={prevStep}
                disabled={currentStep === 1}
                className={`px-4 py-2 rounded-md ${
                  currentStep === 1 
                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                }`}
              >
                Προηγούμενο
              </button>
              
              {currentStep < totalSteps ? (
                <button
                  type="button"
                  onClick={nextStep}
                  className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                  Επόμενο
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                >
                  {processing ? 'Δημιουργία...' : 'Δημιουργία Αποστολής'}
                </button>
              )}
            </div>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}
