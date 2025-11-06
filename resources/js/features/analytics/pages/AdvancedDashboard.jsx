import React, { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/Tabs';
import { 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
  AreaChart,
  Area
} from 'recharts';
import {
  TrendingUp,
  TrendingDown,
  Activity,
  Users,
  Truck,
  AlertTriangle,
  MapPin,
  BarChart3,
  Download,
  RefreshCw,
  Calendar,
  Filter
} from 'lucide-react';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

export default function AdvancedDashboard({ analytics, filters }) {
  const [data, setData] = useState(analytics || {});
  const [loading, setLoading] = useState(false);
  const [dateRange, setDateRange] = useState(filters?.period || '30d');
  const { auth } = usePage().props;

  const fetchAnalytics = async (newFilters = {}) => {
    setLoading(true);
    try {
      const response = await fetch('/api/analytics/dashboard', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        },
        body: JSON.stringify(newFilters),
      });
      
      const result = await response.json();
      if (result.success) {
        setData(result.data);
      }
    } catch (error) {
      console.error('Failed to fetch analytics:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAnalytics({ period: dateRange });
  }, [dateRange]);

  const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
  };

  const getTrendIcon = (direction) => {
    switch (direction) {
      case 'increasing': return <TrendingUp className="w-4 h-4 text-green-600" />;
      case 'decreasing': return <TrendingDown className="w-4 h-4 text-red-600" />;
      default: return <Activity className="w-4 h-4 text-gray-600" />;
    }
  };

  const getTrendColor = (direction) => {
    switch (direction) {
      case 'increasing': return 'text-green-600';
      case 'decreasing': return 'text-red-600';
      default: return 'text-gray-600';
    }
  };

  const prepareTrendData = () => {
    if (!data.trends?.periods) return [];
    
    return data.trends.periods.map((period, index) => ({
      period,
      shipments: data.trends.shipments[index] || 0,
      delivered: data.trends.delivered[index] || 0,
      failed: data.trends.failed[index] || 0,
    }));
  };

  const prepareCourierData = () => {
    if (!data.courier?.courier_performance) return [];
    
    return data.courier.courier_performance.slice(0, 5).map(courier => ({
      name: courier.name,
      shipments: courier.shipment_count,
      delivered: courier.delivered_count,
      successRate: courier.shipment_count > 0 
        ? Math.round((courier.delivered_count / courier.shipment_count) * 100)
        : 0,
    }));
  };

  const prepareGeographicData = () => {
    if (!data.geographic?.top_destinations) return [];
    
    return data.geographic.top_destinations.slice(0, 5).map(dest => ({
      location: dest.shipping_city || dest.shipping_address?.split(',')[0] || 'Unknown',
      shipments: dest.shipment_count,
      successRate: dest.shipment_count > 0 
        ? Math.round((dest.delivered_count / dest.shipment_count) * 100)
        : 0,
    }));
  };

  return (
    <AuthenticatedLayout user={auth.user}>
      <Head title="Προηγμένα Αναλυτικά" />
      
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Προηγμένα Αναλυτικά</h1>
              <p className="text-sm text-gray-600 mt-1">
                Περιεκτική επιχειρηματική νοημοσύνη και πληροφορίες απόδοσης
              </p>
            </div>
            
            <div className="flex items-center space-x-4 mt-4 sm:mt-0">
              <select
                value={dateRange}
                onChange={(e) => setDateRange(e.target.value)}
                className="px-3 py-2 border border-gray-300 rounded-md text-sm"
              >
                <option value="7d">Τελευταίες 7 ημέρες</option>
                <option value="30d">Τελευταίες 30 ημέρες</option>
                <option value="90d">Τελευταίες 90 ημέρες</option>
                <option value="1y">Τελευταίο έτος</option>
              </select>
              
              <Button
                onClick={() => fetchAnalytics({ period: dateRange })}
                disabled={loading}
                variant="outline"
                size="sm"
              >
                <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                Ανανέωση
              </Button>
              
              <Button variant="outline" size="sm">
                <Download className="w-4 h-4 mr-2" />
                Εξαγωγή
              </Button>
            </div>
          </div>

          {/* Key Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Σύνολο Αποστολών</p>
                    <p className="text-2xl font-bold">{formatNumber(data.overview?.total_shipments)}</p>
                    <div className="flex items-center mt-1">
                      {getTrendIcon(data.trends?.trend_direction)}
                      <span className={`text-sm ml-1 ${getTrendColor(data.trends?.trend_direction)}`}>
                        {data.overview?.growth_rate > 0 ? '+' : ''}{data.overview?.growth_rate}%
                      </span>
                    </div>
                  </div>
                  <BarChart3 className="w-8 h-8 text-blue-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Ποσοστό Επιτυχίας</p>
                    <p className="text-2xl font-bold">{data.overview?.success_rate}%</p>
                    <div className="flex items-center mt-1">
                      <Activity className="w-4 h-4 text-green-600" />
                      <span className="text-sm ml-1 text-green-600">
                        {data.overview?.delivery_growth_rate > 0 ? '+' : ''}{data.overview?.delivery_growth_rate}%
                      </span>
                    </div>
                  </div>
                  <TrendingUp className="w-8 h-8 text-green-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Βαθμολογία Απόδοσης</p>
                    <p className="text-2xl font-bold">{data.performance?.performance_score}</p>
                    <p className="text-sm text-gray-500 mt-1">
                      Μέση καθυστέρηση: {data.overview?.avg_delay_hours}ω
                    </p>
                  </div>
                  <Activity className="w-8 h-8 text-purple-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Ενεργές Ειδοποιήσεις</p>
                    <p className="text-2xl font-bold">{data.alerts?.total_alerts || 0}</p>
                    <p className="text-sm text-gray-500 mt-1">
                      {data.alerts?.severity_distribution?.high || 0} υψηλή προτεραιότητα
                    </p>
                  </div>
                  <AlertTriangle className="w-8 h-8 text-orange-600" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Analytics Tabs */}
          <Tabs defaultValue="overview" className="space-y-6">
            <TabsList className="grid w-full grid-cols-6">
              <TabsTrigger value="overview">Επισκόπηση</TabsTrigger>
              <TabsTrigger value="trends">Τάσεις</TabsTrigger>
              <TabsTrigger value="performance">Απόδοση</TabsTrigger>
              <TabsTrigger value="geographic">Γεωγραφικά</TabsTrigger>
              <TabsTrigger value="customers">Πελάτες</TabsTrigger>
              <TabsTrigger value="predictions">Προβλέψεις</TabsTrigger>
            </TabsList>

            <TabsContent value="overview" className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Τάσεις Αποστολών</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <ResponsiveContainer width="100%" height={300}>
                      <AreaChart data={prepareTrendData()}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="period" />
                        <YAxis />
                        <Tooltip />
                        <Area type="monotone" dataKey="shipments" stackId="1" stroke="#8884d8" fill="#8884d8" />
                        <Area type="monotone" dataKey="delivered" stackId="1" stroke="#82ca9d" fill="#82ca9d" />
                      </AreaChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Απόδοση Μεταφορέων</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <ResponsiveContainer width="100%" height={300}>
                      <BarChart data={prepareCourierData()}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="name" />
                        <YAxis />
                        <Tooltip />
                        <Bar dataKey="successRate" fill="#8884d8" />
                      </BarChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="trends" className="space-y-6">
              <Card>
                <CardHeader>
                  <CardTitle>Τάσεις Όγκου Αποστολών</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={400}>
                    <LineChart data={prepareTrendData()}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="period" />
                      <YAxis />
                      <Tooltip />
                      <Line type="monotone" dataKey="shipments" stroke="#8884d8" strokeWidth={2} />
                      <Line type="monotone" dataKey="delivered" stroke="#82ca9d" strokeWidth={2} />
                      <Line type="monotone" dataKey="failed" stroke="#ff7300" strokeWidth={2} />
                    </LineChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="performance" className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                  <CardHeader>
                    <CardTitle>Απόδοση Παράδοσης</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-4">
                      <div className="flex justify-between">
                        <span>Ποσοστό Εγκαίρων</span>
                        <span className="font-semibold">{data.performance?.on_time_rate}%</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Μέσος Χρόνος Παράδοσης</span>
                        <span className="font-semibold">{data.performance?.delivery_times?.average}h</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Βαθμολογία Απόδοσης</span>
                        <span className="font-semibold">{data.performance?.performance_score}</span>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Κορυφαίοι Μεταφορείς</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-3">
                      {prepareCourierData().map((courier, index) => (
                        <div key={index} className="flex items-center justify-between">
                          <span className="text-sm">{courier.name}</span>
                          <Badge variant="outline">{courier.successRate}%</Badge>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              </div>
            </TabsContent>

            <TabsContent value="geographic" className="space-y-6">
              <Card>
                <CardHeader>
                  <CardTitle>Γεωγραφική Κατανομή</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={prepareGeographicData()}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="location" />
                      <YAxis />
                      <Tooltip />
                      <Bar dataKey="shipments" fill="#8884d8" />
                    </BarChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="customers" className="space-y-6">
              <Card>
                <CardHeader>
                  <CardTitle>Αναλυτικά Πελατών</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div className="flex justify-between">
                      <span>Διατήρηση Πελατών</span>
                      <span className="font-semibold">{data.customer?.customer_retention}%</span>
                    </div>
                    <div className="space-y-3">
                      <h4 className="font-medium">Κορυφαίοι Πελάτες</h4>
                      {data.customer?.top_customers?.slice(0, 5).map((customer, index) => (
                        <div key={index} className="flex items-center justify-between p-2 bg-gray-50 rounded">
                          <span className="text-sm">{customer.name}</span>
                          <span className="text-sm font-medium">{customer.shipment_count} αποστολές</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="predictions" className="space-y-6">
              <Card>
                <CardHeader>
                  <CardTitle>Προγνωστικά Αναλυτικά</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div className="flex justify-between">
                      <span>Ακρίβεια Μοντέλου</span>
                      <span className="font-semibold">{data.predictions?.accuracy_score}%</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Σύνολο Προβλέψεων</span>
                      <span className="font-semibold">{data.predictions?.model_performance?.total_predictions}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Υψηλή Εμπιστοσύνη</span>
                      <span className="font-semibold">{data.predictions?.model_performance?.high_confidence_predictions}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
