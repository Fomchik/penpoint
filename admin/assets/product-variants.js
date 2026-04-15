(function () {
  'use strict';

  const DEFAULT_UNITS = ['шт', 'кг', 'г', 'л', 'мл', 'м', 'см', 'мм', 'лист', 'набор', 'уп'];

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

  function signature(attributes) {
    const normalized = {};
    Object.keys(attributes || {}).sort().forEach(function (key) {
      normalized[key] = attributes[key];
    });
    return JSON.stringify(normalized);
  }

    function render(root) {
      const createUrl = root.getAttribute('data-attribute-create-url') || '';
      const csrfToken = root.getAttribute('data-csrf-token') || '';
      const parametersContainer = root.querySelector('[data-parameters]');
      const variantsContainer = root.querySelector('[data-variants]');
      const form = root.closest('form');

    let catalog = parseData(root, 'data-parameter-catalog', []).map(function (item) {
      return String(item || '').trim();
    }).filter(Boolean);

    let searchQuery = '';
    const parameters = parseData(root, 'data-initial-parameters', []).map(function (item) {
      const firstValue = parseValuesText(item.values_text || (Array.isArray(item.values) ? item.values.join('\n') : ''))[0] || '';
      const parts = splitValueAndUnit(firstValue);
      return {
        id: item.id || '',
        name: String(item.name || '').trim(),
        value: parts.value,
        unit: parts.unit,
        price: '',
        stock_quantity: 0,
        use_for_variants: Number(item.use_for_variants || 0) === 1
      };
    }).filter(function (item) {
      return item.name !== '';
    });

    let variants = parseData(root, 'data-initial-variants', []).map(function (item) {
      return {
        id: item.id || '',
        label: item.label || '',
        price: item.price === null || item.price === undefined ? '' : String(item.price),
        stock_quantity: Number(item.stock_quantity || 0),
        attributes: item.attributes || {}
      };
    });

    catalog.forEach(function (name) {
      if (!parameters.some(function (item) { return normalizeName(item.name) === normalizeName(name); })) {
        return;
      }
      if (catalog.indexOf(name) === -1) {
        catalog.push(name);
      }
    });

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

    function findParameter(name) {
      return parameters.find(function (item) {
        return normalizeName(item.name) === normalizeName(name);
      }) || null;
    }

    function toggleParameter(name) {
      const existing = findParameter(name);
      if (existing) {
        existing.use_for_variants = !existing.use_for_variants;
      } else {
        parameters.push({
          id: '',
          name: name,
          value: '',
          unit: 'шт',
          price: '',
          stock_quantity: 0,
          use_for_variants: true
        });
      }
      buildVariantsAutomatically();
      renderParameters();
    }

    function parameterValueLabel(item) {
      const value = String(item.value || '').trim();
      const unit = String(item.unit || '').trim();
      return value ? (value + (unit ? ' ' + unit : '')) : '';
    }

    function syncParameterFromField(name, field, value) {
      const parameter = findParameter(name);
      if (!parameter) {
        return;
      }
      parameter[field] = value;
      buildVariantsAutomatically();
      renderVariants();
    }

    function buildVariantsAutomatically() {
      const existingMap = {};
      variants.forEach(function (variant) {
        existingMap[signature(variant.attributes)] = variant;
      });

      variants = parameters
        .filter(function (item) { return item.use_for_variants; })
        .map(function (item) {
          const displayValue = parameterValueLabel(item);
          const attributes = {};
          attributes[item.name] = displayValue;
          const key = signature(attributes);
          const existing = existingMap[key];

          if (existing) {
            existing.label = displayValue ? (item.name + ': ' + displayValue) : item.name;
            existing.attributes = attributes;
            existing.price = String(item.price !== '' ? item.price : existing.price || '');
            existing.stock_quantity = Number(item.stock_quantity || existing.stock_quantity || 0);
            return existing;
          }

          return {
            id: '',
            label: displayValue ? (item.name + ': ' + displayValue) : item.name,
            price: String(item.price || ''),
            stock_quantity: Number(item.stock_quantity || 0),
            attributes: attributes
          };
        });

      variants.forEach(function (variant) {
        const entryName = Object.keys(variant.attributes || {})[0] || '';
        const parameter = findParameter(entryName);
        if (!parameter) {
          return;
        }
        parameter.price = String(variant.price || '');
        parameter.stock_quantity = Number(variant.stock_quantity || 0);
      });
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

    function renderCatalogItem(name, index) {
      const parameter = findParameter(name);
      const active = !!(parameter && parameter.use_for_variants);
      const filterText = name.toLowerCase();
      if (searchQuery && filterText.indexOf(searchQuery) === -1) {
        return '';
      }

      const controls = active ? '' +
        '<div class="param-controls" data-param-controls>' +
          '<input type="hidden" name="product_parameters[' + index + '][id]" value="' + escapeHtml(parameter.id || '') + '">' +
          '<input type="hidden" name="product_parameters[' + index + '][name]" value="' + escapeHtml(parameter.name) + '">' +
          '<input type="hidden" name="product_parameters[' + index + '][use_for_variants]" value="1">' +
          '<input type="hidden" name="product_parameters[' + index + '][values_text]" value="' + escapeHtml(parameterValueLabel(parameter)) + '">' +
          '<input type="text" class="param-control-input" data-param-field="value" data-param-name="' + escapeHtml(name) + '" value="' + escapeHtml(parameter.value) + '" placeholder="Значение">' +
          '<select class="param-control-select" data-param-field="unit" data-param-name="' + escapeHtml(name) + '">' + renderUnitOptions(parameter.unit || 'шт') + '</select>' +
          '<input type="number" min="0" step="0.01" class="param-control-input" data-param-field="price" data-param-name="' + escapeHtml(name) + '" value="' + escapeHtml(parameter.price) + '" placeholder="Цена">' +
        '</div>' : '';

      return '' +
        '<div class="list-item param-item' + (active ? ' active' : '') + '" data-param-item="' + escapeHtml(name) + '">' +
          '<span class="param-item__title">' + escapeHtml(name) + '</span>' +
          controls +
        '</div>';
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

      parametersContainer.querySelectorAll('[data-param-controls]').forEach(function (controls) {
        controls.addEventListener('click', function (event) {
          event.stopPropagation();
        });
      });

      parametersContainer.querySelectorAll('[data-param-field]').forEach(function (field) {
        const handler = function () {
          const name = field.getAttribute('data-param-name') || '';
          const property = field.getAttribute('data-param-field') || '';
          syncParameterFromField(name, property, field.value);
        };
        field.addEventListener('input', handler);
        field.addEventListener('change', handler);
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
            parameters.push({
              id: attribute.id || '',
              name: name,
              value: '',
              unit: 'шт',
              price: '',
              stock_quantity: 0,
              use_for_variants: true
            });
            addInput.value = '';
            buildVariantsAutomatically();
            renderParameters();
            renderVariants();
          }).catch(function () {});
        });
      }
    }

    function variantHiddenInputs(index, variant) {
      return '' +
        '<input type="hidden" name="product_variants[' + index + '][id]" value="' + escapeHtml(variant.id) + '">' +
        '<input type="hidden" name="product_variants[' + index + '][attributes_json]" value="' + escapeHtml(encodeURIComponent(JSON.stringify(variant.attributes || {}))) + '">' +
        '<input type="hidden" name="product_variants[' + index + '][label]" value="' + escapeHtml(variant.label) + '">';
    }

    function renderVariants() {
      if (!variants.length) {
        variantsContainer.innerHTML = '' +
          '<h4 class="admin-variants__section-subtitle">Варианты</h4>' +
          '<div class="admin-variants__empty">Выберите параметры в списке выше. Варианты появятся автоматически.</div>';
        return;
      }

      const rows = variants.map(function (variant, index) {
        const displayValue = Object.keys(variant.attributes || {}).map(function (key) {
          return '<span class="admin-variants__value-tag">' + escapeHtml(variant.attributes[key]) + '</span>';
        }).join('');

        return '' +
          '<div class="admin-variants__variant-row">' +
            variantHiddenInputs(index, variant) +
            '<div class="admin-variants__variant-meta">' +
              '<strong>' + escapeHtml(variant.label) + '</strong>' +
              '<div class="admin-variants__value-list">' + displayValue + '</div>' +
            '</div>' +
            '<label class="admin-variants__variant-field">' +
              '<span>Цена</span>' +
              '<input type="number" min="0" step="0.01" data-variant-price="' + index + '" name="product_variants[' + index + '][price]" value="' + escapeHtml(variant.price) + '" placeholder="Цена товара">' +
            '</label>' +
            '<label class="admin-variants__variant-field">' +
              '<span>Остаток</span>' +
              '<input type="number" min="0" step="1" data-variant-stock="' + index + '" name="product_variants[' + index + '][stock_quantity]" value="' + escapeHtml(String(variant.stock_quantity)) + '">' +
            '</label>' +
          '</div>';
      }).join('');

      variantsContainer.innerHTML = '' +
        '<h4 class="admin-variants__section-subtitle">Варианты</h4>' +
        '<div class="admin-variants__variant-list">' + rows + '</div>';

      variantsContainer.querySelectorAll('[data-variant-price]').forEach(function (input) {
        input.addEventListener('input', function () {
          const index = parseInt(input.getAttribute('data-variant-price'), 10);
          if (Number.isNaN(index) || !variants[index]) {
            return;
          }
          variants[index].price = input.value;
          const name = Object.keys(variants[index].attributes || {})[0] || '';
          const parameter = findParameter(name);
          if (parameter) {
            parameter.price = input.value;
          }
        });
      });

      variantsContainer.querySelectorAll('[data-variant-stock]').forEach(function (input) {
        input.addEventListener('input', function () {
          const index = parseInt(input.getAttribute('data-variant-stock'), 10);
          if (Number.isNaN(index) || !variants[index]) {
            return;
          }
          variants[index].stock_quantity = Number(input.value || 0);
          const name = Object.keys(variants[index].attributes || {})[0] || '';
          const parameter = findParameter(name);
          if (parameter) {
            parameter.stock_quantity = Number(input.value || 0);
          }
        });
      });
    }

      buildVariantsAutomatically();
      renderParameters();
      renderVariants();

      if (form) {
        form.addEventListener('submit', function () {
          buildVariantsAutomatically();
          renderParameters();
          renderVariants();
        });
      }
    }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.admin-variants').forEach(render);
  });
})();
