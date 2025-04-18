/**
 * Financial Data Visualization Components
 * Uses Chart.js for rendering financial charts
 */

class FinancialCharts {
    /**
     * Initialize the charts manager
     */
    constructor() {
        this.colors = {
            primary: '#007bff',
            success: '#28a745',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8',
            secondary: '#6c757d',
            light: '#f8f9fa',
            dark: '#343a40',
            white: '#ffffff',
            transparent: 'transparent'
        };

        this.chartInstances = {};
    }

    /**
     * Create a revenue bar chart
     * @param {string} canvasId - The ID of the canvas element
     * @param {Array} data - Array of revenue data objects
     * @param {Object} options - Additional chart options
     */
    createRevenueChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Extract quarters and revenue values
        const labels = data.map(item => item.period);
        const revenues = data.map(item => item.revenue);

        // Destroy existing chart if it exists
        if (this.chartInstances[canvasId]) {
            this.chartInstances[canvasId].destroy();
        }

        // Create new chart
        this.chartInstances[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: revenues,
                    backgroundColor: this.colors.primary,
                    borderColor: this.colors.primary,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: options.title ? true : false,
                        text: options.title || '',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                return 'Revenue: $' + value.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000000) {
                                    return '$' + (value / 1000000000).toFixed(1) + 'B';
                                } else if (value >= 1000000) {
                                    return '$' + (value / 1000000).toFixed(1) + 'M';
                                } else if (value >= 1000) {
                                    return '$' + (value / 1000).toFixed(1) + 'K';
                                } else {
                                    return '$' + value;
                                }
                            }
                        }
                    }
                }
            }
        });

        return this.chartInstances[canvasId];
    }

    /**
     * Create a profit margin line chart
     * @param {string} canvasId - The ID of the canvas element
     * @param {Array} data - Array of profit margin data objects
     * @param {Object} options - Additional chart options
     */
    createProfitMarginChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Extract quarters and margin values
        const labels = data.map(item => item.period);
        const grossMargins = data.map(item => item.grossMargin * 100);
        const operatingMargins = data.map(item => item.operatingMargin * 100);
        const netMargins = data.map(item => item.netMargin * 100);

        // Destroy existing chart if it exists
        if (this.chartInstances[canvasId]) {
            this.chartInstances[canvasId].destroy();
        }

        // Create new chart
        this.chartInstances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Gross Margin',
                        data: grossMargins,
                        borderColor: this.colors.primary,
                        backgroundColor: this.hexToRgba(this.colors.primary, 0.1),
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Operating Margin',
                        data: operatingMargins,
                        borderColor: this.colors.success,
                        backgroundColor: this.hexToRgba(this.colors.success, 0.1),
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Net Margin',
                        data: netMargins,
                        borderColor: this.colors.info,
                        backgroundColor: this.hexToRgba(this.colors.info, 0.1),
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: options.title ? true : false,
                        text: options.title || '',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                return context.dataset.label + ': ' + value.toFixed(2) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });

        return this.chartInstances[canvasId];
    }

    /**
     * Create a market share pie chart
     * @param {string} canvasId - The ID of the canvas element
     * @param {Array} data - Array of market share data objects
     * @param {Object} options - Additional chart options
     */
    createMarketShareChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Extract company names and market share values
        const labels = data.map(item => item.name);
        const shares = data.map(item => item.share);

        // Generate colors for each segment
        const backgroundColors = [
            this.colors.primary,
            this.colors.success,
            this.colors.warning,
            this.colors.danger,
            this.colors.info,
            this.colors.secondary,
            '#8e44ad',
            '#e67e22',
            '#16a085',
            '#2c3e50'
        ];

        // Destroy existing chart if it exists
        if (this.chartInstances[canvasId]) {
            this.chartInstances[canvasId].destroy();
        }

        // Create new chart
        this.chartInstances[canvasId] = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: shares,
                    backgroundColor: backgroundColors.slice(0, data.length),
                    borderColor: this.colors.white,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: options.title ? true : false,
                        text: options.title || '',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((value / total) * 100).toFixed(1);
                                return context.label + ': ' + percentage + '%';
                            }
                        }
                    }
                }
            }
        });

        return this.chartInstances[canvasId];
    }

    /**
     * Create a stock price line chart
     * @param {string} canvasId - The ID of the canvas element
     * @param {Array} data - Array of stock price data objects
     * @param {Object} options - Additional chart options
     */
    createStockPriceChart(canvasId, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Extract dates and price values
        const labels = data.map(item => item.date);
        const prices = data.map(item => item.price);

        // Calculate percent change for color
        const startPrice = prices[0];
        const endPrice = prices[prices.length - 1];
        const percentChange = ((endPrice - startPrice) / startPrice) * 100;
        const lineColor = percentChange >= 0 ? this.colors.success : this.colors.danger;

        // Destroy existing chart if it exists
        if (this.chartInstances[canvasId]) {
            this.chartInstances[canvasId].destroy();
        }

        // Create new chart
        this.chartInstances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Stock Price',
                    data: prices,
                    borderColor: lineColor,
                    backgroundColor: this.hexToRgba(lineColor, 0.1),
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    title: {
                        display: options.title ? true : false,
                        text: options.title || '',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                return 'Price: $' + value.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        return this.chartInstances[canvasId];
    }

    /**
     * Create a financial ratios radar chart
     * @param {string} canvasId - The ID of the canvas element
     * @param {Object} data - Financial ratios data object
     * @param {Object} comparisonData - Comparison data (industry averages)
     * @param {Object} options - Additional chart options
     */
    createFinancialRatiosChart(canvasId, data, comparisonData = null, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Extract ratio labels and values
        const labels = Object.keys(data);
        const values = Object.values(data);

        // Prepare datasets
        const datasets = [
            {
                label: 'Company',
                data: values,
                borderColor: this.colors.primary,
                backgroundColor: this.hexToRgba(this.colors.primary, 0.2),
                borderWidth: 2,
                pointBackgroundColor: this.colors.primary,
                pointRadius: 4
            }
        ];

        // Add comparison data if available
        if (comparisonData) {
            const comparisonValues = labels.map(label => comparisonData[label] || 0);
            datasets.push({
                label: 'Industry Average',
                data: comparisonValues,
                borderColor: this.colors.secondary,
                backgroundColor: this.hexToRgba(this.colors.secondary, 0.2),
                borderWidth: 2,
                pointBackgroundColor: this.colors.secondary,
                pointRadius: 4
            });
        }

        // Destroy existing chart if it exists
        if (this.chartInstances[canvasId]) {
            this.chartInstances[canvasId].destroy();
        }

        // Create new chart
        this.chartInstances[canvasId] = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: options.title ? true : false,
                        text: options.title || '',
                    }
                },
                scales: {
                    r: {
                        angleLines: {
                            display: true
                        },
                        suggestedMin: 0
                    }
                }
            }
        });

        return this.chartInstances[canvasId];
    }

    /**
     * Helper method to convert hex color to rgba
     * @param {string} hex - Hex color code
     * @param {number} alpha - Alpha value (0-1)
     * @returns {string} RGBA color string
     */
    hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
}

// Export as global variable
window.FinancialCharts = new FinancialCharts();
