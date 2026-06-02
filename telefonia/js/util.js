const Matrix = {
  apiBase: 'api_matrix',
  token: 'matrix-dev',

  async getJson(endpoint) {
    const url = `${this.apiBase}/${endpoint}`;
    const response = await fetch(url, {
      headers: {
        'Accept': 'application/json',
        'X-Matrix-Token': this.token
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const payload = await response.json();

    if (!payload.ok) {
      throw new Error(payload.error || 'Respuesta no valida');
    }

    return payload;
  },

  setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
  },

  setMessage(id, text, isError = false) {
    const element = document.getElementById(id);
    if (!element) {
      return;
    }

    element.textContent = text;
    element.classList.toggle('error', isError);
  },

  renderRows(tbodyId, rows, columns) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) {
      return;
    }

    tbody.innerHTML = '';

    rows.forEach((row) => {
      const tr = document.createElement('tr');

      columns.forEach((column) => {
        const td = document.createElement('td');
        td.textContent = row[column] ?? '';
        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });
  }
};
