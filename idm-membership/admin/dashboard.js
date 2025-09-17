(function() {
  function $(selector, context) {
    return (context || document).querySelector(selector);
  }

  function $all(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

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
      if (el.tagName === 'INPUT') {
        el.value = '';
      }
    });

    var select = $('select', clone);
    if (select) {
      select.value = 'email';
    }

    var number = $('input[type="number"]', clone);
    if (number) {
      var baseValue = number.getAttribute('value') || 100;
      number.value = baseValue;
      number.setAttribute('value', baseValue);
    }

    tbody.appendChild(clone);
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
