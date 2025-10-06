import React, { useState, useEffect } from 'react';
import { 
  Settings, 
  Save, 
  RotateCcw, 
  Grid, 
  List, 
  Eye, 
  EyeOff,
  Move,
  Trash2,
  Plus,
  Layout
} from 'lucide-react';

export default function CustomizableDashboard({ 
  widgets = [],
  onLayoutChange = null,
  onWidgetToggle = null,
  onWidgetReorder = null,
  onSaveLayout = null,
  onResetLayout = null,
  isEditMode = false,
  onToggleEditMode = null 
}) {
  const [layout, setLayout] = useState([]);
  const [availableWidgets, setAvailableWidgets] = useState([]);
  const [draggedWidget, setDraggedWidget] = useState(null);
  const [dragOverIndex, setDragOverIndex] = useState(null);

  useEffect(() => {
    // Initialize layout from props
    setLayout(widgets);
    
    // Define available widgets
    const defaultWidgets = [
      { id: 'stats', name: 'Στατιστικά Κάρτες', type: 'stats', size: 'large', icon: '📊' },
      { id: 'chart', name: 'Γράφημα Αποστολών', type: 'chart', size: 'medium', icon: '📈' },
      { id: 'recent', name: 'Πρόσφατες Αποστολές', type: 'recent', size: 'medium', icon: '🚢' },
      { id: 'couriers', name: 'Απόδοση Courier', type: 'couriers', size: 'medium', icon: '🚚' },
      { id: 'alerts', name: 'Ειδοποιήσεις', type: 'alerts', size: 'small', icon: '🔔' },
      { id: 'activity', name: 'Δραστηριότητα', type: 'activity', size: 'small', icon: '⚡' },
      { id: 'performance', name: 'Απόδοση', type: 'performance', size: 'small', icon: '🎯' },
      { id: 'summary', name: 'Σύνοψη', type: 'summary', size: 'large', icon: '📋' }
    ];
    
    setAvailableWidgets(defaultWidgets);
  }, [widgets]);

  const handleDragStart = (e, widget) => {
    setDraggedWidget(widget);
    e.dataTransfer.effectAllowed = 'move';
  };

  const handleDragOver = (e, index) => {
    e.preventDefault();
    setDragOverIndex(index);
  };

  const handleDrop = (e, targetIndex) => {
    e.preventDefault();
    
    if (draggedWidget && onWidgetReorder) {
      const newLayout = [...layout];
      const draggedIndex = newLayout.findIndex(w => w.id === draggedWidget.id);
      
      if (draggedIndex !== -1) {
        // Remove from original position
        const [movedWidget] = newLayout.splice(draggedIndex, 1);
        // Insert at new position
        newLayout.splice(targetIndex, 0, movedWidget);
        
        setLayout(newLayout);
        onWidgetReorder(newLayout);
      }
    }
    
    setDraggedWidget(null);
    setDragOverIndex(null);
  };

  const handleAddWidget = (widget) => {
    const newWidget = {
      ...widget,
      visible: true,
      position: layout.length
    };
    
    const newLayout = [...layout, newWidget];
    setLayout(newLayout);
    
    if (onLayoutChange) {
      onLayoutChange(newLayout);
    }
  };

  const handleRemoveWidget = (widgetId) => {
    const newLayout = layout.filter(w => w.id !== widgetId);
    setLayout(newLayout);
    
    if (onLayoutChange) {
      onLayoutChange(newLayout);
    }
  };

  const handleToggleWidget = (widgetId) => {
    const newLayout = layout.map(w => 
      w.id === widgetId ? { ...w, visible: !w.visible } : w
    );
    setLayout(newLayout);
    
    if (onWidgetToggle) {
      onWidgetToggle(widgetId, !layout.find(w => w.id === widgetId)?.visible);
    }
  };

  const handleSaveLayout = () => {
    if (onSaveLayout) {
      onSaveLayout(layout);
    }
  };

  const handleResetLayout = () => {
    if (onResetLayout) {
      onResetLayout();
    }
  };

  const getWidgetSizeClass = (size) => {
    const classes = {
      small: 'col-span-1',
      medium: 'col-span-2',
      large: 'col-span-3',
      xlarge: 'col-span-4'
    };
    return classes[size] || classes.medium;
  };

  const getWidgetIcon = (type) => {
    const icons = {
      stats: '📊',
      chart: '📈',
      recent: '🚢',
      couriers: '🚚',
      alerts: '🔔',
      activity: '⚡',
      performance: '🎯',
      summary: '📋'
    };
    return icons[type] || '📦';
  };

  return (
    <div className="space-y-6">
      {/* Dashboard Controls */}
      <div className="flex items-center justify-between bg-white p-4 rounded-lg border border-gray-200">
        <div className="flex items-center space-x-4">
          <h2 className="text-lg font-semibold text-gray-900">Προσαρμοσμένος Πίνακας</h2>
          <div className="flex items-center space-x-2">
            <button
              onClick={onToggleEditMode}
              className={`px-3 py-1 text-sm rounded-md transition-colors ${
                isEditMode 
                  ? 'bg-blue-600 text-white' 
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              <Settings className="w-4 h-4 mr-1" />
              {isEditMode ? 'Εξεργασία' : 'Επεξεργασία'}
            </button>
          </div>
        </div>

        <div className="flex items-center space-x-2">
          <button
            onClick={handleSaveLayout}
            className="px-3 py-1 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center"
          >
            <Save className="w-4 h-4 mr-1" />
            Αποθήκευση
          </button>
          <button
            onClick={handleResetLayout}
            className="px-3 py-1 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors flex items-center"
          >
            <RotateCcw className="w-4 h-4 mr-1" />
            Επαναφορά
          </button>
        </div>
      </div>

      {/* Edit Mode - Widget Library */}
      {isEditMode && (
        <div className="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <h3 className="text-md font-semibold text-gray-900 mb-4">Διαθέσιμα Widgets</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {availableWidgets.map((widget) => {
              const isAdded = layout.some(w => w.id === widget.id);
              return (
                <div
                  key={widget.id}
                  className={`p-3 border rounded-lg cursor-pointer transition-colors ${
                    isAdded 
                      ? 'bg-green-50 border-green-200 text-green-800' 
                      : 'bg-white border-gray-200 hover:bg-gray-50'
                  }`}
                  onClick={() => !isAdded && handleAddWidget(widget)}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center">
                      <span className="text-lg mr-2">{widget.icon}</span>
                      <span className="text-sm font-medium">{widget.name}</span>
                    </div>
                    {isAdded && (
                      <CheckCircle className="w-4 h-4 text-green-600" />
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Dashboard Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {layout.map((widget, index) => (
          <div
            key={widget.id}
            className={`${getWidgetSizeClass(widget.size)} ${
              isEditMode ? 'relative group' : ''
            }`}
            draggable={isEditMode}
            onDragStart={(e) => handleDragStart(e, widget)}
            onDragOver={(e) => handleDragOver(e, index)}
            onDrop={(e) => handleDrop(e, index)}
          >
            <div className={`bg-white rounded-lg border border-gray-200 p-4 h-full ${
              isEditMode ? 'border-dashed border-blue-300' : ''
            } ${dragOverIndex === index ? 'border-blue-500 bg-blue-50' : ''}`}>
              
              {/* Widget Header */}
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center">
                  <span className="text-lg mr-2">{getWidgetIcon(widget.type)}</span>
                  <h3 className="text-sm font-medium text-gray-900">{widget.name}</h3>
                </div>
                
                {isEditMode && (
                  <div className="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                      onClick={() => handleToggleWidget(widget.id)}
                      className="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      {widget.visible ? <Eye className="w-4 h-4" /> : <EyeOff className="w-4 h-4" />}
                    </button>
                    <button
                      onClick={() => handleRemoveWidget(widget.id)}
                      className="p-1 text-gray-400 hover:text-red-600 transition-colors"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                    <div className="p-1 text-gray-400 cursor-move">
                      <Move className="w-4 h-4" />
                    </div>
                  </div>
                )}
              </div>

              {/* Widget Content */}
              <div className={`${!widget.visible ? 'opacity-50' : ''}`}>
                {widget.type === 'stats' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">📊</div>
                    <p className="text-sm text-gray-500">Στατιστικά Κάρτες</p>
                  </div>
                )}
                
                {widget.type === 'chart' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">📈</div>
                    <p className="text-sm text-gray-500">Γράφημα Αποστολών</p>
                  </div>
                )}
                
                {widget.type === 'recent' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">🚢</div>
                    <p className="text-sm text-gray-500">Πρόσφατες Αποστολές</p>
                  </div>
                )}
                
                {widget.type === 'couriers' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">🚚</div>
                    <p className="text-sm text-gray-500">Απόδοση Courier</p>
                  </div>
                )}
                
                {widget.type === 'alerts' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">🔔</div>
                    <p className="text-sm text-gray-500">Ειδοποιήσεις</p>
                  </div>
                )}
                
                {widget.type === 'activity' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">⚡</div>
                    <p className="text-sm text-gray-500">Δραστηριότητα</p>
                  </div>
                )}
                
                {widget.type === 'performance' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">🎯</div>
                    <p className="text-sm text-gray-500">Απόδοση</p>
                  </div>
                )}
                
                {widget.type === 'summary' && (
                  <div className="text-center py-8">
                    <div className="text-4xl mb-2">📋</div>
                    <p className="text-sm text-gray-500">Σύνοψη</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Empty State */}
      {layout.length === 0 && (
        <div className="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
          <Layout className="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 mb-2">Κανένα Widget</h3>
          <p className="text-gray-500 mb-4">Προσθέστε widgets για να δημιουργήσετε τον πίνακα ελέγχου σας</p>
          {isEditMode && (
            <button
              onClick={() => handleAddWidget(availableWidgets[0])}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center mx-auto"
            >
              <Plus className="w-4 h-4 mr-2" />
              Προσθήκη Widget
            </button>
          )}
        </div>
      )}
    </div>
  );
}
