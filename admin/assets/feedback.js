(function() {
    const showBtn = document.getElementById('show-reply-form');
    const hideBtn = document.getElementById('hide-reply-form');
    const panel = document.getElementById('reply-panel');

    if (showBtn && panel) {
        showBtn.onclick = function(e) {
            e.preventDefault();
            panel.classList.add('is-visible');
            showBtn.classList.add('is-hidden');
        };
    }

    if (hideBtn && panel) {
        hideBtn.onclick = function() {
            panel.classList.remove('is-visible');
            showBtn.classList.remove('is-hidden');
        };
    }
})();