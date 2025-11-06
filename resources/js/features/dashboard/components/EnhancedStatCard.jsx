import React from 'react';
import { TrendingUp, TrendingDown, Minus, AlertTriangle } from 'lucide-react';

export default function EnhancedStatCard({ 
  title, 
  value, 
  icon, 
  color = 'blue', 
  subtitle = null, 
  trend = null,
  trendValue = null,
  miniChart = null,
  onClick = null,
  isClickable = false,
  alertLevel = null
}) {
  const getTrendIcon = (trend) => {
    if (trend === 'up') return <TrendingUp className="w-4 h-4 text-green-500" />;
    if (trend === 'down') return <TrendingDown className="w-4 h-4 text-red-500" />;
    return <Minus className="w-4 h-4 text-gray-400" />;
  };

  const getTrendColor = (trend) => {
    if (trend === 'up') return 'text-green-600';
    if (trend === 'down') return 'text-red-600';
    return 'text-gray-500';
  };

  const getAlertBadge = (level) => {
    if (!level) return null;
    const colors = {
      critical: 'bg-red-100 text-red-800 border-red-200',
      warning: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      info: 'bg-blue-100 text-blue-800 border-blue-200'
    };
    return (
      <div className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border ${colors[level] || colors.info}`}>
        <AlertTriangle className="w-3 h-3 mr-1" />
        {level.toUpperCase()}
      </div>
    );
  };

  const colorClasses = {
    blue: 'bg-blue-100 text-blue-700',
    green: 'bg-green-100 text-green-700',
    indigo: 'bg-indigo-100 text-indigo-700',
    purple: 'bg-purple-100 text-purple-700',
    red: 'bg-red-100 text-red-700',
    yellow: 'bg-yellow-100 text-yellow-700',
    orange: 'bg-orange-100 text-orange-700'
  };

  return (
    <div 
      className={`bg-white rounded-lg shadow-sm border p-4 lg:p-6 transition-all hover:shadow-md ${
        isClickable ? 'cursor-pointer hover:border-blue-300 hover:bg-blue-50' : ''
      }`}
      onClick={onClick}
    >
      <div className="flex items-start justify-between mb-3">
        <div className={`flex-shrink-0 w-10 h-10 lg:w-12 lg:h-12 ${colorClasses[color]} rounded-lg flex items-center justify-center`}>
          <span className="text-xl lg:text-2xl">{icon}</span>
        </div>
        <div className="flex flex-col items-end space-y-1">
          {alertLevel && getAlertBadge(alertLevel)}
          {trend && (
            <div className={`flex items-center space-x-1 ${getTrendColor(trend)}`}>
              {getTrendIcon(trend)}
              <span className="text-xs font-medium">
                {trendValue ? `${trendValue}%` : ''}
              </span>
            </div>
          )}
        </div>
      </div>
      
      <div className="space-y-2">
        <h3 className="text-xs lg:text-sm font-medium text-gray-500 truncate">{title}</h3>
        <p className="text-xl lg:text-2xl font-bold text-gray-900">{value}</p>
        {subtitle && <p className="text-xs lg:text-sm text-gray-600 truncate">{subtitle}</p>}
      </div>

      {/* Mini Chart */}
      {miniChart && (
        <div className="mt-3 h-8 flex items-end space-x-1">
          {miniChart.map((point, index) => (
            <div
              key={index}
              className={`flex-1 rounded-t ${
                point > 0.7 ? 'bg-green-400' : 
                point > 0.4 ? 'bg-yellow-400' : 'bg-red-400'
              }`}
              style={{ height: `${point * 100}%` }}
            />
          ))}
        </div>
      )}

      {/* Click indicator */}
      {isClickable && (
        <div className="mt-2 text-xs text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity">
          Click to view details →
        </div>
      )}
    </div>
  );
}
