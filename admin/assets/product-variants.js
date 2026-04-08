(function () {
  'use strict';

  function parseData(root, name, fallback) {
    const raw = root.getAttribute(name) || '';
    if (!raw) return fallback;
    try {
      const parsed = JSON.parse(raw);
      return parsed || fallback;
    } catch (e) {
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

  function parseValuesText(text) {
    return String(text || '')
      .split(/[\r\n,]+/)
      .map(function (value) { return value.trim(); })
      .filter(Boolean)
      .filter(function (value, index, list) { return list.indexOf(value) === index; });
  }

  function signature(attributes) {
    const normalized = {};
    Object.keys(attributes || {}).sort().forEach(function (key) {
      normalized[key] = attributes[key];
    });
    return JSON.stringify(normalized);
  }

  function render(root) {
    const catalog = parseData(root, 'data-parameter-catalog', {});
    let parameters = parseData(root, 'data-initial-parameters', []).map(function (item) {
      return {
        id: item.id || '',
        name: item.name || '',
        custom_name: '',
        values_text: item.values_text || (Array.isArray(item.values) ? item.values.join('\n') : ''),
        use_for_variants: Number(item.use_for_variants || 0) === 1
      };
    });
    let variants = parseData(root, 'data-initial-variants', []).map(function (item) {
      return {
        id: item.id || '',
        label: item.label || '',
        price: item.price === null || item.price === undefined ? '' : item.price,
        stock_quantity: item.stock_quantity || 0,
        attributes: item.selection_map || item.attributes || {}
      };
    });
    const parametersContainer = root.querySelector('[data-parameters]');
    const variantsContainer = root.querySelector('[data-variants]');

    function collectParametersFromDom() {
      parameters = Array.from(parametersContainer.querySelectorAll('tbody tr')).map(function (row) {
        const select = row.querySelector('[data-parameter-name]');
        const custom = row.querySelector('[data-custom-name]');
        const values = row.querySelector('textarea');
        const checkbox = row.querySelector('input[type="checkbox"]');
        const hiddenId = row.querySelector('input[type="hidden"]');
        return {
          id: hiddenId ? hiddenId.value : '',
          name: select ? select.value : '',
          custom_name: custom ? custom.value.trim() : '',
          values_text: values ? values.value : '',
          use_for_variants: checkbox ? checkbox.checked : false
        };
      });
    }

    function getParameterSelectOptions(selectedName) {
      const labels = Object.keys(catalog).map(function (code) { return catalog[code]; });
      const options = ['<option value="">Выберите параметр</option>'];
      Object.keys(catalog).forEach(function (code) {
        const label = catalog[code];
        options.push('<option value="' + escapeHtml(label) + '"' + (selectedName === label ? ' selected' : '') + '>' + escapeHtml(label) + '</option>');
      });
      options.push('<option value="__custom__"' + (selectedName && labels.indexOf(selectedName) === -1 ? ' selected' : '') + '>Свой параметр</option>');
      return options.join('');
    }

    function syncVariantsAutomatically() {
      collectParametersFromDom();

      const sources = parameters
        .map(function (item) {
          const name = item.name === '__custom__' ? item.custom_name : item.name;
          return {
            name: name,
            values: parseValuesText(item.values_text),
            use_for_variants: !!item.use_for_variants
          };
        })
        .filter(function (item) {
          return item.name && item.values.length && item.use_for_variants;
        });

      if (!sources.length) {
        renderVariants();
        return;
      }

      let combinations = [{}];
      sources.forEach(function (source) {
        const next = [];
        combinations.forEach(function (base) {
          source.values.forEach(function (value) {
            const attributes = Object.assign({}, base);
            attributes[source.name] = value;
            next.push(attributes);
          });
        });
        combinations = next;
      });

      const nextVariants = [];
      const existingBySignature = {};
      variants.forEach(function (variant) {
        existingBySignature[signature(variant.attributes || {})] = variant;
      });

      combinations.forEach(function (attributes) {
        const key = signature(attributes);
        if (existingBySignature[key]) {
          nextVariants.push(existingBySignature[key]);
        } else {
          nextVariants.push({
            id: '',
            label: Object.keys(attributes).map(function (name) { return name + ': ' + attributes[name]; }).join(', '),
            price: '',
            stock_quantity: 0,
            attributes: attributes
          });
        }
      });

      variants.forEach(function (variant) {
        const key = signature(variant.attributes || {});
        if (!existingBySignature[key]) {
          nextVariants.push(variant);
        }
      });

      variants = nextVariants;
      renderVariants();
    }

    function renderParameters() {
      const rows = parameters.map(function (item, index) {
        const labels = Object.keys(catalog).map(function (code) { return catalog[code]; });
        const isCustom = item.name && labels.indexOf(item.name) === -1;
        return '<tr>' +
          '<td>' +
          '<input type="hidden" name="product_parameters[' + index + '][id]" value="' + escapeHtml(item.id) + '">' +
          '<select name="product_parameters[' + index + '][name]" data-parameter-name>' + getParameterSelectOptions(item.name) + '</select>' +
          '</td>' +
          '<td>' +
          '<input type="text" name="product_parameters[' + index + '][custom_name]" value="' + escapeHtml(isCustom ? item.name : '') + '" placeholder="Название параметра"' + (isCustom ? '' : ' style=\"display:none;\"') + ' data-custom-name>' +
          '</td>' +
          '<td><textarea name="product_parameters[' + index + '][values_text]" rows="3" placeholder="Через запятую или с новой строки">' + escapeHtml(item.values_text) + '</textarea></td>' +
          '<td class="admin-variants__center"><input type="checkbox" name="product_parameters[' + index + '][use_for_variants]" value="1"' + (item.use_for_variants ? ' checked' : '') + '></td>' +
          '<td class="admin-variants__actions-col"><button type="button" class="admin-text-btn danger" data-remove-parameter="' + index + '">Удалить</button></td>' +
          '</tr>';
      }).join('');

      parametersContainer.innerHTML =
        '<div class="admin-variants__toolbar">' +
        '<button type="button" class="admin-link-btn" data-add-parameter>Добавить параметр</button>' +
        '</div>' +
        '<table class="admin-table admin-variants__table-ui">' +
        '<thead><tr><th>Параметр</th><th>Свой параметр</th><th>Значения</th><th>Варианты</th><th></th></tr></thead>' +
        '<tbody>' + (rows || '<tr><td colspan="5">Параметры пока не добавлены.</td></tr>') + '</tbody>' +
        '</table>';

      parametersContainer.querySelector('[data-add-parameter]').addEventListener('click', function () {
        parameters.push({ id: '', name: '', custom_name: '', values_text: '', use_for_variants: false });
        renderParameters();
      });

      parametersContainer.querySelectorAll('[data-remove-parameter]').forEach(function (button) {
        button.addEventListener('click', function () {
          parameters.splice(parseInt(this.getAttribute('data-remove-parameter'), 10), 1);
          renderParameters();
          syncVariantsAutomatically();
        });
      });

      parametersContainer.querySelectorAll('[data-parameter-name]').forEach(function (select) {
        select.addEventListener('change', function () {
          const input = this.closest('tr').querySelector('[data-custom-name]');
          if (!input) return;
          input.style.display = this.value === '__custom__' ? '' : 'none';
          if (this.value !== '__custom__') {
            input.value = '';
          }
          syncVariantsAutomatically();
        });
      });

      parametersContainer.querySelectorAll('textarea, [data-custom-name], input[type="checkbox"]').forEach(function (field) {
        field.addEventListener('input', syncVariantsAutomatically);
        field.addEventListener('change', syncVariantsAutomatically);
      });
    }

    function variantHiddenInputs(index, variant) {
      return '<input type="hidden" name="product_variants[' + index + '][id]" value="' + escapeHtml(variant.id) + '">' +
        '<input type="hidden" name="product_variants[' + index + '][attributes_json]" value="' +
        escapeHtml(encodeURIComponent(JSON.stringify(variant.attributes || {}))) + '">';
    }

    function renderVariants() {
      const rows = variants.map(function (item, index) {
        const attributesText = Object.keys(item.attributes || {}).map(function (key) {
          return '<span class="admin-variants__value-tag">' + escapeHtml(key) + ': ' + escapeHtml(item.attributes[key]) + '</span>';
        }).join('');

        return '<tr>' +
          '<td>' + variantHiddenInputs(index, item) + '<input type="text" name="product_variants[' + index + '][label]" value="' + escapeHtml(item.label) + '" readonly></td>' +
          '<td><div class="admin-variants__value-list">' + attributesText + '</div></td>' +
          '<td><input type="number" step="0.01" min="0" name="product_variants[' + index + '][price]" value="' + escapeHtml(item.price) + '" placeholder="По умолчанию цена товара"></td>' +
          '<td><input type="number" min="0" name="product_variants[' + index + '][stock_quantity]" value="' + escapeHtml(item.stock_quantity) + '"></td>' +
          '<td class="admin-variants__actions-col"><button type="button" class="admin-text-btn danger" data-remove-variant="' + index + '">Удалить</button></td>' +
          '</tr>';
      }).join('');

      variantsContainer.innerHTML =
        '<div class="admin-variants__toolbar">' +
        '<button type="button" class="admin-link-btn" data-add-variant>Добавить вариант вручную</button>' +
        '</div>' +
        '<table class="admin-table admin-variants__table-ui">' +
        '<thead><tr><th>Комбинация</th><th>Значения</th><th>Цена</th><th>Остаток</th><th></th></tr></thead>' +
        '<tbody>' + (rows || '<tr><td colspan="5">Варианты пока не сформированы.</td></tr>') + '</tbody>' +
        '</table>';

      variantsContainer.querySelector('[data-add-variant]').addEventListener('click', function () {
        variants.push({ id: '', label: '', price: '', stock_quantity: 0, attributes: {} });
        renderVariants();
      });

      variantsContainer.querySelectorAll('[data-remove-variant]').forEach(function (button) {
        button.addEventListener('click', function () {
          const index = parseInt(this.getAttribute('data-remove-variant'), 10);
          variants.splice(index, 1);
          renderVariants();
        });
      });
    }

    renderParameters();
    syncVariantsAutomatically();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.admin-variants').forEach(render);
  });
})();
