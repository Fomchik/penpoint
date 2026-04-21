(function () {
  "use strict";

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getValueWord(count) {
    if (count % 10 === 1 && count % 100 !== 11) return "значение";
    if (
      count % 10 >= 2 &&
      count % 10 <= 4 &&
      (count % 100 < 10 || count % 100 >= 20)
    ) {
      return "значения";
    }
    return "значений";
  }

  function normalizeParam(raw) {
    const param = raw && typeof raw === "object" ? raw : {};
    const values = Array.isArray(param.values) ? param.values : [];

    return {
      name: String(param.name || ""),
      values:
        values.length > 0
          ? values.map((item) => ({
              name: String(item.name || ""),
              price: item.price ?? "",
              stock: item.stock ?? "",
              image_url: String(item.image_url || item.image_path || ""),
              image_preview: String(item.image_url || item.image_path || ""),
              image_file: null,
            }))
          : [{ name: "", price: "", stock: "", image_url: "", image_preview: "", image_file: null }],
    };
  }

  function createValueRow(param, pIdx, vIdx, onRemove) {
    const val = param.values[vIdx];
    const row = document.createElement("div");
    row.className = "admin-value-row";

    row.innerHTML = `
      <div class="admin-value-image">
        <label class="admin-image-upload-zone">
          ${
            (val.image_preview || val.image_url)
              ? `<img src="${escapeHtml(val.image_preview || val.image_url)}" alt="">`
              : `<span class="admin-upload-placeholder">+</span>`
          }
          <input
            type="file"
            name="product_parameters[${pIdx}][values][${vIdx}][image]"
            accept="image/jpeg,image/png,image/webp"
            data-image-input
          >
          <input
            type="hidden"
            name="product_parameters[${pIdx}][values][${vIdx}][image_path]"
            value="${escapeHtml(val.image_url || '')}"
            data-image-path-input
          >
        </label>
      </div>
      <div class="admin-value-name">
        <input
          type="text"
          name="product_parameters[${pIdx}][values][${vIdx}][name]"
          value="${escapeHtml(val.name)}"
          placeholder="Например: Royal Blue"
          class="admin-value-input"
        >
      </div>
      <div class="admin-value-price">
        <input
          type="number"
          name="product_parameters[${pIdx}][values][${vIdx}][price]"
          value="${escapeHtml(val.price)}"
          step="0.01"
          placeholder="0"
          class="admin-value-input"
        >
      </div>
      <div class="admin-value-stock">
        <input
          type="number"
          name="product_parameters[${pIdx}][values][${vIdx}][stock]"
          value="${escapeHtml(val.stock)}"
          min="0"
          placeholder="0"
          class="admin-value-input"
        >
      </div>
      <div class="admin-value-action">
        <button
          type="button"
          class="admin-value-delete-btn"
          data-remove-value
          title="Удалить значение"
          aria-label="Удалить значение"
        >
          ×
        </button>
      </div>
    `;

    const nameInput = row.querySelector('.admin-value-name input');
    const priceInput = row.querySelector('.admin-value-price input');
    const stockInput = row.querySelector('.admin-value-stock input');

    nameInput.addEventListener("input", () => {
      val.name = nameInput.value;
    });
    priceInput.addEventListener("input", () => {
      val.price = priceInput.value;
    });
    stockInput.addEventListener("input", () => {
      val.stock = stockInput.value;
    });

    const fileInput = row.querySelector("[data-image-input]");
    if (val.image_file && typeof DataTransfer !== "undefined") {
      try {
        const transfer = new DataTransfer();
        transfer.items.add(val.image_file);
        fileInput.files = transfer.files;
      } catch (error) {
        console.warn("Cannot restore selected file for value row:", error);
      }
    }

    fileInput.addEventListener("change", (event) => {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      val.image_file = file;

      const reader = new FileReader();
      reader.onload = (loadEvent) => {
        const src = String((loadEvent.target && loadEvent.target.result) || "");
        if (!src) return;
        val.image_preview = src;
        const zone = row.querySelector(".admin-image-upload-zone");
        const preview = zone.querySelector("img");
        const placeholder = zone.querySelector(".admin-upload-placeholder");

        if (placeholder) {
          placeholder.remove();
        }

        if (preview) {
          preview.src = src;
        } else {
          const img = document.createElement("img");
          img.src = src;
          img.alt = "";
          zone.insertBefore(img, fileInput);
        }
      };
      reader.readAsDataURL(file);
    });

    row.querySelector("[data-remove-value]").addEventListener("click", onRemove);

    return row;
  }

  function createParamCard(pIdx, param, onParamRemove, onParamChanged, isInitiallyExpanded) {
    const card = document.createElement("div");
    card.className = "admin-param-card";

    card.innerHTML = `
      <div class="admin-param-composite">
        <div class="admin-param-sidebar" data-accordion-trigger role="button" aria-expanded="false">
          <div class="admin-param-sidebar-top">
            <span class="accordion-icon" aria-hidden="true"></span>
            <input
              type="text"
              name="product_parameters[${pIdx}][name]"
              value="${escapeHtml(param.name)}"
              placeholder="Название параметра"
              class="admin-param-name-input"
            >
          </div>
          <button
            type="button"
            class="admin-param-delete-btn"
            data-remove-param
            title="Удалить группу"
          >
            Удалить группу
          </button>
        </div>

        <div class="admin-param-content">
          <div class="admin-param-values-header">
            <div class="admin-values-grid-header">
              <div class="admin-col-image"></div>
              <div class="admin-col-name">Значение</div>
              <div class="admin-col-price">Цена</div>
              <div class="admin-col-stock">Остаток</div>
              <div class="admin-col-action"></div>
            </div>
          </div>
          <div class="admin-param-values-list" data-values-container></div>
          <div class="admin-param-footer">
            <button type="button" class="admin-add-value-btn" data-add-value>+ Добавить значение</button>
            <span class="admin-values-counter" data-count-badge></span>
          </div>
        </div>
      </div>
    `;

    const sidebar = card.querySelector("[data-accordion-trigger]");
    const paramNameInput = card.querySelector(".admin-param-name-input");
    const valuesContainer = card.querySelector("[data-values-container]");
    const countBadge = card.querySelector("[data-count-badge]");

    const setExpanded = (expanded) => {
      card.classList.toggle("is-expanded", expanded);
      sidebar.setAttribute("aria-expanded", expanded ? "true" : "false");
    };

    setExpanded(Boolean(isInitiallyExpanded));

    sidebar.addEventListener("click", (event) => {
      if (event.target.closest("input") || event.target.closest("button")) return;
      setExpanded(!card.classList.contains("is-expanded"));
    });

    paramNameInput.addEventListener("input", () => {
      param.name = paramNameInput.value;
    });

    const renderValues = () => {
      valuesContainer.innerHTML = "";
      countBadge.textContent = `${param.values.length} ${getValueWord(param.values.length)}`;

      param.values.forEach((_, vIdx) => {
        const row = createValueRow(param, pIdx, vIdx, () => {
          param.values.splice(vIdx, 1);
          renderValues();
          onParamChanged();
        });
        valuesContainer.appendChild(row);
      });
    };

    card.querySelector("[data-add-value]").addEventListener("click", () => {
      param.values.push({ name: "", price: "", stock: "", image_url: "", image_preview: "", image_file: null });
      renderValues();
      setExpanded(true);
      onParamChanged();
    });

    card.querySelector("[data-remove-param]").addEventListener("click", () => {
      onParamRemove();
      card.remove();
      onParamChanged();
    });

    renderValues();
    return card;
  }

  function reindexParameterNames(container) {
    const cards = container.querySelectorAll(".admin-param-card");

    cards.forEach((card, pIdx) => {
      card.querySelectorAll('[name^="product_parameters["]').forEach((field) => {
        const original = field.getAttribute("name") || "";
        const withParam = original.replace(
          /product_parameters\[\d+\]/,
          `product_parameters[${pIdx}]`,
        );
        field.setAttribute("name", withParam);
      });

      card.querySelectorAll(".admin-value-row").forEach((row, vIdx) => {
        row.querySelectorAll('[name^="product_parameters["]').forEach((field) => {
          const original = field.getAttribute("name") || "";
          const next = original
            .replace(/product_parameters\[\d+\]/, `product_parameters[${pIdx}]`)
            .replace(/\[values\]\[\d+\]/, `[values][${vIdx}]`);
          field.setAttribute("name", next);
        });
      });
    });
  }

  function init() {
    const root = document.querySelector(".admin-variants");
    if (!root) return;

    const container = root.querySelector("[data-parameters-container]");
    const addParamButton = document.getElementById("add-parameter");
    if (!container) return;

    let initialParams = [];
    try {
      initialParams = JSON.parse(root.dataset.initialParameters || "[]");
    } catch (error) {
      console.error("Failed to parse initial parameters:", error);
    }

    const state = {
      params: Array.isArray(initialParams)
        ? initialParams.map(normalizeParam)
        : [],
    };

    const repaint = () => {
      container.innerHTML = "";

      state.params.forEach((param, pIdx) => {
        const card = createParamCard(
          pIdx,
          param,
          () => {
            state.params.splice(pIdx, 1);
          },
          () => {
            reindexParameterNames(container);
          },
          param.values.length > 1,
        );
        container.appendChild(card);
      });

      reindexParameterNames(container);
    };

    repaint();

    if (addParamButton) {
      addParamButton.addEventListener("click", () => {
        state.params.push(
          normalizeParam({
            name: "",
            values: [{ name: "", price: "", stock: "", image_url: "", image_preview: "", image_file: null }],
          }),
        );
        repaint();
      });
    }
  }

  init();
})();
