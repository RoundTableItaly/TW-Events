// Statistics Dashboard JavaScript
class StatisticsDashboard {
    constructor() {
        this.charts = {};
        this.data = null;
        this.observer = null;
        this.init();
    }

    async init() {
        try {
            await this.loadStatistics();
            this.hideLoading();
            this.setupLazyLoading();
        } catch (error) {
            console.error('Error loading statistics:', error);
            this.showError(error.message || 'Errore nel caricamento delle statistiche');
        }
    }

    setupLazyLoading() {
        // Use Intersection Observer for lazy loading charts
        if ('IntersectionObserver' in window) {
            const chartContainers = document.querySelectorAll('.chart-container');
            
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const canvas = entry.target.querySelector('canvas');
                        if (canvas && !canvas.dataset.loaded) {
                            canvas.dataset.loaded = 'true';
                            this.renderChartForCanvas(canvas);
                        }
                    }
                });
            }, {
                rootMargin: '50px'
            });

            chartContainers.forEach(container => {
                this.observer.observe(container);
            });
        } else {
            // Fallback: render all charts immediately if IntersectionObserver is not supported
            this.renderAllCharts();
        }
    }

    renderChartForCanvas(canvas) {
        const chartId = canvas.id;
        
        if (!this.data) return;

        switch(chartId) {
            case 'topTablesChart':
                this.renderTopTablesChart(this.data.top_tables || []);
                break;
            case 'zonesChart':
                this.renderZonesChart(this.data.events_by_zone || []);
                break;
            case 'monthlyChart':
                this.renderMonthlyChart(this.data.monthly_distribution || []);
                break;
            case 'typesChart':
                this.renderTypesChart(this.data.event_types || []);
                break;
            case 'multiDayChart':
                this.renderMultiDayChart(this.data);
                break;
            case 'dayOfWeekChart':
                this.renderDayOfWeekChart(this.data.day_of_week_distribution || []);
                break;
            case 'bellCurveChart':
                const maxDays = parseInt(document.getElementById('maxDaysFilter')?.value || 180);
                this.renderBellCurveChart(this.data.days_from_creation || [], maxDays);
                break;
        }
    }

    renderAllCharts() {
        if (!this.data) return;
        
        this.renderTopTablesChart(this.data.top_tables || []);
        this.renderZonesChart(this.data.events_by_zone || []);
        this.renderMonthlyChart(this.data.monthly_distribution || []);
        this.renderTypesChart(this.data.event_types || []);
        this.renderMultiDayChart(this.data);
        this.renderDayOfWeekChart(this.data.day_of_week_distribution || []);
        this.setupBellCurveFilter();
    }

    async loadStatistics() {
        const response = await fetch('/api/statistics');
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
        }
        const data = await response.json();
        
        // Check if data exists and has required properties
        if (!data) {
            throw new Error('Nessun dato ricevuto dal server');
        }
        
        // Store data for lazy loading
        this.data = data;
        
        // Render charts immediately if IntersectionObserver is not available
        // Otherwise, charts will be rendered when they come into view
        if (!('IntersectionObserver' in window)) {
            this.renderAllCharts();
        } else {
            // Render charts that are already visible
            const visibleCharts = document.querySelectorAll('.chart-container canvas');
            visibleCharts.forEach(canvas => {
                if (this.isElementInViewport(canvas)) {
                    canvas.dataset.loaded = 'true';
                    this.renderChartForCanvas(canvas);
                }
            });
            
            // Also render non-canvas charts (multi-day stats are shown in HTML)
            this.renderMultiDayChart(data);
            this.renderDayOfWeekChart(data.day_of_week_distribution || []);
            this.setupBellCurveFilter();
        }
    }

    isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    renderTopTablesChart(data) {
        const canvas = document.getElementById('topTablesChart');
        const emptyDiv = document.getElementById('topTablesChart-empty');
        
        if (!data || data.length === 0) {
            canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        // Destroy existing chart if it exists
        if (this.charts.topTables) {
            this.charts.topTables.destroy();
        }
        
        this.charts.topTables = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(item => item.table_name || 'Senza nome'),
                datasets: [{
                    label: 'Eventi',
                    data: data.map(item => item.event_count),
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.x} eventi`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    renderZonesChart(data) {
        const canvas = document.getElementById('zonesChart');
        const emptyDiv = document.getElementById('zonesChart-empty');
        
        if (!data || data.length === 0) {
            canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        const colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)',
            'rgba(83, 102, 255, 0.8)',
            'rgba(255, 99, 255, 0.8)',
            'rgba(99, 255, 132, 0.8)'
        ];

        // Sort data alphabetically by zone name
        const sortedData = [...data].sort((a, b) => {
            const zoneA = (a.zone || 'Senza zona').toLowerCase();
            const zoneB = (b.zone || 'Senza zona').toLowerCase();
            return zoneA.localeCompare(zoneB, 'it');
        });

        // Destroy existing chart if it exists
        if (this.charts.zones) {
            this.charts.zones.destroy();
        }

        this.charts.zones = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: sortedData.map(item => item.zone || 'Senza zona'),
                datasets: [{
                    data: sortedData.map(item => item.event_count),
                    backgroundColor: colors.slice(0, sortedData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} eventi (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    renderMonthlyChart(data) {
        const canvas = document.getElementById('monthlyChart');
        const emptyDiv = document.getElementById('monthlyChart-empty');
        
        if (!data || data.length === 0) {
            canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        // Format month labels
        const monthLabels = data.map(item => {
            const [year, month] = item.month.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('it-IT', { month: 'short', year: 'numeric' });
        });

        // Destroy existing chart if it exists
        if (this.charts.monthly) {
            this.charts.monthly.destroy();
        }

        this.charts.monthly = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Eventi',
                    data: data.map(item => item.event_count),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.y} eventi`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    renderTypesChart(data) {
        const canvas = document.getElementById('typesChart');
        const emptyDiv = document.getElementById('typesChart-empty');
        
        if (!data || data.length === 0) {
            canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        const colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)',
            'rgba(83, 102, 255, 0.8)'
        ];

        // Destroy existing chart if it exists
        if (this.charts.types) {
            this.charts.types.destroy();
        }

        this.charts.types = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(item => item.rt_type || 'Senza tipo'),
                datasets: [{
                    data: data.map(item => item.count),
                    backgroundColor: colors.slice(0, data.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} eventi (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    hideLoading() {
        const loadingEl = document.getElementById('loading');
        const contentEl = document.getElementById('statistics-content');
        if (loadingEl) loadingEl.style.display = 'none';
        if (contentEl) contentEl.style.display = 'block';
    }

    renderMultiDayChart(data) {
        const canvas = document.getElementById('multiDayChart');
        const emptyDiv = document.getElementById('multiDayChart-empty');
        
        if (!data || !data.multi_day_events && !data.single_day_events) {
            if (canvas) canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        if (canvas) canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.multiDay) {
            this.charts.multiDay.destroy();
        }
        
        this.charts.multiDay = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Multi-giornata', 'Singola giornata'],
                datasets: [{
                    data: [data.multi_day_events || 0, data.single_day_events || 0],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} eventi (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    renderDayOfWeekChart(data) {
        const canvas = document.getElementById('dayOfWeekChart');
        const emptyDiv = document.getElementById('dayOfWeekChart-empty');
        
        if (!data || data.length === 0) {
            if (canvas) canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        if (canvas) canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        const dayNames = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
        
        // Sort data by day of week (1=Sunday, 7=Saturday)
        const sortedData = [...data].sort((a, b) => {
            // MySQL DAYOFWEEK returns 1=Sunday, 7=Saturday
            // Adjust to 0=Sunday, 6=Saturday for array indexing
            const dayA = (a.day_of_week % 7);
            const dayB = (b.day_of_week % 7);
            return dayA - dayB;
        });
        
        const labels = sortedData.map(item => {
            const dayIndex = (item.day_of_week % 7);
            return dayNames[dayIndex] || item.day_name;
        });
        
        const values = sortedData.map(item => item.event_count);
        
        if (this.charts.dayOfWeek) {
            this.charts.dayOfWeek.destroy();
        }
        
        this.charts.dayOfWeek = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Eventi',
                    data: values,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.y} eventi`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    calculateGaussian(x, mean, stdDev, totalCount) {
        if (stdDev === 0) {
            return 0;
        }
        
        const exponent = -0.5 * Math.pow((x - mean) / stdDev, 2);
        const coefficient = 1 / (stdDev * Math.sqrt(2 * Math.PI));
        const value = coefficient * Math.exp(exponent);
        
        // Scale to match actual data count
        return value * totalCount;
    }

    calculatePercentile(sortedArray, percentile) {
        if (sortedArray.length === 0) return 0;
        const index = Math.floor(sortedArray.length * percentile);
        return sortedArray[Math.min(index, sortedArray.length - 1)];
    }

    renderBellCurveChart(daysFromCreationData, maxDaysFilter = 180) {
        const canvas = document.getElementById('bellCurveChart');
        const emptyDiv = document.getElementById('bellCurveChart-empty');
        const statsDiv = document.getElementById('bellCurveStats');
        
        if (!daysFromCreationData || daysFromCreationData.length === 0) {
            if (canvas) canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            if (statsDiv) statsDiv.style.display = 'none';
            return;
        }
        
        // Build days array from raw data and store events details for tooltip
        const daysArray = [];
        const eventsByDay = new Map(); // Map<days_diff, events[]>
        
        daysFromCreationData.forEach(item => {
            for (let i = 0; i < item.event_count; i++) {
                daysArray.push(item.days_diff);
            }
            // Store events details for this day
            if (item.events && item.events.length > 0) {
                eventsByDay.set(item.days_diff, item.events);
            }
        });
        
        if (daysArray.length === 0) {
            if (canvas) canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            if (statsDiv) statsDiv.style.display = 'none';
            return;
        }
        
        // Filter by max days
        const totalEventsBeforeFilter = daysArray.length;
        const filteredDaysArray = daysArray.filter(day => day <= maxDaysFilter);
        const excludedEventsCount = totalEventsBeforeFilter - filteredDaysArray.length;
        
        if (filteredDaysArray.length === 0) {
            if (canvas) canvas.style.display = 'none';
            if (emptyDiv) emptyDiv.style.display = 'block';
            if (statsDiv) statsDiv.style.display = 'none';
            return;
        }
        
        // Calculate statistics on filtered data
        const meanDays = filteredDaysArray.reduce((a, b) => a + b, 0) / filteredDaysArray.length;
        let stdDev = 0;
        if (filteredDaysArray.length > 1) {
            const variance = filteredDaysArray.reduce((sum, day) => {
                return sum + Math.pow(day - meanDays, 2);
            }, 0) / filteredDaysArray.length;
            stdDev = Math.sqrt(variance);
        }
        
        // Update stats display
        if (statsDiv) {
            statsDiv.style.display = 'block';
            document.getElementById('meanDaysValue').textContent = meanDays.toFixed(1);
            document.getElementById('stdDevValue').textContent = stdDev.toFixed(1);
            document.getElementById('excludedEventsCount').textContent = excludedEventsCount;
        }
        
        // Prepare chart data
        const minDays = Math.max(0, Math.floor(meanDays - 3 * stdDev));
        const maxDays = Math.ceil(meanDays + 3 * stdDev);
        
        // Create a map of filtered data and events
        const filteredDataMap = new Map();
        const filteredEventsMap = new Map();
        
        daysFromCreationData.forEach(item => {
            if (item.days_diff <= maxDaysFilter) {
                filteredDataMap.set(item.days_diff, item.event_count);
                if (item.events && item.events.length > 0) {
                    filteredEventsMap.set(item.days_diff, item.events);
                }
            }
        });
        
        const labels = [];
        const actualData = [];
        const theoreticalData = [];
        const eventsData = []; // Store events for each day for tooltip
        
        for (let day = minDays; day <= maxDays; day++) {
            labels.push(day);
            actualData.push(filteredDataMap.get(day) || 0);
            theoreticalData.push(this.calculateGaussian(day, meanDays, stdDev, filteredDaysArray.length));
            eventsData.push(filteredEventsMap.get(day) || []);
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        if (canvas) canvas.style.display = 'block';
        
        const ctx = canvas.getContext('2d');
        
        if (this.charts.bellCurve) {
            this.charts.bellCurve.destroy();
        }
        
        // Store events data for click handler
        this.bellCurveEventsData = eventsData;
        this.bellCurveLabels = labels;
        
        const self = this;
        
        this.charts.bellCurve = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Dati Reali',
                        data: actualData,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointHitRadius: 10
                    },
                    {
                        label: 'Curva Teorica (Gaussiana)',
                        data: theoreticalData,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title: function(context) {
                                const dayIndex = context[0].dataIndex;
                                const day = labels[dayIndex];
                                return `${day} giorni prima della data di inizio`;
                            },
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return `${context.dataset.label}: ${context.parsed.y} eventi`;
                                } else {
                                    return `${context.dataset.label}: ${context.parsed.y.toFixed(1)} eventi`;
                                }
                            },
                            afterBody: function(context) {
                                if (context[0].datasetIndex === 0) {
                                    return 'Clicca sul punto per vedere i dettagli degli eventi';
                                }
                                return '';
                            }
                        }
                    }
                },
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const element = elements[0];
                        const datasetIndex = element.datasetIndex;
                        
                        // Only show details for real data (datasetIndex 0)
                        if (datasetIndex === 0) {
                            const dayIndex = element.index;
                            const day = self.bellCurveLabels[dayIndex];
                            const events = self.bellCurveEventsData[dayIndex] || [];
                            
                            self.showEventsDetails(day, events);
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Giorni prima della data di inizio'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Numero Eventi'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    showEventsDetails(day, events) {
        const detailsDiv = document.getElementById('bellCurveEventsDetails');
        const selectedDaysSpan = document.getElementById('selectedDaysValue');
        const eventsList = document.getElementById('eventsList');
        
        if (!detailsDiv || !selectedDaysSpan || !eventsList) return;
        
        if (events.length === 0) {
            detailsDiv.style.display = 'none';
            return;
        }
        
        selectedDaysSpan.textContent = day;
        eventsList.innerHTML = '';
        
        events.forEach(event => {
            const listItem = document.createElement('div');
            listItem.className = 'list-group-item';
            
            let content = `<strong>${event.name || 'Senza nome'}</strong>`;
            
            if (event.table) {
                content += `<br><small class="text-muted"><i class="bi bi-people"></i> ${event.table}</small>`;
            }
            
            if (event.created_at) {
                content += `<br><small class="text-muted"><i class="bi bi-calendar-plus"></i> Pubblicato il: ${event.created_at}</small>`;
            }
            
            if (event.start_date) {
                content += `<br><small class="text-muted"><i class="bi bi-calendar-event"></i> Data evento: ${event.start_date}</small>`;
            }
            
            listItem.innerHTML = content;
            eventsList.appendChild(listItem);
        });
        
        detailsDiv.style.display = 'block';
        
        // Scroll to details section
        detailsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    setupBellCurveFilter() {
        const slider = document.getElementById('maxDaysFilter');
        const valueDisplay = document.getElementById('maxDaysValue');
        
        if (!slider || !valueDisplay) return;
        
        // Update display on slider change
        slider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            valueDisplay.textContent = value;
            
            // Hide events details when filter changes
            const detailsDiv = document.getElementById('bellCurveEventsDetails');
            if (detailsDiv) {
                detailsDiv.style.display = 'none';
            }
            
            // Re-render chart with new filter
            if (this.data && this.data.days_from_creation) {
                this.renderBellCurveChart(this.data.days_from_creation, value);
            }
        });
        
        // Initial render
        const initialValue = parseInt(slider.value);
        if (this.data && this.data.days_from_creation) {
            this.renderBellCurveChart(this.data.days_from_creation, initialValue);
        }
    }

    showError(message) {
        const loadingEl = document.getElementById('loading');
        const errorEl = document.getElementById('error-message');
        const errorTextEl = document.getElementById('error-text');
        
        if (loadingEl) loadingEl.style.display = 'none';
        
        if (errorEl) {
            errorEl.style.display = 'block';
            if (errorTextEl) {
                errorTextEl.textContent = message || 'Errore nel caricamento delle statistiche. Riprova più tardi.';
            }
        }
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new StatisticsDashboard();
});

// Theme toggle functionality (copied from main app)
(() => {
    'use strict'

    const storedTheme = localStorage.getItem('theme')

    const getPreferredTheme = () => {
        if (storedTheme) {
            return storedTheme
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    }

    const setTheme = function (theme) {
        if (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark')
        } else {
            document.documentElement.setAttribute('data-bs-theme', theme)
        }
    }

    setTheme(getPreferredTheme())

    const showActiveTheme = (theme, focus = false) => {
        const themeSwitcher = document.querySelector('#bd-theme')

        if (!themeSwitcher) {
            return
        }

        const themeSwitcherText = document.querySelector('#bd-theme-text')
        const activeThemeIcon = document.querySelector('.theme-icon-active use')
        const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`)
        
        if (!btnToActive) {
            return
        }
        
        const svgOfActiveBtn = btnToActive.querySelector('svg use')
        
        if (!svgOfActiveBtn) {
            return
        }

        document.querySelectorAll('[data-bs-theme-value]').forEach(element => {
            element.classList.remove('active')
            element.setAttribute('aria-pressed', 'false')
        })

        btnToActive.classList.add('active')
        btnToActive.setAttribute('aria-pressed', 'true')
        
        if (activeThemeIcon) {
            activeThemeIcon.setAttribute('href', svgOfActiveBtn.getAttribute('href'))
        }
        
        if (themeSwitcherText) {
            const themeSwitcherLabel = `${themeSwitcherText.textContent} (${btnToActive.dataset.bsThemeValue})`
            themeSwitcher.setAttribute('aria-label', themeSwitcherLabel)
        }

        if (focus) {
            themeSwitcher.focus()
        }
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedTheme !== 'light' || storedTheme !== 'dark') {
            setTheme(getPreferredTheme())
        }
    })

    window.addEventListener('DOMContentLoaded', () => {
        showActiveTheme(getPreferredTheme())

        document.querySelectorAll('[data-bs-theme-value]')
            .forEach(toggle => {
                toggle.addEventListener('click', () => {
                    const theme = toggle.getAttribute('data-bs-theme-value')
                    localStorage.setItem('theme', theme)
                    setTheme(theme)
                    showActiveTheme(theme, true)
                })
            })
    })
})()
