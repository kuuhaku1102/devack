(function() {
  function $(selector, context) {
    return (context || document).querySelector(selector);
  }

(function() {
  function $(selector, context) {
    return (context || document).querySelector(selector);
  }

  function $all(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function escapeSelector(value) {
    if (window.CSS && window.CSS.escape) {
      return window.CSS.escape(value);
    }
    return value.replace(/([\0-\x1F\x7F\s!"#$%&'()*+,./:;<=>?@[\]^`{|}~])/g, '\\$1');
  }

  var dashboard = document.querySelector('.idm-dashboard');
  var defaultWeight = dashboard ? parseInt(dashboard.getAttribute('data-default-weight'), 10) : NaN;
  if (isNaN(defaultWeight) || defaultWeight <= 0) {
    defaultWeight = 100;
  }

  var entrantSelectAll = document.getElementById('idm-entrant-select-all');
  var selectedBody = document.getElementById('idm-selected-entries');
  var selectedTemplate = document.getElementById('idm-selected-entry-template');
  var selectedEmpty = document.querySelector('.idm-selected-empty');
  var selectedBulkInput = document.getElementById('idm-selected-bulk');
  var selectedBulkButton = document.getElementById('idm-apply-selected-bulk');
  var manualBody = document.getElementById('idm-manual-rows');
  var manualTemplate = manualBody ? manualBody.querySelector('.idm-manual-row[data-template="1"]') : null;
  var addManualButton = document.getElementById('idm-add-manual');

  function getEntryRowByKey(key) {
    if (!key) {
      return null;
    }
    return document.querySelector('.idm-entrant[data-entry-key="' + escapeSelector(key) + '"]');
  }

  function syncEntryWeightDisplay(entryRow, weight) {
    if (!entryRow) {
      return;
    }
    entryRow.setAttribute('data-weight', weight);
    var valueEl = entryRow.querySelector('.idm-entrant-weight-value');
    if (valueEl) {
      valueEl.textContent = weight;
    }
  }

  function resetEntryWeight(entryRow) {
    if (!entryRow) {
      return;
    }
    var base = parseInt(entryRow.getAttribute('data-default-weight'), 10);
    if (isNaN(base) || base <= 0) {
      base = defaultWeight;
    }
    syncEntryWeightDisplay(entryRow, base);
    entryRow.classList.remove('is-selected');
  }

  function renumberSelectedRows() {
    if (!selectedBody) {
      return;
    }
    $all('.idm-selected-row', selectedBody).forEach(function(row, index) {
      $all('[data-field]', row).forEach(function(input) {
        var field = input.getAttribute('data-field');
        if (!field) {
          return;
        }
        input.setAttribute('name', 'selected[' + index + '][' + field + ']');
      });
    });
  }

  function renumberManualRows() {
    if (!manualBody) {
      return;
    }
    $all('.idm-manual-row', manualBody).forEach(function(row, index) {
      if (row.hasAttribute('data-template')) {
        return;
      }
      $all('[data-field]', row).forEach(function(input) {
        var field = input.getAttribute('data-field');
        if (!field) {
          return;
        }
        input.setAttribute('name', 'manual[' + index + '][' + field + ']');
      });
    });
  }

  function updateSelectedEmptyState() {
    if (!selectedBody) {
      return;
    }
    var hasRows = $all('.idm-selected-row', selectedBody).length > 0;
    if (selectedEmpty) {
      selectedEmpty.style.display = hasRows ? 'none' : '';
    }
    var table = selectedBody.closest('table');
    if (table) {
      table.style.display = hasRows ? '' : 'none';
    }
    if (selectedBulkInput) {
      selectedBulkInput.disabled = !hasRows;
    }
    if (selectedBulkButton) {
      selectedBulkButton.disabled = !hasRows;
    }
  }

  function updateEntrantSelectAll() {
    if (!entrantSelectAll) {
      return;
    }
    var boxes = $all('.idm-entrant-checkbox');
    if (!boxes.length) {
      entrantSelectAll.checked = false;
      entrantSelectAll.indeterminate = false;
      return;
    }
    var checkedCount = boxes.filter(function(box) { return box.checked; }).length;
    entrantSelectAll.checked = checkedCount === boxes.length;
    entrantSelectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
  }

  function ensureSelectedRow(entryRow) {
    if (!selectedBody || !entryRow) {
      return null;
    }
    var key = entryRow.getAttribute('data-entry-key');
    if (!key) {
      return null;
    }

    var existing = selectedBody.querySelector('.idm-selected-row[data-entry-key="' + escapeSelector(key) + '"]');
    var weight = parseInt(entryRow.getAttribute('data-weight'), 10);
    if (isNaN(weight) || weight <= 0) {
      weight = defaultWeight;
    }

    if (existing) {
      var input = existing.querySelector('[data-field="weight"]');
      if (input) {
        input.value = weight;
      }
      entryRow.classList.add('is-selected');
      syncEntryWeightDisplay(entryRow, weight);
      return existing;
    }

    var templateRow = null;
    if (selectedTemplate && 'content' in selectedTemplate) {
      templateRow = selectedTemplate.content.querySelector('.idm-selected-row');
    }

    var clone = templateRow ? templateRow.cloneNode(true) : document.createElement('tr');
    if (!templateRow) {
      clone.className = 'idm-selected-row';
      clone.innerHTML = '';
    }

    clone.setAttribute('data-entry-key', key);
    clone.setAttribute('data-member-id', entryRow.getAttribute('data-member-id') || '');
    clone.setAttribute('data-email', entryRow.getAttribute('data-email') || '');
    clone.setAttribute('data-name', entryRow.getAttribute('data-name') || '');

    var sourceNameCell = entryRow.querySelector('td:nth-child(2)');
    var sourceEmailCell = entryRow.querySelector('td:nth-child(3)');
    var nameCell = clone.querySelector('.idm-selected-name');
    var emailCell = clone.querySelector('.idm-selected-email');
    if (nameCell && sourceNameCell) {
      nameCell.textContent = sourceNameCell.textContent.trim();
    }
    if (emailCell && sourceEmailCell) {
      emailCell.textContent = sourceEmailCell.textContent.trim();
    }

    var memberField = clone.querySelector('[data-field="member_id"]');
    if (memberField) {
      memberField.value = entryRow.getAttribute('data-member-id') || '';
    }
    var emailField = clone.querySelector('[data-field="email"]');
    if (emailField) {
      emailField.value = entryRow.getAttribute('data-email') || '';
    }
    var nameField = clone.querySelector('[data-field="name"]');
    if (nameField) {
      nameField.value = entryRow.getAttribute('data-name') || '';
    }
    var weightField = clone.querySelector('[data-field="weight"]');
    if (weightField) {
      weightField.value = weight;
    }

    selectedBody.appendChild(clone);
    entryRow.classList.add('is-selected');
    syncEntryWeightDisplay(entryRow, weight);
    renumberSelectedRows();
    updateSelectedEmptyState();
    return clone;
  }

  function removeSelectedRowByKey(key, options) {
    if (!key || !selectedBody) {
      return;
    }
    var row = selectedBody.querySelector('.idm-selected-row[data-entry-key="' + escapeSelector(key) + '"]');
    if (row) {
      row.parentNode.removeChild(row);
      renumberSelectedRows();
      updateSelectedEmptyState();
    }

    var entryRow = getEntryRowByKey(key);
    if (entryRow) {
      if (!options || !options.keepCheckbox) {
        var checkbox = entryRow.querySelector('.idm-entrant-checkbox');
        if (checkbox) {
          checkbox.checked = false;
        }
      }
      resetEntryWeight(entryRow);
    }
    updateEntrantSelectAll();
  }

  function handleEntrantToggle(checkbox) {
    var row = checkbox.closest('.idm-entrant');
    if (!row) {
      return;
    }
    if (checkbox.checked) {
      ensureSelectedRow(row);
    } else {
      removeSelectedRowByKey(row.getAttribute('data-entry-key'), { keepCheckbox: true });
    }
    updateEntrantSelectAll();
  }

  function addManualRow() {
    if (!manualTemplate || !manualBody) {
      return;
    }
    var clone = manualTemplate.cloneNode(true);
    clone.removeAttribute('data-template');
    clone.classList.remove('is-template');
    clone.style.display = '';
    $all('[disabled]', clone).forEach(function(el) {
      el.removeAttribute('disabled');
    });
    $all('[data-field]', clone).forEach(function(input) {
      var field = input.getAttribute('data-field');
      if (input.tagName === 'SELECT') {
        input.value = 'email';
      } else if (input.tagName === 'INPUT') {
        if (input.type === 'number') {
          var base = input.getAttribute('value') || defaultWeight;
          input.value = base;
          input.setAttribute('value', base);
        } else {
          input.value = '';
        }
      }
      if (field) {
        input.setAttribute('name', 'manual[' + $all('.idm-manual-row:not([data-template])', manualBody).length + '][' + field + ']');
      }
    });
    manualBody.appendChild(clone);
    renumberManualRows();
  }

  renumberSelectedRows();
  renumberManualRows();
  updateSelectedEmptyState();
  updateEntrantSelectAll();

  if (selectedBody) {
    $all('.idm-selected-row', selectedBody).forEach(function(row) {
      var key = row.getAttribute('data-entry-key');
      if (!key) {
        return;
      }
      var weightField = row.querySelector('[data-field="weight"]');
      var weight = weightField ? parseInt(weightField.value, 10) : NaN;
      if (isNaN(weight) || weight <= 0) {
        weight = defaultWeight;
      }
      var entryRow = getEntryRowByKey(key);
      if (entryRow) {
        entryRow.classList.add('is-selected');
        var checkbox = entryRow.querySelector('.idm-entrant-checkbox');
        if (checkbox) {
          checkbox.checked = true;
        }
        syncEntryWeightDisplay(entryRow, weight);
      }
    });
    updateSelectedEmptyState();
  }

  if (addManualButton) {
    addManualButton.addEventListener('click', function() {
      addManualRow();
    });
  }

  if (entrantSelectAll) {
    entrantSelectAll.addEventListener('change', function() {
      var checked = entrantSelectAll.checked;
      $all('.idm-entrant-checkbox').forEach(function(box) {
        if (box.checked === checked) {
          return;
        }
        box.checked = checked;
        handleEntrantToggle(box);
      });
      updateEntrantSelectAll();
    });
  }

  document.addEventListener('change', function(event) {
    if (event.target && event.target.classList && event.target.classList.contains('idm-entrant-checkbox')) {
      handleEntrantToggle(event.target);
    }
  });

  document.addEventListener('click', function(event) {
    if (event.target && event.target.classList && event.target.classList.contains('idm-remove-weight')) {
      var row = event.target.closest('.idm-manual-row');
      if (row && !row.hasAttribute('data-template')) {
        event.preventDefault();
        row.parentNode.removeChild(row);
        renumberManualRows();
      }
    }

    if (event.target && event.target.classList && event.target.classList.contains('idm-remove-selected')) {
      event.preventDefault();
      var selectedRow = event.target.closest('.idm-selected-row');
      if (selectedRow) {
        removeSelectedRowByKey(selectedRow.getAttribute('data-entry-key'));
      }
    }
  });

  if (selectedBody) {
    selectedBody.addEventListener('input', function(event) {
      if (event.target && event.target.getAttribute('data-field') === 'weight') {
        var row = event.target.closest('.idm-selected-row');
        if (!row) {
          return;
        }
        var key = row.getAttribute('data-entry-key');
        var weight = parseInt(event.target.value, 10);
        if (!key || isNaN(weight) || weight <= 0) {
          return;
        }
        var entryRow = getEntryRowByKey(key);
        if (entryRow) {
          syncEntryWeightDisplay(entryRow, weight);
        }
      }
    });
  }

  if (selectedBulkButton && selectedBulkInput && selectedBody) {
    selectedBulkButton.addEventListener('click', function() {
      var rawValue = selectedBulkInput.value;
      if (rawValue === '') {
        selectedBulkInput.focus();
  var selectAll = null;

  function addWeightRow() {
    var template = document.querySelector('.idm-weight-row[data-template="1"]');
    var tbody = document.getElementById('idm-weight-rows');
    if (!template || !tbody) {
      return;
    }

    var clone = template.cloneNode(true);
    clone.removeAttribute('data-template');
    clone.classList.remove('is-template');
    clone.style.display = '';
    $all('[disabled]', clone).forEach(function(el) {
      el.removeAttribute('disabled');
    });

    var index = tbody.querySelectorAll('.idm-weight-row:not([data-template])').length;
    $all('[name]', clone).forEach(function(el) {
      var name = el.getAttribute('name');
      if (name) {
        el.setAttribute('name', name.replace('__INDEX__', index));
      }
      if (el.tagName === 'INPUT' && el.type !== 'number' && el.type !== 'checkbox') {
        el.value = '';
      }
    });

    var select = $('select', clone);
    if (select) {
      select.value = 'email';
    }

    var checkbox = $('input[type="checkbox"]', clone);
    if (checkbox) {
      checkbox.checked = false;
    }

    var number = $('input[type="number"]', clone);
    if (number) {
      var baseValue = number.getAttribute('value') || 100;
      number.value = baseValue;
      number.setAttribute('value', baseValue);
    }

    tbody.appendChild(clone);
    updateSelectAllState();
  }

  function updateSelectAllState() {
    if (!selectAll) {
      return;
    }
    var checkboxes = $all('.idm-weight-row:not([data-template]) .idm-weight-select');
    if (!checkboxes.length) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    var checkedCount = checkboxes.filter(function(box) { return box.checked; }).length;
    selectAll.checked = checkedCount === checkboxes.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
  }

  var addButton = document.getElementById('idm-add-weight');
  if (addButton) {
    addButton.addEventListener('click', addWeightRow);
  }

  selectAll = document.getElementById('idm-weight-select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      var checked = selectAll.checked;
      $all('.idm-weight-row:not([data-template]) .idm-weight-select').forEach(function(box) {
        box.checked = checked;
      });
      updateSelectAllState();
    });
    updateSelectAllState();
  }

  document.addEventListener('click', function(event) {
    if (event.target && event.target.classList.contains('idm-remove-weight')) {
      var row = event.target.closest('.idm-weight-row');
      if (row && !row.hasAttribute('data-template')) {
        event.preventDefault();
        row.parentNode.removeChild(row);
        updateSelectAllState();
      }
    }
  });

  document.addEventListener('change', function(event) {
    if (event.target && event.target.classList.contains('idm-weight-select')) {
      updateSelectAllState();
    }
  });

  document.addEventListener('input', function(event) {
    if (event.target && event.target.matches('.idm-weight-row input[type="number"][name$="[weight]"]')) {
      var row = event.target.closest('.idm-weight-row');
      if (!row || row.hasAttribute('data-template')) {
        return;
      }
      var checkbox = row.querySelector('.idm-weight-select');
      if (checkbox && !checkbox.checked) {
        checkbox.checked = true;
        updateSelectAllState();
      }
    }
  });

  var bulkInput = document.getElementById('idm-bulk-weight');
  var bulkButton = document.getElementById('idm-apply-bulk');

  if (bulkButton && bulkInput) {
    bulkButton.addEventListener('click', function() {
      var rawValue = bulkInput.value;
      if (rawValue === '') {
        bulkInput.focus();
        return;
      }

      var number = parseInt(rawValue, 10);
      if (isNaN(number)) {
        selectedBulkInput.focus();
        return;
      }

      var min = parseInt(selectedBulkInput.getAttribute('min'), 10);
      var max = parseInt(selectedBulkInput.getAttribute('max'), 10);
        bulkInput.focus();
        return;
      }

      var min = parseInt(bulkInput.getAttribute('min'), 10);
      var max = parseInt(bulkInput.getAttribute('max'), 10);
      if (!isNaN(min) && number < min) {
        number = min;
      }
      if (!isNaN(max) && number > max) {
        number = max;
      }

      selectedBulkInput.value = number;

      $all('.idm-selected-row', selectedBody).forEach(function(row) {
        var weightField = row.querySelector('[data-field="weight"]');
        if (weightField) {
          weightField.value = number;
        }
        var key = row.getAttribute('data-entry-key');
        if (key) {
          var entryRow = getEntryRowByKey(key);
          if (entryRow) {
            syncEntryWeightDisplay(entryRow, number);
          }
        }
      });
    });
  }

  var drawButton = document.getElementById('idm-draw-button');
  var resultBox = document.getElementById('idm-draw-result');

  if (drawButton && resultBox && typeof window.idmDashboard !== 'undefined') {
    drawButton.addEventListener('click', function() {
      if (drawButton.disabled) {
        return;
      }
      if (!idmDashboard.campaign) {
        return;
      }

      drawButton.disabled = true;
      resultBox.textContent = idmDashboard.i18n ? idmDashboard.i18n.drawing : 'Drawing...';

      var formData = new FormData();
      formData.append('action', 'idm_campaign_draw');
      formData.append('nonce', idmDashboard.nonce);
      formData.append('campaign', idmDashboard.campaign);

      fetch(idmDashboard.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then(function(response) { return response.json(); })
        .then(function(json) {
          if (!json || !json.success) {
            var message = json && json.data && json.data.message ? json.data.message : 'Error';
            showError(message);
            return;
          }

          if (!json.data || !json.data.winner) {
            showError('No winner returned');
            return;
          }

          var winner = json.data.winner;
          animateRoulette(winner);
        })
        .catch(function() {
          showError('通信中にエラーが発生しました。');
        });
    });
  }

  function showError(message) {
    if (!resultBox) {
      return;
    }
    resultBox.innerHTML = '<span class="error">' + escapeHtml(message) + '</span>';
    if (drawButton) {
      drawButton.disabled = false;
    }
  }

  function animateRoulette(winner) {
    var rows = $all('.idm-entrant-list .idm-entrant');
    if (!rows.length) {
      displayWinner(winner);
      if (drawButton) {
        drawButton.disabled = false;
      }
      return;
    }

    rows.forEach(function(row) {
      row.classList.remove('is-active', 'is-final');
    });

    var winnerRow = rows.find(function(row) {
      return row.getAttribute('data-member-id') === String(winner.member_id);
    });

    var sequence = [];
    var loops = 3;
    var i;

    for (i = 0; i < loops; i++) {
      rows.forEach(function(_, idx) {
        sequence.push(idx);
      });
    }

    var winnerIndex = winnerRow ? rows.indexOf(winnerRow) : -1;
    if (winnerIndex >= 0) {
      var extra = rows.length * 2;
      for (i = 0; i < extra; i++) {
        sequence.push((winnerIndex + i) % rows.length);
      }
      sequence.push(winnerIndex);
    }

    if (!sequence.length) {
      displayWinner(winner);
      if (drawButton) {
        drawButton.disabled = false;
      }
      return;
    }

    var totalDelay = 0;
    var previous;
    sequence.forEach(function(idx, step) {
      var isFinal = step === sequence.length - 1;
      var delay = 70 + Math.min(step, 40) * 5;
      totalDelay += delay;
      setTimeout(function() {
        if (previous) {
          previous.classList.remove('is-active', 'is-final');
        }
        var row = rows[idx];
        if (!row) {
          return;
        }
        row.classList.add('is-active');
        if (isFinal) {
          row.classList.add('is-final');
          displayWinner(winner);
        }
        previous = row;
      }, totalDelay);
    });

    setTimeout(function() {
      if (drawButton) {
        drawButton.disabled = false;
      }
    }, totalDelay + 50);
  }

  function displayWinner(winner) {
    if (!resultBox) {
      return;
    }
    var name = winner.name ? winner.name : '(未設定)';
    var email = winner.email ? winner.email : '(メール不明)';
    var chance = winner.weight ? winner.weight : 100;
    var html = '<span class="winner">' + escapeHtml(name) + '</span> (' + escapeHtml(email) + ') - ' + chance + '%';
    resultBox.innerHTML = html;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
      bulkInput.value = number;

      var selected = $all('.idm-weight-row:not([data-template]) .idm-weight-select:checked');
      if (!selected.length) {
        return;
      }

      selected.forEach(function(box) {
        var row = box.closest('.idm-weight-row');
        if (!row) {
          return;
        }
        var input = row.querySelector('input[type="number"][name$="[weight]"]');
        if (!input) {
          return;
        }
        input.value = number;
        input.setAttribute('value', number);
      });
    });
  }

  var drawButton = document.getElementById('idm-draw-button');
  var resultBox = document.getElementById('idm-draw-result');

  if (drawButton && resultBox && typeof window.idmDashboard !== 'undefined') {
    drawButton.addEventListener('click', function() {
      if (drawButton.disabled) {
        return;
      }
      if (!idmDashboard.campaign) {
        return;
      }

      drawButton.disabled = true;
      resultBox.textContent = idmDashboard.i18n ? idmDashboard.i18n.drawing : 'Drawing...';

      var formData = new FormData();
      formData.append('action', 'idm_campaign_draw');
      formData.append('nonce', idmDashboard.nonce);
      formData.append('campaign', idmDashboard.campaign);

      fetch(idmDashboard.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then(function(response) { return response.json(); })
        .then(function(json) {
          if (!json || !json.success) {
            var message = json && json.data && json.data.message ? json.data.message : 'Error';
            showError(message);
            return;
          }

          if (!json.data || !json.data.winner) {
            showError('No winner returned');
            return;
          }

          var winner = json.data.winner;
          animateRoulette(winner);
        })
        .catch(function() {
          showError('通信中にエラーが発生しました。');
        });
    });
  }

  function showError(message) {
    if (!resultBox) {
      return;
    }
    resultBox.innerHTML = '<span class="error">' + escapeHtml(message) + '</span>';
    if (drawButton) {
      drawButton.disabled = false;
    }
  }

  function animateRoulette(winner) {
    var rows = $all('.idm-entrant-list .idm-entrant');
    if (!rows.length) {
      displayWinner(winner);
      if (drawButton) {
        drawButton.disabled = false;
      }
      return;
    }

    rows.forEach(function(row) {
      row.classList.remove('is-active', 'is-final');
    });

    var winnerRow = rows.find(function(row) {
      return row.getAttribute('data-member-id') === String(winner.member_id);
    });

    var sequence = [];
    var loops = 3;
    var i;

    for (i = 0; i < loops; i++) {
      rows.forEach(function(_, idx) {
        sequence.push(idx);
      });
    }

    var winnerIndex = winnerRow ? rows.indexOf(winnerRow) : -1;
    if (winnerIndex >= 0) {
      var extra = rows.length * 2;
      for (i = 0; i < extra; i++) {
        sequence.push((winnerIndex + i) % rows.length);
      }
      sequence.push(winnerIndex);
    }

    if (!sequence.length) {
      displayWinner(winner);
      if (drawButton) {
        drawButton.disabled = false;
      }
      return;
    }

    var totalDelay = 0;
    var previous;
    sequence.forEach(function(idx, step) {
      var isFinal = step === sequence.length - 1;
      var delay = 70 + Math.min(step, 40) * 5;
      totalDelay += delay;
      setTimeout(function() {
        if (previous) {
          previous.classList.remove('is-active', 'is-final');
        }
        var row = rows[idx];
        if (!row) {
          return;
        }
        row.classList.add('is-active');
        if (isFinal) {
          row.classList.add('is-final');
          displayWinner(winner);
        }
        previous = row;
      }, totalDelay);
    });

    setTimeout(function() {
      if (drawButton) {
        drawButton.disabled = false;
      }
    }, totalDelay + 50);
  }

  function displayWinner(winner) {
    if (!resultBox) {
      return;
    }
    var name = winner.name ? winner.name : '(未設定)';
    var email = winner.email ? winner.email : '(メール不明)';
    var chance = winner.weight ? winner.weight : 100;
    var html = '<span class="winner">' + escapeHtml(name) + '</span> (' + escapeHtml(email) + ') - ' + chance + '%';
    resultBox.innerHTML = html;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
