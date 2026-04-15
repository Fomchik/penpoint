(function () {
  'use strict';

  const DEFAULT_UNITS = ['шт', 'кг', 'г', 'л', 'мл', 'м', 'см', 'мм', 'лист', 'набор', 'уп', 'Нет'];

  function parseData(root, name, fallback) {
    const raw = root.getAttribute(name) || '';
    if (!raw) return fallback;
    try {
      const parsed = JSON.parse(raw);
      return parsed || fallback;
    } catch (error) {
      return fallback;
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalizeName(value) {
    return String(value || '').trim().toLowerCase();
  }

  function parseValuesText(text) {
    return String(text || '')
      .split(/[\r\n,]+/)
      .map(function (value) { return value.trim(); })
      .filter(Boolean);
  }

  function splitValueAndUnit(text) {
    const normalized = String(text || '').trim();
    if (!normalized) {
      return { value: '', unit: 'шт' };
    }

    const match = normalized.match(/^(.+?)\s+([^\s]+)$/u);
    if (!match) {
      return { value: normalized, unit: 'шт' };
    }

    return {
      value: match[1].trim(),
      unit: match[2].trim() || 'шт'
    };
  }

  function valueLabel(row) {
    const value = String(row.value || '').trim();
    const unit = String(row.unit || '').trim();
    if (!value || unit === 'Нет') {
      return '';
    }
    return unit ? (value + ' ' + unit) : value;
  }

  function rowKey(name, label) {
    return normalizeName(name) + '::' + normalizeName(label);
  }

  function render(root) {
    const createUrl = root.getAttribute('data-attribute-create-url') || '';
    const csrfToken = root.getAttribute('data-csrf-token') || '';
    const parametersContainer = root.querySelector('[data-parameters]');
    const form = root.closest('form');

    if (!parametersContainer) {
      return;
    }

    let catalog = parseData(root, 'data-parameter-catalog', []).map(function (item) {
      return String(item || '').trim();
    }).filter(Boolean);

    const initialParameters = parseData(root, 'data-initial-parameters', []);
    const initialVariants = parseData(root, 'data-initial-variants', []);
    const variantMap = {};

    initialVariants.forEach(function (variant) {
      const attrs = variant && variant.attributes ? variant.attributes : {};
      const names = Object.keys(attrs);
      if (!names.length) {
        return;
      }
      const name = names[0];
      const label = String(attrs[name] || '').trim();
      if (!name || !label) {
        return;
      }
      variantMap[rowKey(name, label)] = {
        price: variant.price === null || variant.price === undefined ? '' : String(variant.price),
        stock_quantity: Number(variant.stock_quantity || 0)
      };
    });

    const rows = [];
    initialParameters.forEach(function (parameter) {
      const values = parseValuesText(parameter.values_text || (Array.isArray(parameter.values) ? parameter.values.join('\n') : ''));
      const prepared = values.length ? values : [''];
      prepared.forEach(function (item) {
        const parts = splitValueAndUnit(item);
        const label = parts.unit === 'Нет' ? '' : valueLabel(parts);
        const key = rowKey(parameter.name, label);
        const variantData = variantMap[key] || { price: '', stock_quantity: 0 };
        rows.push({
          id: parameter.id || '',
          name: String(parameter.name || '').trim(),
          value: parts.value,
          unit: parts.unit || 'шт',
          price: variantData.price,
          stock_quantity: variantData.stock_quantity,
          use_for_variants: Number(parameter.use_for_variants || 0) === 1
        });
      });
    });

    let searchQuery = '';

    function sortCatalog() {
      catalog = catalog
        .filter(Boolean)
        .filter(function (item, index, list) {
          return list.findIndex(function (value) {
            return normalizeName(value) === normalizeName(item);
          }) === index;
        })
        .sort(function (a, b) { return a.localeCompare(b, 'ru'); });
    }

    function rowsByName(name) {
      return rows.filter(function (row) {
        return normalizeName(row.name) === normalizeName(name);
      });
    }

    function toggleParameter(name) {
      const exists = rowsByName(name);
      if (exists.length) {
        for (let i = rows.length - 1; i >= 0; i -= 1) {
          if (normalizeName(rows[i].name) === normalizeName(name)) {
            rows.splice(i, 1);
          }
        }
      } else {
        rows.push({
          id: '',
          name: name,
          value: '',
          unit: 'шт',
          price: '',
          stock_quantity: 0,
          use_for_variants: true
        });
      }
      renderParameters();
    }

    function addRow(name) {
      rows.push({
        id: '',
        name: name,
        value: '',
        unit: 'шт',
        price: '',
        stock_quantity: 0,
        use_for_variants: true
      });
      renderParameters();
    }

    function removeRow(name, indexInGroup) {
      let seen = -1;
      for (let i = 0; i < rows.length; i += 1) {
        if (normalizeName(rows[i].name) !== normalizeName(name)) {
          continue;
        }
        seen += 1;
        if (seen === indexInGroup) {
          rows.splice(i, 1);
          break;
        }
      }
      renderParameters();
    }

    function renderUnitOptions(selectedUnit) {
      const units = DEFAULT_UNITS.slice();
      if (selectedUnit && units.indexOf(selectedUnit) === -1) {
        units.unshift(selectedUnit);
      }
      return units.map(function (unit) {
        return '<option value="' + escapeHtml(unit) + '"' + (unit === selectedUnit ? ' selected' : '') + '>' + escapeHtml(unit) + '</option>';
      }).join('');
    }

    function buildHiddenInputs(activeRows) {
      const params = [];
      const variants = [];
      let paramIndex = 0;
      let variantIndex = 0;

      activeRows.forEach(function (row) {
        const label = valueLabel(row);
        if (!label) {
          return;
        }

        params.push(
          '<input type="hidden" name="product_parameters[' + paramIndex + '][id]" value="' + escapeHtml(row.id || '') + '">' +
          '<input type="hidden" name="product_parameters[' + paramIndex + '][name]" value="' + escapeHtml(row.name) + '">' +
          '<input type="hidden" name="product_parameters[' + paramIndex + '][use_for_variants]" value="1">' +
          '<input type="hidden" name="product_parameters[' + paramIndex + '][values_text]" value="' + escapeHtml(label) + '">'
        );
        paramIndex += 1;

        const variantAttributes = {};
        variantAttributes[row.name] = label;
        const variantLabel = row.name + ': ' + label;
        variants.push(
          '<input type="hidden" name="product_variants[' + variantIndex + '][id]" value="">' +
          '<input type="hidden" name="product_variants[' + variantIndex + '][label]" value="' + escapeHtml(variantLabel) + '">' +
          '<input type="hidden" name="product_variants[' + variantIndex + '][price]" value="' + escapeHtml(row.price) + '">' +
          '<input type="hidden" name="product_variants[' + variantIndex + '][stock_quantity]" value="' + escapeHtml(String(row.stock_quantity || 0)) + '">' +
          '<input type="hidden" name="product_variants[' + variantIndex + '][attributes_json]" value="' + escapeHtml(encodeURIComponent(JSON.stringify(variantAttributes))) + '">'
        );
        variantIndex += 1;
      });

      return '<div class="admin-variants__hidden-inputs">' + params.join('') + variants.join('') + '</div>';
    }

    function renderRowEditor(name, row, indexInGroup) {
      return '' +
        '<div class="param-controls">' +
          '<input type="text" class="param-control-input" data-param-field="value" data-param-name="' + escapeHtml(name) + '" data-param-index="' + indexInGroup + '" value="' + escapeHtml(row.value) + '" placeholder="Значение">' +
          '<select class="param-control-select" data-param-field="unit" data-param-name="' + escapeHtml(name) + '" data-param-index="' + indexInGroup + '">' + renderUnitOptions(row.unit || 'шт') + '</select>' +
          '<input type="number" min="0" step="0.01" class="param-control-input" data-param-field="price" data-param-name="' + escapeHtml(name) + '" data-param-index="' + indexInGroup + '" value="' + escapeHtml(row.price) + '" placeholder="Цена">' +
          '<input type="number" min="0" step="1" class="param-control-input" data-param-field="stock_quantity" data-param-name="' + escapeHtml(name) + '" data-param-index="' + indexInGroup + '" value="' + escapeHtml(String(row.stock_quantity || 0)) + '" placeholder="Остаток">' +
          '<button type="button" class="param-row-remove" data-remove-row="' + escapeHtml(name) + '" data-remove-index="' + indexInGroup + '" aria-label="Удалить значение">×</button>' +
        '</div>';
    }

    function renderCatalogItem(name) {
      const groupRows = rowsByName(name);
      const active = groupRows.length > 0;
      const filterText = name.toLowerCase();
      if (searchQuery && filterText.indexOf(searchQuery) === -1) {
        return '';
      }

      const controls = active
        ? '<div class="param-item__controls">' +
            groupRows.map(function (row, indexInGroup) {
              return renderRowEditor(name, row, indexInGroup);
            }).join('') +
            '<button type="button" class="param-item__add-row" data-add-row="' + escapeHtml(name) + '" aria-label="Добавить значение">+</button>' +
            buildHiddenInputs(groupRows) +
          '</div>'
        : '';

      return '' +
        '<div class="list-item param-item' + (active ? ' active' : '') + '" data-param-item="' + escapeHtml(name) + '">' +
          '<span class="param-item__title">' + escapeHtml(name) + '</span>' +
          controls +
        '</div>';
    }

    function findRow(name, indexInGroup) {
      let seen = -1;
      for (let i = 0; i < rows.length; i += 1) {
        if (normalizeName(rows[i].name) !== normalizeName(name)) {
          continue;
        }
        seen += 1;
        if (seen === indexInGroup) {
          return rows[i];
        }
      }
      return null;
    }

    function renderParameters() {
      sortCatalog();
      parametersContainer.innerHTML = '' +
        '<h4 class="admin-variants__section-subtitle">Параметры товара</h4>' +
        '<div class="admin-variants__catalog">' +
          '<input type="text" class="search-param admin-variants__search" data-search-param placeholder="Поиск параметров">' +
          '<div class="list admin-variants__catalog-list">' + catalog.map(renderCatalogItem).join('') + '</div>' +
          '<input type="text" class="add-param admin-variants__add-input" data-add-attribute-input placeholder="Добавить параметр и нажать Enter">' +
        '</div>';

      const searchInput = parametersContainer.querySelector('[data-search-param]');
      if (searchInput) {
        searchInput.value = searchQuery;
        searchInput.addEventListener('input', function () {
          searchQuery = String(this.value || '').trim().toLowerCase();
          renderParameters();
        });
      }

      parametersContainer.querySelectorAll('[data-param-item]').forEach(function (item) {
        item.addEventListener('click', function () {
          toggleParameter(item.getAttribute('data-param-item') || '');
        });
      });

      parametersContainer.querySelectorAll('.param-item__controls').forEach(function (controls) {
        controls.addEventListener('click', function (event) {
          event.stopPropagation();
        });
      });

      parametersContainer.querySelectorAll('[data-param-field]').forEach(function (field) {
        const syncRow = function () {
          const name = field.getAttribute('data-param-name') || '';
          const indexInGroup = parseInt(field.getAttribute('data-param-index'), 10);
          const property = field.getAttribute('data-param-field') || '';
          const row = findRow(name, Number.isNaN(indexInGroup) ? 0 : indexInGroup);
          if (!row) {
            return;
          }
          if (property === 'stock_quantity') {
            row.stock_quantity = Number(field.value || 0);
          } else {
            row[property] = field.value;
          }
        };

        const changeHandler = function () {
          syncRow();
          renderParameters();
        };

        field.addEventListener('input', syncRow);
        field.addEventListener('change', changeHandler);
      });

      parametersContainer.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.stopPropagation();
          addRow(button.getAttribute('data-add-row') || '');
        });
      });

      parametersContainer.querySelectorAll('[data-remove-row]').forEach(function (button) {
        button.addEventListener('click', function (event) {
          event.stopPropagation();
          const name = button.getAttribute('data-remove-row') || '';
          const indexInGroup = parseInt(button.getAttribute('data-remove-index'), 10);
          removeRow(name, Number.isNaN(indexInGroup) ? 0 : indexInGroup);
        });
      });

      const addInput = parametersContainer.querySelector('[data-add-attribute-input]');
      if (addInput) {
        addInput.addEventListener('keydown', function (event) {
          if (event.key !== 'Enter') {
            return;
          }

          event.preventDefault();
          const value = String(addInput.value || '').trim();
          if (!value || !createUrl) {
            return;
          }

          if (catalog.some(function (item) { return normalizeName(item) === normalizeName(value); })) {
            addInput.value = '';
            return;
          }

          const body = new URLSearchParams();
          body.set('csrf_token', csrfToken);
          body.set('name', value);

          fetch(createUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              'Accept': 'application/json'
            },
            body: body.toString()
          }).then(function (response) {
            return response.json().then(function (json) {
              return { ok: response.ok, json: json };
            });
          }).then(function (result) {
            if (!result.ok || !result.json.success) {
              throw new Error(result.json.message || 'Не удалось добавить параметр.');
            }
            const attribute = result.json.attribute || {};
            const name = String(attribute.name || value).trim();
            if (!catalog.some(function (item) { return normalizeName(item) === normalizeName(name); })) {
              catalog.push(name);
            }
            addRow(name);
            addInput.value = '';
          }).catch(function () {});
        });
      }
    }

    renderParameters();

    if (form) {
      form.addEventListener('submit', function () {
        renderParameters();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.admin-variants').forEach(render);
  });
})();
