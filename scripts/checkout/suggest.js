import { debounce, escapeHtml } from './utils.js';

export function initDeliverySuggest({ input, container, getMapController }) {
  if (!input || !container) return;

  const loadSuggestions = debounce(async () => {
    const value = input.value.trim();
    if (value.length < 2) {
      container.style.display = 'none';
      container.innerHTML = '';
      return;
    }

    try {
      const controller = await getMapController();
      const suggestions = await controller.fetchSuggestions(value);
      if (!suggestions.length) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
      }

      container.innerHTML = suggestions.map((item) => {
        return '<div class="delivery-suggest-item" data-address="' + escapeHtml(item.address) + '">' +
          '<strong>' + escapeHtml(item.fullName) + '</strong>' +
          (item.description ? '<small>' + escapeHtml(item.description) + '</small>' : '') +
          '</div>';
      }).join('');

      container.querySelectorAll('.delivery-suggest-item').forEach((item) => {
        item.addEventListener('click', function () {
          input.value = this.getAttribute('data-address') || '';
          container.style.display = 'none';
        });
      });

      container.style.display = 'block';
    } catch (e) {
      container.style.display = 'none';
    }
  }, 250);

  input.addEventListener('input', loadSuggestions);
  input.addEventListener('focus', loadSuggestions);
  input.addEventListener('blur', function () {
    setTimeout(() => {
      container.style.display = 'none';
    }, 200);
  });

  document.addEventListener('click', function (event) {
    if (!input.contains(event.target) && !container.contains(event.target)) {
      container.style.display = 'none';
    }
  });
}
