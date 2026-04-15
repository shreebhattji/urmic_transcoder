<?php

/*
Urmi you happy me happy licence

Copyright (c) 2026 shreebhattji

License text:
https://github.com/shreebhattji/Urmi/blob/main/licence.md
*/

include 'header.php'; ?>

<div class="containerindex">
  <div class="grid">
    <div class="card">
      <h3>CPU (%)</h3>
      <div class="chart-wrap"><canvas id="cpuChart"></canvas></div>
    </div>
    <div class="card">
      <h3>RAM (%)</h3>
      <div class="chart-wrap"><canvas id="ramChart"></canvas></div>
    </div>
    <div class="card wide">
      <h3>Network (KB/s)</h3>
      <div class="chart-wrap"><canvas id="netChart"></canvas></div>
    </div>
    <div class="card wide">
      <h3>Disk I/O (KB/s) & Disk %</h3>
      <div class="chart-wrap"><canvas id="diskChart"></canvas></div>
    </div>
  </div>

  <div style="margin-top:12px; color:#9fb2d6; display:flex; justify-content:space-between;">
    <div>Last update: <span id="lastUpdate">—</span></div>
    <div>CPU: <span id="lastCpu">—</span>% · RAM: <span id="lastRam">—</span>% · In: <span id="lastIn">—</span>KB/s ·
      Out: <span id="lastOut">—</span>KB/s</div>
  </div>
  <br>
  <br>
  <br>
  <br>
</div>
<script>
  const POLL_MS = 1000;
  const JSON_URL = "metrics.json";

  function toKB(v) {
    return Math.round(v / 1024);
  }

  const cpuChart = new Chart(document.getElementById('cpuChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label: 'CPU %',
        data: [],
        fill: false,
        tension: 0.2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          min: 0,
          max: 100
        }
      }
    }
  });
  const ramChart = new Chart(document.getElementById('ramChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label: 'RAM %',
        data: [],
        fill: false,
        tension: 0.2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          min: 0,
          max: 100
        }
      }
    }
  });
  const netChart = new Chart(document.getElementById('netChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
          label: 'Net In (KB/s)',
          data: [],
          fill: false,
          tension: 0.2
        },
        {
          label: 'Net Out (KB/s)',
          data: [],
          fill: false,
          tension: 0.2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
  const diskChart = new Chart(document.getElementById('diskChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
          label: 'Disk Read (KB/s)',
          data: [],
          fill: false,
          tension: 0.2
        },
        {
          label: 'Disk Write (KB/s)',
          data: [],
          fill: false,
          tension: 0.2
        },
        {
          label: 'Disk %',
          data: [],
          yAxisID: 'percent',
          fill: false,
          tension: 0.2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          position: 'left',
          beginAtZero: true
        },
        percent: {
          position: 'right',
          min: 0,
          max: 100,
          grid: {
            display: false
          },
          ticks: {
            callback: v => v + '%'
          }
        }
      }
    }
  });

  async function update() {
    try {
      const res = await fetch(JSON_URL + "?_=" + Date.now(), {
        cache: 'no-store'
      });
      if (!res.ok) throw new Error('fetch fail ' + res.status);
      const j = await res.json();

      const labels = j.timestamps.map(t => new Date(t).toLocaleTimeString());
      cpuChart.data.labels = labels;
      cpuChart.data.datasets[0].data = j.cpu_percent;

      ramChart.data.labels = labels;
      ramChart.data.datasets[0].data = j.ram_percent;

      netChart.data.labels = labels;
      netChart.data.datasets[0].data = j.net_in_Bps.map(toKB);
      netChart.data.datasets[1].data = j.net_out_Bps.map(toKB);

      diskChart.data.labels = labels;
      diskChart.data.datasets[0].data = j.disk_read_Bps.map(toKB);
      diskChart.data.datasets[1].data = j.disk_write_Bps.map(toKB);
      diskChart.data.datasets[2].data = j.disk_percent;

      cpuChart.update();
      ramChart.update();
      netChart.update();
      diskChart.update();

      const last = labels.length - 1;
      if (last >= 0) {
        document.getElementById('lastUpdate').textContent = labels[last];
        document.getElementById('lastCpu').textContent = j.cpu_percent[last];
        document.getElementById('lastRam').textContent = j.ram_percent[last];
        document.getElementById('lastIn').textContent = toKB(j.net_in_Bps[last]);
        document.getElementById('lastOut').textContent = toKB(j.net_out_Bps[last]);
      }
    } catch (e) {
      console.error('update failed', e);
    }
  }

  setInterval(update, POLL_MS);
  update();
</script>
<?php include 'footer.php'; ?>