import React from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
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
  Cell
} from 'recharts';
import {
  TrendingUp,
  Package,
  Truck,
  CheckCircle,
  AlertTriangle,
  BarChart3,
  ArrowRight
} from 'lucide-react';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];

export default function AnalyticsIndex({ analytics, filters, auth }) {
  const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
  };

  const prepareTrendData = () => {
    if (!analytics?.trends?.periods) return [];
    
    return analytics.trends.periods.map((period, index) => ({
      period,
      shipments: analytics.trends.shipments[index] || 0,
      delivered: analytics.trends.delivered[index] || 0,
    }));
  };

  const prepareCourierData = () => {
    if (!analytics?.courier?.courier_performance) return [];
    
    return analytics.courier.courier_performance.slice(0, 5).map(courier => ({
      name: courier.name,
      shipments: courier.shipment_count,
      successRate: courier.shipment_count > 0 
        ? Math.round((courier.delivered_count / courier.shipment_count) * 100)
        : 0,
    }));
  };

  return (
    <AuthenticatedLayout user={auth.user}>
      <Head title="Analytics Dashboard" />
      
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Analytics Dashboard</h1>
              <p className="text-sm text-gray-600 mt-1">
                Business intelligence and performance insights
              </p>
            </div>
            
            <div className="flex items-center space-x-4 mt-4 sm:mt-0">
              <Button
                onClick={() => window.location.href = '/analytics/advanced'}
                variant="outline"
                size="sm"
              >
                <BarChart3 className="w-4 h-4 mr-2" />
                Advanced Analytics
                <ArrowRight className="w-4 h-4 ml-2" />
              </Button>
            </div>
          </div>

          {/* Key Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Total Shipments</p>
                    <p className="text-2xl font-bold">{formatNumber(analytics?.overview?.total_shipments)}</p>
                    <div className="flex items-center mt-1">
                      <TrendingUp className="w-4 h-4 text-green-600" />
                      <span className="text-sm text-green-600 ml-1">
                        {analytics?.overview?.growth_rate > 0 ? '+' : ''}{analytics?.overview?.growth_rate}%
                      </span>
                    </div>
                  </div>
                  <Package className="w-8 h-8 text-blue-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Success Rate</p>
                    <p className="text-2xl font-bold">{analytics?.overview?.success_rate}%</p>
                    <p className="text-sm text-gray-500 mt-1">
                      {analytics?.overview?.delivered_shipments} delivered
                    </p>
                  </div>
                  <CheckCircle className="w-8 h-8 text-green-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">In Transit</p>
                    <p className="text-2xl font-bold">{analytics?.overview?.in_transit_shipments}</p>
                    <p className="text-sm text-gray-500 mt-1">
                      Active shipments
                    </p>
                  </div>
                  <Truck className="w-8 h-8 text-orange-600" />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Performance Score</p>
                    <p className="text-2xl font-bold">{analytics?.performance?.performance_score}</p>
                    <p className="text-sm text-gray-500 mt-1">
                      On-time: {analytics?.performance?.on_time_rate}%
                    </p>
                  </div>
                  <BarChart3 className="w-8 h-8 text-purple-600" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Charts */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <Card>
              <CardHeader>
                <CardTitle>Shipment Trends</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={prepareTrendData()}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="period" />
                    <YAxis />
                    <Tooltip />
                    <Line type="monotone" dataKey="shipments" stroke="#8884d8" strokeWidth={2} />
                    <Line type="monotone" dataKey="delivered" stroke="#82ca9d" strokeWidth={2} />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Courier Performance</CardTitle>
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

          {/* Quick Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Delivery Performance</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex justify-between">
                    <span>On-time Rate</span>
                    <span className="font-semibold">{analytics?.performance?.on_time_rate}%</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Avg Delivery Time</span>
                    <span className="font-semibold">{analytics?.performance?.delivery_times?.average}h</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Performance Score</span>
                    <span className="font-semibold">{analytics?.performance?.performance_score}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Alerts Summary</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex justify-between">
                    <span>Total Alerts</span>
                    <span className="font-semibold">{analytics?.alerts?.total_alerts || 0}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>High Priority</span>
                    <span className="font-semibold text-red-600">
                      {analytics?.alerts?.severity_distribution?.high || 0}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span>Avg Resolution</span>
                    <span className="font-semibold">{analytics?.alerts?.avg_resolution_time}h</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-lg">Predictive Analytics</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex justify-between">
                    <span>Model Accuracy</span>
                    <span className="font-semibold">{analytics?.predictions?.accuracy_score}%</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Total Predictions</span>
                    <span className="font-semibold">{analytics?.predictions?.model_performance?.total_predictions}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>High Confidence</span>
                    <span className="font-semibold">{analytics?.predictions?.model_performance?.high_confidence_predictions}</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
