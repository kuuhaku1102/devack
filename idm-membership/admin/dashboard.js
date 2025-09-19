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

    var index = tbody.querySelectorAll('.idm-weight-row:not([data-template])').length;
    $all('[name]', clone).forEach(function(el) {
      var nameAttr = el.getAttribute('name');
      if (nameAttr) {
        el.setAttribute('name', nameAttr.replace('__INDEX__', index));
      }
      if (el.tagName === 'INPUT' && el.type !== 'hidden') {
        el.value = '';
      }
    });

    var fieldValue = initialData && initialData.field ? initialData.field : 'name';
    var label = $('.idm-weight-field-label', clone);
    if (label) {
      var labelForName = label.getAttribute('data-label-name');
      var labelForEmail = label.getAttribute('data-label-email');
      var text = fieldValue === 'email' && labelForEmail ? labelForEmail : (labelForName || label.textContent);
      label.textContent = text;
      if (fieldValue === 'email') {
        label.classList.add('is-legacy');
      } else {
        label.classList.remove('is-legacy');
      }
    }

    var fieldInput = $('.idm-weight-field-input', clone);
    if (fieldInput) {
      fieldInput.value = fieldValue;
      fieldInput.setAttribute('value', fieldValue);
    }

    var textInput = $('.idm-weight-value-input', clone);
    if (textInput) {
      var textValue = initialData && initialData.value ? initialData.value : '';
      textInput.value = textValue;
      textInput.setAttribute('value', textValue);
    }

    var number = $('input[type="number"]', clone);
    if (number) {
      var fallback = number.getAttribute('value') || (window.idmDashboard && idmDashboard.defaultWeight ? idmDashboard.defaultWeight : 100);
      var weightValue = (initialData && typeof initialData.weight !== 'undefined') ? initialData.weight : fallback;
      number.value = weightValue;
      number.setAttribute('value', weightValue);
    }

    tbody.appendChild(clone);
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
      li.textContent = entry.displayName || '';
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

  function ensureWeightRow(value, weight) {
    var tbody = document.getElementById('idm-weight-rows');
    if (!tbody) {
      return null;
    }

    var rows = $all('.idm-weight-row', tbody).filter(function(row) {
      return !row.hasAttribute('data-template');
    });

    var targetRow = null;
    rows.some(function(row) {
      var fieldInput = $('.idm-weight-field-input', row);
      var textInput = $('.idm-weight-value-input', row);
      if (!fieldInput || !textInput) {
        return false;
      }
      if (fieldInput.value === 'name' && textInput.value === value) {
        targetRow = row;
        return true;
      }
      return false;
    });

    if (!targetRow) {
      targetRow = addWeightRow({ field: 'name', value: value, weight: weight });
    }

    if (!targetRow) {
      return null;
    }

    var label = $('.idm-weight-field-label', targetRow);
    if (label) {
      var labelForName = label.getAttribute('data-label-name');
      label.textContent = labelForName || label.textContent;
      label.classList.remove('is-legacy');
    }

    var fieldInput = $('.idm-weight-field-input', targetRow);
    if (fieldInput) {
      fieldInput.value = 'name';
      fieldInput.setAttribute('value', 'name');
    }

    var textEl = $('.idm-weight-value-input', targetRow);
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
        if (!displayName) {
          displayName = row.getAttribute('data-name') || '';
        }
        var rawName = row.getAttribute('data-name') || '';
        var entryId = row.getAttribute('data-entry-id') || '';

        addSelectedEntry(memberId, {
          memberId: memberId,
          entryId: entryId,
          rawName: rawName,
          displayName: displayName
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
        var value = entry.rawName ? entry.rawName : '';
        if (!value) {
          skipped++;
          return;
        }
        if (ensureWeightRow(value, weightValue)) {
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
    var chance = winner.weight ? winner.weight : 100;
    var html = '<span class="winner">' + escapeHtml(name) + '</span> - ' + chance + '%';
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
