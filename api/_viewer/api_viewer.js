(function () {
  const table = document.querySelector('[data-api-table]');
  const search = document.querySelector('[data-search]');
  const exportButton = document.querySelector('[data-export-csv]');

  if (table && search) {
    search.addEventListener('input', function () {
      const term = search.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (row) {
        const source = (row.getAttribute('data-search-text') || '').toLowerCase();
        row.hidden = term !== '' && !source.includes(term);
      });
    });
  }

  if (exportButton) {
    exportButton.addEventListener('click', function () {
      const node = document.getElementById('api-viewer-data');
      if (!node) return;

      const payload = JSON.parse(node.textContent || '{}');
      const rows = Array.isArray(payload.rows_fmt) ? payload.rows_fmt : Array.isArray(payload.rows) ? payload.rows : [];
      if (!rows.length) return;

      const headers = Object.keys(rows[0]);
      const escapeCsv = function (value) {
        const text = value === null || value === undefined ? '' : String(value);
        return '"' + text.replace(/"/g, '""') + '"';
      };

      const lines = [
        headers.map(escapeCsv).join(';'),
        ...rows.map(function (row) {
          return headers.map(function (header) {
            return escapeCsv(row[header]);
          }).join(';');
        })
      ];

      const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'api_export.csv';
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    });
  }
})();
