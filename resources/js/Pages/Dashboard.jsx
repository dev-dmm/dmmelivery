// resources/js/Pages/Dashboard.jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/dist/style.css'; // Optional; Tailwind styles below override most

import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend,
} from 'chart.js';
import { Bar } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

// Helper functions for date handling
const toLocalISO = (d) => {
  // returns YYYY-MM-DDTHH:mm:ss in local time (no timezone Z)
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0);
const endOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59);

export default function Dashboard(props) {
  const {
    stats,
    recentShipments,
    selectedPeriod,
    periodOptions,
    tenant,
    weeklyStats,           // LAZY (null on first render)
    chartData,             // LAZY
    courierStats,          // LAZY
    recentNotifications,   // LAZY
    customStart,
    customEnd,
  } = props;

  // Get shared tenant from page props as fallback
  const page = usePage();
  const sharedTenant = page.props.tenant;
  const effectiveTenantName = tenant?.name ?? sharedTenant?.name ?? 'Your eShop';

  // Debug logging
  console.log('Dashboard props:', { tenant, sharedTenant, effectiveTenantName });
  console.log('Full page props:', page.props);

  const [isChangingPeriod, setIsChangingPeriod] = useState(false);
  
  // Date picker state
  const [isPickerOpen, setIsPickerOpen] = useState(false);
  const [range, setRange] = useState({});
  const [startTime, setStartTime] = useState('00:00');
  const [endTime, setEndTime] = useState('23:59');

  // ---------- On first mount, fetch lazy props
  useEffect(() => {
    router.reload({ only: ['weeklyStats','chartData','courierStats','recentNotifications'] });
  }, []);

  // ---------- Helpers
  const getStatusBadgeColor = (status) => {
    const colors = {
      pending: 'bg-yellow-100 text-yellow-800',
      picked_up: 'bg-blue-100 text-blue-800',
      in_transit: 'bg-indigo-100 text-indigo-800',
      out_for_delivery: 'bg-purple-100 text-purple-800',
      delivered: 'bg-green-100 text-green-800',
      failed: 'bg-red-100 text-red-800',
      returned: 'bg-gray-100 text-gray-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      title: { display: false },
      tooltip: {
        backgroundColor: 'rgba(0,0,0,0.8)',
        titleColor: 'white',
        bodyColor: 'white',
        borderColor: 'rgba(255,255,255,0.1)',
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: true,
        callbacks: {
          label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y} αποστολές`,
        },
      },
    },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1, color: '#6B7280' }, grid: { color: '#F3F4F6' } },
      x: { ticks: { color: '#6B7280', font: { size: 12 } }, grid: { display: false } },
    },
  };

  const chartDataConfig = chartData ? {
    labels: chartData.labels,
    datasets: [{
      label: 'Αποστολές',
      data: chartData.data,
      backgroundColor: chartData.colors,
      borderColor: (chartData.colors || []).map((c) => `${c}CC`),
      borderWidth: 1,
      borderRadius: 4,
    }],
  } : null;

  // ---------- Period change with partial reload
  const handlePeriodChange = (newPeriod) => {
    if (newPeriod === selectedPeriod) return;
    setIsChangingPeriod(true);

    router.get(
      route('dashboard'),
      { period: newPeriod },
      {
        preserveState: true,
        preserveScroll: true,
        only: ['stats','recentShipments','weeklyStats','chartData','courierStats','recentNotifications','selectedPeriod'],
        onFinish: () => setIsChangingPeriod(false),
      }
    );
  };

  // Date picker handlers
  const applyCustomRange = () => {
    if (!range.from || !range.to) return;

    // Build start/end with chosen times in local time
    const [sh, sm] = startTime.split(':').map(Number);
    const [eh, em] = endTime.split(':').map(Number);

    const start = new Date(range.from);
    start.setHours(sh ?? 0, sm ?? 0, 0, 0);

    const end = new Date(range.to);
    end.setHours(eh ?? 23, em ?? 59, 59, 999);

    setIsChangingPeriod(true);
    setIsPickerOpen(false);

    router.get(
      route('dashboard'),
      {
        period: 'custom',
        start: toLocalISO(start),
        end: toLocalISO(end),
      },
      {
        preserveState: true,
        preserveScroll: true,
        only: ['stats','recentShipments','weeklyStats','chartData','courierStats','recentNotifications','selectedPeriod'],
        onFinish: () => setIsChangingPeriod(false),
      }
    );
  };

  const clearRange = () => {
    setRange({});
    setStartTime('00:00');
    setEndTime('23:59');
  };

  const prettyRange = () => {
    if (!range.from || !range.to) return 'Επιλογή ημερομηνιών';
    const opts = { day: '2-digit', month: 'short', year: 'numeric' };
    return `${range.from.toLocaleDateString('el-GR', opts)} → ${range.to.toLocaleDateString('el-GR', opts)}`;
  };

  return (
    <AuthenticatedLayout>
      <Head title="Dashboard" />

      <div className="py-6 space-y-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-gray-800">📊 Dashboard - {effectiveTenantName}</h1>
            <p className="text-sm text-gray-500 mt-1">Παρακολουθήστε την απόδοση των αποστολών και τις ειδοποιήσεις πελατών</p>
          </div>

          <div className="flex items-end space-x-4">
            {/* Period selector */}
            <div className="relative">
              <label className="block text-sm font-medium text-gray-700 mb-1">📅 Χρονική Περίοδος</label>
              <select
                value={selectedPeriod}
                onChange={(e) => handlePeriodChange(e.target.value)}
                disabled={isChangingPeriod}
                className="block w-48 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm bg-white disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {Object.entries(periodOptions).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              {isChangingPeriod && (
                <div className="absolute right-2 top-9 transform -translate-y-1/2">
                  <svg className="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                </div>
              )}
            </div>

            {/* Custom Range Picker */}
            <div className="relative">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                🗓️ Προσαρμοσμένη Περίοδος
              </label>

              <button
                type="button"
                onClick={() => setIsPickerOpen((v) => !v)}
                className="w-64 inline-flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm hover:bg-gray-50"
              >
                <span className="truncate">{prettyRange()}</span>
                <svg className={`w-4 h-4 transition-transform ${isPickerOpen ? 'rotate-180' : ''}`} viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clipRule="evenodd" />
                </svg>
              </button>

              {isPickerOpen && (
                <div className="absolute right-0 z-20 mt-2 w-[620px] bg-white border rounded-lg shadow-lg p-4">
                  <div className="flex flex-col lg:flex-row gap-4">
                    {/* Calendar */}
                    <div className="border rounded-md p-2">
                      <DayPicker
                        mode="range"
                        numberOfMonths={2}
                        selected={range}
                        onSelect={setRange}
                        weekStartsOn={1}
                        disabled={{ after: new Date() /* remove if future allowed */ }}
                        modifiersClassNames={{
                          selected: 'bg-blue-600 text-white',
                          range_start: 'bg-blue-600 text-white',
                          range_end: 'bg-blue-600 text-white',
                          range_middle: 'bg-blue-100',
                          today: 'ring-1 ring-blue-500',
                        }}
                        className="rdp !text-sm"
                      />
                    </div>

                    {/* Time + Actions */}
                    <div className="flex-1 flex flex-col justify-between">
                      <div className="grid grid-cols-1 gap-3">
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Ώρα Έναρξης</label>
                          <input
                            type="time"
                            value={startTime}
                            onChange={(e) => setStartTime(e.target.value)}
                            className="w-full border rounded-md px-2 py-2 text-sm"
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Ώρα Λήξης</label>
                          <input
                            type="time"
                            value={endTime}
                            onChange={(e) => setEndTime(e.target.value)}
                            className="w-full border rounded-md px-2 py-2 text-sm"
                          />
                        </div>
                      </div>

                      <div className="mt-4 flex items-center justify-end gap-2">
                        <button
                          type="button"
                          onClick={clearRange}
                          className="px-3 py-2 text-sm rounded-md border bg-white hover:bg-gray-50"
                        >
                          Καθαρισμός
                        </button>
                        <button
                          type="button"
                          disabled={!range.from || !range.to}
                          onClick={applyCustomRange}
                          className="px-4 py-2 text-sm rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
                        >
                          Εφαρμογή
                        </button>
                      </div>
                    </div>
                  </div>

                  {/* Tiny pointer */}
                  <div className="absolute -top-2 right-6 w-3 h-3 rotate-45 bg-white border-l border-t" />
                </div>
              )}
            </div>

            <Link
              href={route('shipments.index')}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 transition-colors"
            >
              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              Προβολή Όλων
            </Link>
          </div>
        </div>

        {/* KPI cards (EAGER) */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatCard title="Συνολικές Αποστολές" value={stats.total_shipments} icon="📦" color="blue" subtitle="για την επιλεγμένη περίοδο" />
          <StatCard title="Παραδοτέα" value={stats.delivered_shipments} icon="✅" color="green" subtitle={`${stats.delivery_success_rate}% επιτυχία`} />
          <StatCard title="Σε Μεταφορά" value={stats.in_transit_shipments + stats.out_for_delivery_shipments} icon="🚚" color="indigo" subtitle="Αποστολές σε εξέλιξη" />
          <StatCard title="Ενεργοί Courier" value={stats.total_couriers} icon="🏢" color="purple" subtitle={`${stats.total_customers} πελάτες`} />
        </div>

        {/* Charts + Activity */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-lg font-semibold text-gray-900">📊 Κατάσταση Αποστολών</h3>
              <div className="text-sm text-gray-500">{periodOptions[selectedPeriod]}</div>
            </div>
            <div style={{ height: '300px' }}>
              {chartDataConfig
                ? <Bar data={chartDataConfig} options={chartOptions} />
                : <div className="flex items-center justify-center h-full"><div className="text-gray-500">Φόρτωση γραφήματος…</div></div>}
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-sm border p-6">
            <h3 className="text-lg font-semibold mb-4">📈 Δραστηριότητα Περιόδου</h3>
            <div className="space-y-3 max-h-80 overflow-y-auto">
              {weeklyStats && weeklyStats.length
                ? weeklyStats.map((day, idx) => (
                    <div key={idx} className="flex items-center justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                      <span className="text-sm font-medium text-gray-600">{day.date}</span>
                      <div className="flex items-center space-x-4">
                        <div className="flex items-center">
                          <div className="w-3 h-3 bg-blue-400 rounded-full mr-2"></div>
                          <span className="text-sm text-gray-600">{day.shipments} αποστολές</span>
                        </div>
                        <div className="flex items-center">
                          <div className="w-3 h-3 bg-green-400 rounded-full mr-2"></div>
                          <span className="text-sm text-gray-600">{day.delivered} παραδοτέα</span>
                        </div>
                      </div>
                    </div>
                  ))
                : <p className="text-gray-500 text-center py-8">Δεν υπάρχουν δεδομένα δραστηριότητας</p>}
            </div>
          </div>
        </div>

        {/* Recent Shipments (EAGER) & Courier performance (LAZY) */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <div className="bg-white rounded-lg shadow-sm border">
            <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
              <h3 className="text-lg font-semibold">🚢 Πρόσφατες Αποστολές</h3>
              <Link href={route('shipments.index')} className="text-blue-600 hover:text-blue-800 text-sm font-medium">
                Προβολή όλων →
              </Link>
            </div>
            <div className="p-6">
              <div className="space-y-4">
                {recentShipments?.length
                  ? recentShipments.slice(0, 5).map((s) => (
                      <div key={s.id} className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div className="flex items-center">
                          <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                            <span className="text-xs font-mono">{s.tracking_number.slice(-4)}</span>
                          </div>
                          <div>
                            <p className="font-medium text-gray-900">{s.tracking_number}</p>
                            <p className="text-sm text-gray-500">{s.customer?.name || 'Άγνωστος πελάτης'}</p>
                          </div>
                        </div>
                        <div className="flex items-center space-x-3">
                          <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(s.status)}`}>
                            {s.status.replace('_', ' ').toUpperCase()}
                          </span>
                          <Link href={route('shipments.show', { shipment: s.id })} className="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Προβολή
                          </Link>
                        </div>
                      </div>
                    ))
                  : <p className="text-gray-500 text-center py-8">Δεν υπάρχουν πρόσφατες αποστολές</p>}
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-sm border">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold">🚚 Απόδοση Courier</h3>
            </div>
            <div className="p-6">
              <div className="space-y-4">
                {courierStats?.length
                  ? courierStats.slice(0, 4).map((c, i) => (
                      <div key={i} className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div className="flex items-center">
                          <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <span className="text-sm font-bold text-blue-700">{c.code}</span>
                          </div>
                          <div>
                            <p className="font-medium text-gray-900">{c.name}</p>
                            <p className="text-sm text-gray-500">Σύνολο: <span className="font-medium">{c.total_shipments}</span> αποστολές</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="space-y-1">
                            <p className="text-sm text-green-600">✅ <span className="font-semibold">{c.delivered_shipments}</span> παραδοτέα</p>
                            <p className="text-sm text-yellow-600">⏳ <span className="font-semibold">{c.pending_shipments}</span> εκρεμότητα</p>
                            {c.failed_shipments > 0 && (
                              <p className="text-sm text-red-600">❌ <span className="font-semibold">{c.failed_shipments}</span> αποτυχημένα</p>
                            )}
                          </div>
                        </div>
                      </div>
                    ))
                  : <p className="text-gray-500 text-center py-8">Φόρτωση δεδομένων courier…</p>}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

function StatCard({ title, value, icon, color = 'blue', subtitle = null }) {
  return (
    <div className="bg-white rounded-lg shadow-sm border p-6 transition-all hover:shadow-md">
      <div className="flex items-center">
        <div className={`flex-shrink-0 w-12 h-12 bg-${color}-100 rounded-lg flex items-center justify-center`}>
          <span className="text-2xl">{icon}</span>
        </div>
        <div className="ml-4 flex-1">
          <h3 className="text-sm font-medium text-gray-500">{title}</h3>
          <p className="text-2xl font-bold text-gray-900">{value}</p>
          {subtitle && <p className="text-sm text-gray-600">{subtitle}</p>}
        </div>
      </div>
    </div>
  );
}
