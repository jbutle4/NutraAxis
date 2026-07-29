(function () {
  var table = document.getElementById('por-lines-table');
  if (!table) {
    return;
  }

  var body = document.getElementById('por-lines-body');
  if (!body) {
    return;
  }

  function reindexLineNames() {
    var rows = body.querySelectorAll('tr.por-lot-row');
    rows.forEach(function (row, index) {
      row.querySelectorAll('[name^="lines["]').forEach(function (input) {
        var field = input.name.replace(/^lines\[\d+]/, '').replace(/^\[/, '');
        input.name = 'lines[' + index + ']' + field;
      });
    });
  }

  function refreshAddLotButtons() {
    var rows = Array.prototype.slice.call(body.querySelectorAll('tr.por-lot-row'));
    rows.forEach(function (row, index) {
      var actions = row.querySelector('.por-lot-actions');
      if (!actions) {
        return;
      }

      var poLineId = row.getAttribute('data-po-line-id') || '';
      var nextRow = rows[index + 1];
      var isLastForLine = !nextRow || nextRow.getAttribute('data-po-line-id') !== poLineId;
      actions.innerHTML = '';

      if (isLastForLine) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn-text por-add-lot-btn';
        button.title = 'Add lot number';
        button.textContent = '+ Lot';
        actions.appendChild(button);
      }
    });
  }

  function clearContinuationMeta(row) {
    row.classList.add('por-lot-continuation');
    row.querySelectorAll('.por-line-meta').forEach(function (cell) {
      if (cell.querySelector('input[type="hidden"]')) {
        cell.childNodes.forEach(function (node) {
          if (node.nodeType === Node.TEXT_NODE) {
            node.textContent = '';
          }
        });
        return;
      }
      cell.textContent = '';
    });
  }

  body.addEventListener('click', function (event) {
    var button = event.target.closest('.por-add-lot-btn');
    if (!button) {
      return;
    }

    var sourceRow = button.closest('tr.por-lot-row');
    if (!sourceRow) {
      return;
    }

    var clone = sourceRow.cloneNode(true);
    clone.classList.add('por-lot-continuation');
    clearContinuationMeta(clone);

    clone.querySelectorAll('input:not([type="hidden"])').forEach(function (input) {
      if (input.type === 'checkbox') {
        input.checked = false;
        return;
      }
      input.value = input.name.indexOf('[quantity_received]') !== -1 ? '0' : '';
    });

    var pordInput = clone.querySelector('input[name$="[pord_id]"]');
    if (pordInput) {
      pordInput.value = '';
    }

    sourceRow.parentNode.insertBefore(clone, sourceRow.nextSibling);
    reindexLineNames();
    refreshAddLotButtons();
  });

  refreshAddLotButtons();
})();
