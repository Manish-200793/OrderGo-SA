/**
 * Admin Analytics Charts using Chart.js CDN
 * Matches former Recharts visual aesthetics
 */

function renderRevenueChart(canvasId, labels, dataPoints) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
  gradient.addColorStop(0, 'rgba(249, 115, 22, 0.4)');
  gradient.addColorStop(1, 'rgba(249, 115, 22, 0.0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Revenue (₹)',
        data: dataPoints,
        borderColor: '#F97316',
        backgroundColor: gradient,
        borderWidth: 3,
        fill: true,
        tension: 0.35,
        pointBackgroundColor: '#F97316',
        pointRadius: 5,
        pointHoverRadius: 7
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#FFFFFF',
          titleColor: '#0F172A',
          bodyColor: '#EA580C',
          borderColor: '#E2E8F0',
          borderWidth: 1,
          padding: 10,
          callbacks: {
            label: function(context) {
              return ' ₹' + context.parsed.y.toLocaleString();
            }
          }
        }
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) { return '₹' + value; }
          },
          grid: { color: 'rgba(0,0,0,0.05)' }
        }
      }
    }
  });
}

function renderPeakHoursChart(canvasId, labels, orderCounts) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Orders',
        data: orderCounts,
        backgroundColor: '#FB923C',
        borderRadius: 6,
        hoverBackgroundColor: '#EA580C'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#FFFFFF',
          titleColor: '#0F172A',
          bodyColor: '#475569',
          borderColor: '#E2E8F0',
          borderWidth: 1,
          padding: 10
        }
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          beginAtZero: true,
          ticks: { stepSize: 1 },
          grid: { color: 'rgba(0,0,0,0.05)' }
        }
      }
    }
  });
}
