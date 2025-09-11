import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { useState } from 'react';

export default function CourierPerformance({ 
    stats, 
    courierStats, 
    areas, 
    selectedPeriod, 
    selectedArea, 
    periodOptions 
}) {
    const [isChangingFilters, setIsChangingFilters] = useState(false);

    const handleFilterChange = (newPeriod, newArea) => {
        if (newPeriod === selectedPeriod && newArea === selectedArea) return;
        
        setIsChangingFilters(true);
        router.get(route('courier.performance'), { 
            period: newPeriod, 
            area: newArea 
        }, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setIsChangingFilters(false)
        });
    };

    const StatCard = ({ title, value, icon, color = 'blue', subtitle = null }) => {
        const bgColorMap = {
            green: 'bg-green-100 text-green-800',
            blue: 'bg-blue-100 text-blue-800',
            yellow: 'bg-yellow-100 text-yellow-800',
            red: 'bg-red-100 text-red-800',
            gray: 'bg-gray-100 text-gray-800',
        };
    
        const iconBg = bgColorMap[color] || bgColorMap.blue;
    
        return (
            <div className="bg-white rounded-lg shadow-sm border p-6 transition-all hover:shadow-md">
                <div className="flex items-center">
                    <div className={`flex-shrink-0 w-12 h-12 ${iconBg} rounded-lg flex items-center justify-center`}>
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
    };
    

    const CourierCard = ({ courier }) => {
        const getGradeColor = (grade) => {
            const colors = {
                'A+': 'bg-green-100 text-green-800',
                'A': 'bg-green-100 text-green-800',
                'B+': 'bg-blue-100 text-blue-800',
                'B': 'bg-blue-100 text-blue-800',
                'C+': 'bg-yellow-100 text-yellow-800',
                'C': 'bg-red-100 text-red-800',
            };
            return colors[grade] || 'bg-gray-100 text-gray-800';
        };

        const getInitials = (name) => {
            return name.split(' ').map(word => word[0]).join('').toUpperCase();
        };

        return (
            <div className="bg-white rounded-lg shadow-sm border p-6 transition-all hover:shadow-md">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center">
                        <div className="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center text-white font-semibold">
                            {getInitials(courier.name)}
                        </div>
                        <div className="ml-3">
                            <h3 className="text-lg font-semibold text-gray-900">{courier.name}</h3>
                            <p className="text-sm text-gray-500">{courier.total_shipments} συνολικές αποστολές</p>
                        </div>
                    </div>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getGradeColor(courier.grade)}`}>
                        Βαθμός: {courier.grade}
                    </span>
                </div>

                {/* Performance Distribution Bar */}
                <div className="mb-4">
                    <div className="flex h-4 bg-gray-200 rounded-full overflow-hidden">
                        <div 
                            className="bg-green-500 h-full transition-all duration-300"
                            style={{ width: `${courier.delivered_percentage}%` }}
                        ></div>
                        <div 
                            className="bg-red-500 h-full transition-all duration-300"
                            style={{ width: `${courier.returned_percentage}%` }}
                        ></div>
                        <div 
                            className="bg-yellow-500 h-full transition-all duration-300"
                            style={{ width: `${courier.other_percentage}%` }}
                        ></div>
                    </div>
                    <div className="flex justify-between text-xs text-gray-600 mt-1">
                        <span>Παραδοτέα: {courier.delivered_percentage}%</span>
                        <span>Επιστραφήκαν: {courier.returned_percentage}%</span>
                        <span>Άλλα: {courier.other_percentage}%</span>
                        <span>100%</span>
                    </div>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-3 gap-4">
                    <div className="text-center">
                        <div className="flex items-center justify-center mb-1">
                            <span className="text-green-500 mr-1">↗</span>
                            <span className="text-lg font-semibold text-gray-900">{courier.delivered_percentage}%</span>
                        </div>
                        <p className="text-xs text-gray-500">Παραδοτέα</p>
                    </div>
                    <div className="text-center">
                        <div className="flex items-center justify-center mb-1">
                            <span className="text-red-500 mr-1">↘</span>
                            <span className="text-lg font-semibold text-gray-900">{courier.returned_percentage}%</span>
                        </div>
                        <p className="text-xs text-gray-500">Επιστραφήκαν</p>
                    </div>
                    <div className="text-center">
                        <div className="flex items-center justify-center mb-1">
                            <span className="text-blue-500 mr-1">⏱</span>
                            <span className="text-lg font-semibold text-gray-900">{courier.avg_delivery_time}</span>
                        </div>
                        <p className="text-xs text-gray-500">Μέσος χρόνος</p>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Απόδοση Courier" />

            <div className="py-12">
                <div className="mx-auto">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">
                            Απόδοση Courier
                        </h1>
                        <p className="text-gray-600">
                            Επισκόπηση της απόδοσης των courier ανά περιοχή και χρονικό διάστημα
                        </p>
                    </div>

                    {/* Filters */}
                    <div className="bg-white rounded-lg shadow-sm border p-6 mb-8">
                        <div className="flex flex-wrap gap-4 items-center">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Χρονικό Διάστημα
                                </label>
                                <select
                                    value={selectedPeriod}
                                    onChange={(e) => handleFilterChange(e.target.value, selectedArea)}
                                    className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    disabled={isChangingFilters}
                                >
                                    {Object.entries(periodOptions).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Περιοχή
                                </label>
                                <select
                                    value={selectedArea}
                                    onChange={(e) => handleFilterChange(selectedPeriod, e.target.value)}
                                    className="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    disabled={isChangingFilters}
                                >
                                    <option value="all">Όλες οι περιοχές</option>
                                    {areas.map((area) => (
                                        <option key={area} value={area}>{area}</option>
                                    ))}
                                </select>
                            </div>
                            {isChangingFilters && (
                                <div className="flex items-center text-sm text-gray-500">
                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500 mr-2"></div>
                                    Ενημέρωση...
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Overall Statistics */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <StatCard
                            title="Παραδοτέα"
                            value={stats.delivered_shipments}
                            subtitle={`${stats.delivered_percentage}%`}
                            icon="✅"
                            color="green"
                        />
                        <StatCard
                            title="Σε Μεταφορά"
                            value={stats.in_transit_shipments}
                            subtitle={`${stats.in_transit_percentage}%`}
                            icon="🚚"
                            color="blue"
                        />
                        <StatCard
                            title="Εκρεμούν"
                            value={stats.pending_shipments}
                            subtitle={`${stats.pending_percentage}%`}
                            icon="⏰"
                            color="yellow"
                        />
                        <StatCard
                            title="Καθυστερημένα"
                            value={stats.delayed_shipments}
                            subtitle={`${stats.delayed_percentage}%`}
                            icon="⚠️"
                            color="red"
                        />
                    </div>

                    {/* Courier Performance Metrics */}
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <div className="flex items-center mb-6">
                            <span className="text-2xl mr-3">📊</span>
                            <h2 className="text-xl font-semibold text-gray-900">
                                Μετρήσεις Απόδοσης Courier
                            </h2>
                        </div>

                        {courierStats.length > 0 ? (
                            <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                                {courierStats.map((courier) => (
                                    <CourierCard key={courier.id} courier={courier} />
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-12">
                                <div className="text-gray-400 text-6xl mb-4">📦</div>
                                <h3 className="text-lg font-medium text-gray-900 mb-2">
                                    Δεν βρέθηκαν δεδομένα
                                </h3>
                                <p className="text-gray-500">
                                    Δεν υπάρχουν αποστολές για τα επιλεγμένα φίλτρα
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 