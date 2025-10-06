import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { 
  ChevronLeft, 
  ChevronRight, 
  Search, 
  Filter, 
  Download,
  Loader,
  AlertCircle,
  CheckCircle
} from 'lucide-react';

export default function PerformanceOptimizedList({ 
  data = [],
  itemsPerPage = 20,
  onLoadMore = null,
  onSearch = null,
  onFilter = null,
  onExport = null,
  renderItem = null,
  searchFields = [],
  filterOptions = [],
  loading = false,
  hasMore = false,
  totalCount = 0,
  className = ''
}) {
  const [currentPage, setCurrentPage] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [filters, setFilters] = useState({});
  const [sortBy, setSortBy] = useState('');
  const [sortOrder, setSortOrder] = useState('asc');
  const [selectedItems, setSelectedItems] = useState(new Set());

  // Memoized filtered and sorted data
  const processedData = useMemo(() => {
    let filtered = [...data];

    // Apply search
    if (searchTerm && searchFields.length > 0) {
      filtered = filtered.filter(item => 
        searchFields.some(field => {
          const value = getNestedValue(item, field);
          return value && value.toString().toLowerCase().includes(searchTerm.toLowerCase());
        })
      );
    }

    // Apply filters
    Object.entries(filters).forEach(([key, value]) => {
      if (value && value !== 'all') {
        filtered = filtered.filter(item => {
          const itemValue = getNestedValue(item, key);
          return itemValue === value || (Array.isArray(value) && value.includes(itemValue));
        });
      }
    });

    // Apply sorting
    if (sortBy) {
      filtered.sort((a, b) => {
        const aValue = getNestedValue(a, sortBy);
        const bValue = getNestedValue(b, sortBy);
        
        if (sortOrder === 'asc') {
          return aValue > bValue ? 1 : -1;
        } else {
          return aValue < bValue ? 1 : -1;
        }
      });
    }

    return filtered;
  }, [data, searchTerm, filters, sortBy, sortOrder, searchFields]);

  // Paginated data
  const paginatedData = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return processedData.slice(startIndex, startIndex + itemsPerPage);
  }, [processedData, currentPage, itemsPerPage]);

  const totalPages = Math.ceil(processedData.length / itemsPerPage);

  // Handlers
  const handleSearch = useCallback((term) => {
    setSearchTerm(term);
    setCurrentPage(1);
    if (onSearch) {
      onSearch(term);
    }
  }, [onSearch]);

  const handleFilter = useCallback((key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setCurrentPage(1);
    if (onFilter) {
      onFilter({ ...filters, [key]: value });
    }
  }, [filters, onFilter]);

  const handleSort = useCallback((field) => {
    if (sortBy === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortBy(field);
      setSortOrder('asc');
    }
  }, [sortBy, sortOrder]);

  const handleSelectItem = useCallback((itemId) => {
    setSelectedItems(prev => {
      const newSet = new Set(prev);
      if (newSet.has(itemId)) {
        newSet.delete(itemId);
      } else {
        newSet.add(itemId);
      }
      return newSet;
    });
  }, []);

  const handleSelectAll = useCallback(() => {
    if (selectedItems.size === paginatedData.length) {
      setSelectedItems(new Set());
    } else {
      setSelectedItems(new Set(paginatedData.map(item => item.id)));
    }
  }, [selectedItems.size, paginatedData]);

  const handleExport = useCallback(() => {
    if (onExport) {
      const exportData = selectedItems.size > 0 
        ? data.filter(item => selectedItems.has(item.id))
        : processedData;
      onExport(exportData);
    }
  }, [onExport, selectedItems, data, processedData]);

  // Load more data when scrolling to bottom
  useEffect(() => {
    const handleScroll = () => {
      if (window.innerHeight + document.documentElement.scrollTop >= document.documentElement.offsetHeight - 1000) {
        if (hasMore && !loading && onLoadMore) {
          onLoadMore();
        }
      }
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [hasMore, loading, onLoadMore]);

  // Helper function to get nested object values
  const getNestedValue = (obj, path) => {
    return path.split('.').reduce((current, key) => current?.[key], obj);
  };

  return (
    <div className={`space-y-4 ${className}`}>
      {/* Search and Filters */}
      <div className="bg-white p-4 rounded-lg border border-gray-200">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => handleSearch(e.target.value)}
                placeholder="Αναζήτηση..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {/* Filters */}
          {filterOptions.map((filter) => (
            <div key={filter.key} className="lg:w-48">
              <select
                value={filters[filter.key] || 'all'}
                onChange={(e) => handleFilter(filter.key, e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">{filter.label}</option>
                {filter.options.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          ))}

          {/* Actions */}
          <div className="flex items-center space-x-2">
            <button
              onClick={handleExport}
              className="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center"
            >
              <Download className="w-4 h-4 mr-1" />
              Εξαγωγή
            </button>
          </div>
        </div>
      </div>

      {/* Results Summary */}
      <div className="flex items-center justify-between text-sm text-gray-600">
        <div>
          Εμφάνιση {paginatedData.length} από {processedData.length} αποτελέσματα
          {totalCount > 0 && ` (σύνολο ${totalCount})`}
        </div>
        {selectedItems.size > 0 && (
          <div className="text-blue-600">
            {selectedItems.size} επιλεγμένα
          </div>
        )}
      </div>

      {/* Data List */}
      <div className="bg-white rounded-lg border border-gray-200">
        {/* Header with Select All */}
        {paginatedData.length > 0 && (
          <div className="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <div className="flex items-center justify-between">
              <label className="flex items-center">
                <input
                  type="checkbox"
                  checked={selectedItems.size === paginatedData.length && paginatedData.length > 0}
                  onChange={handleSelectAll}
                  className="mr-2"
                />
                <span className="text-sm font-medium text-gray-700">Επιλογή Όλων</span>
              </label>
              
              {/* Sort Options */}
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-500">Ταξινόμηση:</span>
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value)}
                  className="text-sm border border-gray-300 rounded px-2 py-1"
                >
                  <option value="">Προεπιλογή</option>
                  <option value="created_at">Ημερομηνία</option>
                  <option value="status">Κατάσταση</option>
                  <option value="tracking_number">Αριθμός</option>
                </select>
                {sortBy && (
                  <button
                    onClick={() => handleSort(sortBy)}
                    className="text-sm text-blue-600 hover:text-blue-800"
                  >
                    {sortOrder === 'asc' ? '↑' : '↓'}
                  </button>
                )}
              </div>
            </div>
          </div>
        )}

        {/* Items */}
        <div className="divide-y divide-gray-200">
          {loading && paginatedData.length === 0 ? (
            <div className="flex items-center justify-center py-8">
              <Loader className="w-6 h-6 animate-spin text-blue-600 mr-2" />
              <span className="text-gray-600">Φόρτωση δεδομένων...</span>
            </div>
          ) : paginatedData.length > 0 ? (
            paginatedData.map((item, index) => (
              <div key={item.id || index} className="p-4 hover:bg-gray-50">
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    checked={selectedItems.has(item.id)}
                    onChange={() => handleSelectItem(item.id)}
                    className="mr-3"
                  />
                  {renderItem ? renderItem(item, index) : (
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900">
                        {item.tracking_number || item.name || `Item ${index + 1}`}
                      </div>
                      <div className="text-sm text-gray-500">
                        {item.status || item.description || 'No description'}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            ))
          ) : (
            <div className="text-center py-8">
              <AlertCircle className="w-8 h-8 text-gray-400 mx-auto mb-2" />
              <p className="text-gray-500">Δεν βρέθηκαν αποτελέσματα</p>
            </div>
          )}
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="px-4 py-3 border-t border-gray-200 bg-gray-50">
            <div className="flex items-center justify-between">
              <div className="text-sm text-gray-600">
                Σελίδα {currentPage} από {totalPages}
              </div>
              
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                  disabled={currentPage === 1}
                  className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="w-4 h-4" />
                </button>
                
                <div className="flex items-center space-x-1">
                  {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                    const page = i + 1;
                    return (
                      <button
                        key={page}
                        onClick={() => setCurrentPage(page)}
                        className={`px-3 py-1 text-sm rounded-md ${
                          currentPage === page
                            ? 'bg-blue-600 text-white'
                            : 'border border-gray-300 hover:bg-gray-50'
                        }`}
                      >
                        {page}
                      </button>
                    );
                  })}
                </div>
                
                <button
                  onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                  disabled={currentPage === totalPages}
                  className="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Load More */}
        {hasMore && (
          <div className="px-4 py-3 border-t border-gray-200 bg-gray-50 text-center">
            <button
              onClick={() => onLoadMore && onLoadMore()}
              disabled={loading}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center mx-auto"
            >
              {loading ? (
                <>
                  <Loader className="w-4 h-4 animate-spin mr-2" />
                  Φόρτωση...
                </>
              ) : (
                'Φόρτωση Περισσότερων'
              )}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
