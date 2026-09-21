(function () {
    const el = document.getElementById('dash-data');
    if (!el || typeof Chart === 'undefined') return;
    const data = JSON.parse(el.textContent);

    Chart.defaults.font.family = '"IBM Plex Sans", "Segoe UI", sans-serif';
    Chart.defaults.color = '#64748b';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.boxHeight = 12;

    const grid = { color: 'rgba(14,35,64,.06)' };

    const salesCanvas = document.getElementById('salesChart');
    if (salesCanvas) {
        new Chart(salesCanvas, {
            type: 'bar',
            data: {
                labels: data.months,
                datasets: [
                    {
                        label: 'Sales',
                        data: data.sales,
                        backgroundColor: '#0e2340',
                        borderRadius: 8,
                        stack: 'amt',
                    },
                    {
                        label: 'PRA tax',
                        data: data.tax,
                        backgroundColor: '#1aa58a',
                        borderRadius: 8,
                        stack: 'amt',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: grid, ticks: { callback: function (v) { return 'Rs ' + Number(v).toLocaleString(); } } },
                },
            },
        });
    }

    const stackCanvas = document.getElementById('stackChart');
    if (stackCanvas) {
        new Chart(stackCanvas, {
            type: 'bar',
            data: {
                labels: data.months,
                datasets: [
                    { label: 'Approved', data: data.stackApproved, backgroundColor: '#1aa58a', stack: 'st', borderRadius: 6 },
                    { label: 'Pending', data: data.stackPending, backgroundColor: '#f4b942', stack: 'st', borderRadius: 6 },
                    { label: 'Rejected', data: data.stackRejected, backgroundColor: '#c24141', stack: 'st', borderRadius: 6 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: grid, ticks: { stepSize: 1 } },
                },
            },
        });
    }

    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: data.statusLabels,
                datasets: [{
                    data: data.statusValues,
                    backgroundColor: ['#1aa58a', '#f4b942', '#c24141', '#94a3b8', '#38bdf8'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }

    const dailyCanvas = document.getElementById('dailyChart');
    if (dailyCanvas) {
        new Chart(dailyCanvas, {
            type: 'line',
            data: {
                labels: data.days,
                datasets: [{
                    label: 'Sales',
                    data: data.daySales,
                    borderColor: '#1aa58a',
                    backgroundColor: 'rgba(26,165,138,.16)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#0e2340',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: grid, ticks: { callback: function (v) { return Number(v).toLocaleString(); } } },
                },
            },
        });
    }

    const payCanvas = document.getElementById('payChart');
    if (payCanvas) {
        new Chart(payCanvas, {
            type: 'bar',
            indexAxis: 'y',
            data: {
                labels: data.payLabels,
                datasets: [{
                    data: data.payValues,
                    backgroundColor: ['#0e2340', '#1aa58a', '#38bdf8'],
                    borderRadius: 8,
                    barThickness: 16,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: grid, ticks: { callback: function (v) { return Number(v).toLocaleString(); } } },
                    y: { grid: { display: false } },
                },
            },
        });
    }
})();
