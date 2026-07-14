/* Storeboot dashboard charts — powered by ApexCharts (public/vendor/apexcharts). */
(function () {
    'use strict';

    var data = window.storebootDashboard;
    if (!data || typeof ApexCharts === 'undefined') return;

    var PALETTE = ['#009a53', '#22dd85', '#2563eb', '#f59e0b', '#8b5cf6', '#14b8a6', '#ef4444', '#06c168', '#0ea5e9', '#64748b'];
    var INK = '#334155';
    var GRID = '#eef2f0';
    var cur = data.currency || '';

    function money(v) {
        return cur + ' ' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function el(id) { return document.querySelector('#' + id); }

    var baseFont = { fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif' };

    // ---- Order status (donut, counts) ----
    if (el('chart-order-status') && data.orderStatus.length) {
        new ApexCharts(el('chart-order-status'), {
            chart: { type: 'donut', height: 280, fontFamily: baseFont.fontFamily },
            series: data.orderStatus.map(function (d) { return d.value; }),
            labels: data.orderStatus.map(function (d) { return d.label; }),
            colors: PALETTE,
            legend: { position: 'bottom', labels: { colors: INK } },
            dataLabels: { enabled: true, formatter: function (val, o) { return o.w.globals.series[o.seriesIndex]; } },
            plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Orders', color: INK, formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0); } } } } } },
            stroke: { width: 2 },
            tooltip: { y: { formatter: function (v) { return v + ' orders'; } } }
        }).render();
    }

    // ---- Sales revenue (area) ----
    if (el('chart-revenue')) {
        var rev = data.revenue || [];
        new ApexCharts(el('chart-revenue'), {
            chart: { type: 'area', height: 300, fontFamily: baseFont.fontFamily, toolbar: { show: false }, zoom: { enabled: false } },
            series: [{ name: 'Revenue', data: rev.map(function (d) { return d.y; }) }],
            xaxis: { categories: rev.map(function (d) { return d.x; }), labels: { style: { colors: INK }, rotate: -35, rotateAlways: false, hideOverlappingLabels: true }, tickAmount: Math.min(rev.length, 12), axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: INK }, formatter: function (v) { return Number(v || 0).toLocaleString(); } } },
            colors: ['#009a53'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] } },
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            grid: { borderColor: GRID, strokeDashArray: 4 },
            markers: { size: 0, hover: { size: 5 } },
            tooltip: { y: { formatter: function (v) { return money(v); } } }
        }).render();
    }

    // ---- Sales by channel (donut, money) ----
    if (el('chart-channel') && data.channel.length) {
        new ApexCharts(el('chart-channel'), {
            chart: { type: 'donut', height: 280, fontFamily: baseFont.fontFamily },
            series: data.channel.map(function (d) { return d.value; }),
            labels: data.channel.map(function (d) { return d.label; }),
            colors: ['#009a53', '#2563eb', '#8b5cf6', '#f59e0b'],
            legend: { position: 'bottom', labels: { colors: INK } },
            plotOptions: { pie: { donut: { size: '60%' } } },
            stroke: { width: 2 },
            dataLabels: { enabled: true, formatter: function (val) { return Number(val).toFixed(0) + '%'; } },
            tooltip: { y: { formatter: function (v) { return money(v); } } }
        }).render();
    }

    // ---- Payment methods (pie, money) ----
    if (el('chart-payment') && data.payment.length) {
        new ApexCharts(el('chart-payment'), {
            chart: { type: 'pie', height: 280, fontFamily: baseFont.fontFamily },
            series: data.payment.map(function (d) { return d.value; }),
            labels: data.payment.map(function (d) { return d.label; }),
            colors: PALETTE,
            legend: { position: 'bottom', labels: { colors: INK } },
            stroke: { width: 2 },
            dataLabels: { enabled: true, formatter: function (val) { return Number(val).toFixed(0) + '%'; } },
            tooltip: { y: { formatter: function (v) { return money(v); } } }
        }).render();
    }

    // ---- Payment accounts (donut, money) ----
    if (el('chart-payment-account') && data.paymentAccount && data.paymentAccount.length) {
        new ApexCharts(el('chart-payment-account'), {
            chart: { type: 'donut', height: 280, fontFamily: baseFont.fontFamily },
            series: data.paymentAccount.map(function (d) { return d.value; }),
            labels: data.paymentAccount.map(function (d) { return d.label; }),
            colors: PALETTE,
            legend: { position: 'bottom', labels: { colors: INK } },
            plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Collected', color: INK, formatter: function (w) { return money(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); } } } } } },
            stroke: { width: 2 },
            dataLabels: { enabled: true, formatter: function (val) { return Number(val).toFixed(0) + '%'; } },
            tooltip: { y: { formatter: function (v) { return money(v); } } }
        }).render();
    }

    // ---- Expense breakdown (horizontal bar, money) ----
    if (el('chart-expense') && data.expense.length) {
        new ApexCharts(el('chart-expense'), {
            chart: { type: 'bar', height: 280, fontFamily: baseFont.fontFamily, toolbar: { show: false } },
            series: [{ name: 'Expenses', data: data.expense.map(function (d) { return d.value; }) }],
            xaxis: { categories: data.expense.map(function (d) { return d.label; }), labels: { style: { colors: INK }, formatter: function (v) { return Number(v || 0).toLocaleString(); } } },
            yaxis: { labels: { style: { colors: INK } } },
            colors: PALETTE,
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '62%', distributed: true } },
            legend: { show: false },
            dataLabels: { enabled: false },
            grid: { borderColor: GRID, strokeDashArray: 4 },
            tooltip: { y: { formatter: function (v) { return money(v); } } }
        }).render();
    }
})();
