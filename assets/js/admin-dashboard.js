(function () {
  const d = window.ADM; if (!d || !window.Chart) return;
  const navy = '#0B132B', gold = '#D4AF37';
  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  new Chart(document.getElementById('chDays'), {
    data: {
      labels: d.labels,
      datasets: [
        { type: 'bar', label: 'Commandes', data: d.orders, backgroundColor: navy, borderRadius: 4, yAxisID: 'y', order: 2 },
        { type: 'line', label: "Chiffre d'affaires (DH)", data: d.rev, borderColor: gold, backgroundColor: gold, tension: .3, pointRadius: 2, yAxisID: 'y1', order: 1 },
      ],
    },
    options: {
      maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Commandes' } },
        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'DH' } },
        x: { ticks: { maxTicksLimit: 10 } },
      },
      plugins: { legend: { position: 'bottom' } },
    },
  });
  // Statuts : couleurs distinctes, valeur écrite dans la légende (pas seulement la couleur).
  const cols = ['#d9a400', '#2b8fb3', '#0B132B', '#7a7f8c', '#2e8b57', '#b3261e'];
  const total = d.status.data.reduce((a, b) => a + b, 0);
  new Chart(document.getElementById('chStatus'), {
    type: 'doughnut',
    data: { labels: d.status.labels.map((l, i) => l + ' (' + d.status.data[i] + ')'), datasets: [{ data: d.status.data, backgroundColor: cols, borderWidth: 2 }] },
    options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } }, title: { display: total === 0, text: 'Aucune commande' } } },
  });
})();
