(function() {
  function $(selector, context) {
    return (context || document).querySelector(selector);
  }

  function $all(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function addWeightRow(initialData) {
    var template = document.querySelector('.idm-weight-row[data-template="1"]');
    var tbody = document.getElementById('idm-weight-rows');
    if (!template || !tbody) {
      return null;
    }

    var clone = template.cloneNode(true);
    clone.removeAttribute('data-template');
    clone.classList.remove('is-template');
    clone.style.display = '';
    $all('[disabled]', clone).forEach(function(el) {
      el.removeAttribute('disabled');
    });

    var nextIndex = parseInt(tbody.getAttribute('data-next-index') || '0', 10);
    if (isNaN(nextIndex) || nextIndex < 0) {
      nextIndex = 0;
    }
    $all('[name]', clone).forEach(function(el) {
      var name = el.getAttribute('name');
      if (name) {
        el.setAttribute('name', name.replace('__INDEX__', nextIndex));
      }
      if (el.tagName === 'INPUT') {
        el.value = '';
        el.setAttribute('value', '');
      }
    });

    var fieldInput = clone.querySelector('.idm-weight-field');
    if (fieldInput) {
      var fieldValue = initialData && initialData.field ? initialData.field : 'name';
      fieldInput.value = fieldValue;
      fieldInput.setAttribute('value', fieldValue);
    }

    var textInput = clone.querySelector('.idm-weight-value');
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
      if (el.tagName === 'INPUT') {
        el.value = '';
      }
    });

    var select = $('select', clone);
    if (select) {
      select.value = initialData && initialData.field ? initialData.field : 'email';
    }

    var textInput = $('input[type="text"]', clone);
    if (textInput) {
      var textValue = initialData && initialData.value ? initialData.value : '';
      textInput.value = textValue;
      textInput.setAttribute('value', textValue);
    }

    var number = clone.querySelector('.idm-weight-weight');
    var number = $('input[type="number"]', clone);
    if (number) {
      var fallback = number.getAttribute('value') || (window.idmDashboard && idmDashboard.defaultWeight ? idmDashboard.defaultWeight : 100);
      var weightValue = (initialData && typeof initialData.weight !== 'undefined') ? initialData.weight : fallback;
      number.value = weightValue;
      number.setAttribute('value', weightValue);
    }

    tbody.appendChild(clone);
    tbody.setAttribute('data-next-index', String(nextIndex + 1));
    return clone;
  }

  var addButton = document.getElementById('idm-add-weight');
  if (addButton) {
    addButton.addEventListener('click', addWeightRow);
  }

  document.addEventListener('click', function(event) {
    if (event.target && event.target.classList.contains('idm-remove-weight')) {
      var row = event.target.closest('.idm-weight-row');
      if (row && !row.hasAttribute('data-template')) {
        event.preventDefault();
        row.parentNode.removeChild(row);
      }
    }
  });

  var selectedEntries = {};
  var selectedOrder = [];
  var selectedList = document.querySelector('.idm-selected-list');
  var selectedEmpty = document.querySelector('.idm-selected-empty');
  var selectedMessage = document.querySelector('.idm-selected-message');
  var selectedWeightInput = document.querySelector('.idm-selected-weight');
  var applySelectedButton = document.querySelector('.idm-selected-apply');
  var messageFallbacks = {
    selectedNone: '選択された応募者がありません。',
    weightInvalid: '抽選確率は1以上の数値を入力してください。',
    applySuccess: '抽選確率を適用しました。設定を保存してください。',
    applyPartial: '抽選確率を適用しましたが、名前が未設定の応募者は対象外です。',
    applyPartial: '抽選確率を適用しましたが、メールアドレスまたは名前が未設定の応募者は対象外です。',
    applyFailed: '抽選確率を適用できませんでした。対象となる応募者を選択してください。'
  };

  function getMessage(key) {
    if (window.idmDashboard && idmDashboard.i18n && idmDashboard.i18n[key]) {
      return idmDashboard.i18n[key];
    }
    return messageFallbacks[key] || '';
  }

  function setSelectedMessage(text, status) {
    if (!selectedMessage) {
      return;
    }
    selectedMessage.textContent = text || '';
    selectedMessage.className = 'idm-selected-message';
    if (status) {
      selectedMessage.classList.add('is-' + status);
    }
  }

  function addSelectedEntry(id, data) {
    if (!selectedEntries[id]) {
      selectedOrder.push(id);
    }
    selectedEntries[id] = data;
  }

  function removeSelectedEntry(id) {
    if (!selectedEntries[id]) {
      return;
    }
    delete selectedEntries[id];
    var index = selectedOrder.indexOf(id);
    if (index >= 0) {
      selectedOrder.splice(index, 1);
    }
  }

  function getSelectedEntries() {
    return selectedOrder
      .map(function(id) { return selectedEntries[id]; })
      .filter(function(entry) { return !!entry; });
  }

  function updateSelectedDisplay() {
    if (!selectedList || !selectedEmpty) {
      return;
    }

    var entries = getSelectedEntries();
    selectedList.innerHTML = '';

    if (!entries.length) {
      selectedList.style.display = 'none';
      selectedEmpty.style.display = '';
      setSelectedMessage('');
      return;
    }

    selectedEmpty.style.display = 'none';
    selectedList.style.display = '';

    entries.forEach(function(entry) {
      var li = document.createElement('li');
      var label = entry.displayName || entry.rawName || '';
      var label = entry.displayName;
      if (entry.displayEmail) {
        label += ' (' + entry.displayEmail + ')';
      }
      li.textContent = label;
      selectedList.appendChild(li);
    });
  }

  function highlightWeightRow(row) {
    if (!row) {
      return;
    }
    row.classList.add('is-highlighted');
    setTimeout(function() {
      row.classList.remove('is-highlighted');
    }, 1500);
  }

  function ensureWeightRow(field, value, weight) {
    var tbody = document.getElementById('idm-weight-rows');
    if (!tbody) {
      return null;
    }

    var rows = $all('.idm-weight-row', tbody).filter(function(row) {
      return !row.hasAttribute('data-template');
    });

    var targetRow = null;
    rows.some(function(row) {
      var fieldInput = row.querySelector('.idm-weight-field');
      var valueInput = row.querySelector('.idm-weight-value');
      if (!fieldInput || !valueInput) {
        return false;
      }
      if (fieldInput.value === field && valueInput.value === value) {
      var select = $('select', row);
      var textInput = $('input[type="text"]', row);
      if (!select || !textInput) {
        return false;
      }
      if (select.value === field && textInput.value === value) {
        targetRow = row;
        return true;
      }
      return false;
    });

    if (!targetRow) {
      targetRow = addWeightRow({ field: field, value: value, weight: weight });
    }

    if (!targetRow) {
      return null;
    }

    var fieldEl = targetRow.querySelector('.idm-weight-field');
    if (fieldEl) {
      fieldEl.value = field;
      fieldEl.setAttribute('value', field);
    }

    var valueEl = targetRow.querySelector('.idm-weight-value');
    if (valueEl) {
      valueEl.value = value;
      valueEl.setAttribute('value', value);
    }

    var numberEl = targetRow.querySelector('.idm-weight-weight');
    var selectEl = $('select', targetRow);
    if (selectEl) {
      selectEl.value = field;
    }

    var textEl = $('input[type="text"]', targetRow);
    if (textEl) {
      textEl.value = value;
      textEl.setAttribute('value', value);
    }

    var numberEl = $('input[type="number"]', targetRow);
    if (numberEl) {
      numberEl.value = weight;
      numberEl.setAttribute('value', weight);
    }

    highlightWeightRow(targetRow);
    return targetRow;
  }

  $all('.idm-entrant-select').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
      var row = checkbox.closest('.idm-entrant');
      if (!row) {
        return;
      }

      var memberId = row.getAttribute('data-member-id') || checkbox.value;
      if (!memberId) {
        return;
      }
      memberId = String(memberId);

      if (checkbox.checked) {
        var nameEl = $('.idm-entrant-name-text', row);
        var displayName = nameEl ? nameEl.textContent.trim() : '';
        var rawName = (row.getAttribute('data-name') || '').trim();
        if (!displayName) {
          displayName = rawName;
        }
        addSelectedEntry(memberId, {
          memberId: memberId,
          rawName: rawName,
          displayName: displayName || ''
        var emailEl = $('.idm-entrant-email', row);
        var displayName = nameEl ? nameEl.textContent.trim() : '';
        if (!displayName) {
          displayName = row.getAttribute('data-name') || '';
        }
        var displayEmail = emailEl ? emailEl.textContent.trim() : '';
        if (!displayEmail) {
          displayEmail = row.getAttribute('data-email') || '';
        }

        addSelectedEntry(memberId, {
          memberId: memberId,
          rawName: row.getAttribute('data-name') || '',
          rawEmail: row.getAttribute('data-email') || '',
          displayName: displayName,
          displayEmail: displayEmail
        });
        row.classList.add('is-selected');
      } else {
        removeSelectedEntry(memberId);
        row.classList.remove('is-selected');
      }

      updateSelectedDisplay();
    });
  });

  if (applySelectedButton) {
    applySelectedButton.addEventListener('click', function() {
      var entries = getSelectedEntries();
      if (!entries.length) {
        setSelectedMessage(getMessage('selectedNone'), 'warning');
        return;
      }

      if (!selectedWeightInput) {
        return;
      }

      var weightValue = parseInt(selectedWeightInput.value, 10);
      if (!weightValue || weightValue <= 0) {
        setSelectedMessage(getMessage('weightInvalid'), 'error');
        selectedWeightInput.focus();
        return;
      }

      var processed = 0;
      var skipped = 0;

      entries.forEach(function(entry) {
        var value = (entry.rawName || '').trim();
        if (!value) {
          skipped++;
          return;
        }
        if (ensureWeightRow('name', value, weightValue)) {
        var field = entry.rawEmail ? 'email' : (entry.rawName ? 'name' : '');
        var value = entry.rawEmail ? entry.rawEmail : entry.rawName;
        if (!field || !value) {
          skipped++;
          return;
        }
        if (ensureWeightRow(field, value, weightValue)) {
          processed++;
        } else {
          skipped++;
        }
      });

      if (processed > 0 && skipped === 0) {
        setSelectedMessage(getMessage('applySuccess'), 'success');
      } else if (processed > 0) {
        setSelectedMessage(getMessage('applyPartial'), 'warning');
      } else {
        setSelectedMessage(getMessage('applyFailed'), 'error');
      }
    });
  }

  updateSelectedDisplay();

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
