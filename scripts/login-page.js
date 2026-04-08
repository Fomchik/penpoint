(function () {
    'use strict';

    function validatePasswordStrength(password) {
        if (password.length < 8) return false;
        if (!/\p{Lu}/u.test(password)) return false;
        if (!/[0-9]/.test(password)) return false;
        if (!/[^\p{L}\p{N}]/u.test(password)) return false;
        return true;
    }

    function ensureNoValidateOnAllForms() {
        document.querySelectorAll('form').forEach(function (form) {
            form.setAttribute('novalidate', 'novalidate');
        });
    }

    function showForm(formId) {
        document.querySelectorAll('.login-page__form').forEach(function (f) {
            f.classList.remove('login-page__form--active');
        });

        const target = document.getElementById(formId);
        if (target) {
            target.classList.add('login-page__form--active');
        }
    }

    function activateTab(tabName) {
        document.querySelectorAll('.login-page__tab').forEach(function (t) {
            t.classList.toggle('login-page__tab--active', t.getAttribute('data-tab') === tabName);
        });
    }

    function clearFieldError(input) {
        if (!input) return;
        input.classList.remove('error');
        const field = input.closest('.login-page__field');
        if (!field) return;
        const errorEl = field.querySelector('.login-page__field-error');
        if (errorEl) {
            errorEl.textContent = '';
        }
    }

    function setFieldError(input, message) {
        if (!input) return;
        input.classList.add('error');
        const field = input.closest('.login-page__field');
        if (!field) return;

        let errorEl = field.querySelector('.login-page__field-error');
        if (!errorEl) {
            errorEl = document.createElement('small');
            errorEl.className = 'login-page__field-error';
            field.appendChild(errorEl);
        }
        errorEl.textContent = message;
    }

    function clearFormErrors(form) {
        if (!form) return;
        form.querySelectorAll('.login-page__input.error').forEach(function (input) {
            input.classList.remove('error');
        });
        form.querySelectorAll('.login-page__field-error').forEach(function (el) {
            el.textContent = '';
        });
    }

    function validateRegisterForm(form) {
        const nameInput = form.querySelector('input[name="name"]');
        const emailInput = form.querySelector('input[name="email"]');
        const passwordInput = form.querySelector('input[name="password"]');
        const confirmInput = form.querySelector('input[name="password_confirm"]');

        let isValid = true;

        if (!nameInput || nameInput.value.trim() === '') {
            setFieldError(nameInput, 'Введите имя.');
            isValid = false;
        }

        const emailValue = emailInput ? emailInput.value.trim() : '';
        if (!emailInput || emailValue === '') {
            setFieldError(emailInput, 'Введите email.');
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
            setFieldError(emailInput, 'Введите корректный email.');
            isValid = false;
        }

        const password = passwordInput ? passwordInput.value : '';
        const confirm = confirmInput ? confirmInput.value : '';

        if (!passwordInput || password === '') {
            setFieldError(passwordInput, 'Введите пароль.');
            isValid = false;
        } else if (!validatePasswordStrength(password)) {
            setFieldError(passwordInput, 'Минимум 8 символов, 1 заглавная буква, 1 цифра и 1 спецсимвол.');
            isValid = false;
        }

        if (!confirmInput || confirm === '') {
            setFieldError(confirmInput, 'Подтвердите пароль.');
            isValid = false;
        } else if (password !== confirm) {
            setFieldError(confirmInput, 'Пароли не совпадают.');
            isValid = false;
        }

        return isValid;
    }

    function validateSetPasswordForm(form) {
        const passwordInput = form.querySelector('input[name="new_password"]');
        const confirmInput = form.querySelector('input[name="new_password_confirm"]');

        let isValid = true;
        const password = passwordInput ? passwordInput.value : '';
        const confirm = confirmInput ? confirmInput.value : '';

        if (!passwordInput || password === '') {
            setFieldError(passwordInput, 'Введите новый пароль.');
            isValid = false;
        } else if (!validatePasswordStrength(password)) {
            setFieldError(passwordInput, 'Минимум 8 символов, 1 заглавная буква, 1 цифра и 1 спецсимвол.');
            isValid = false;
        }

        if (!confirmInput || confirm === '') {
            setFieldError(confirmInput, 'Подтвердите новый пароль.');
            isValid = false;
        } else if (password !== confirm) {
            setFieldError(confirmInput, 'Пароли не совпадают.');
            isValid = false;
        }

        return isValid;
    }

    function bindLiveCleanup(form) {
        if (!form) return;
        form.querySelectorAll('.login-page__input').forEach(function (input) {
            input.addEventListener('input', function () {
                if (form.classList.contains('login-page__form--submitted')) {
                    clearFieldError(input);
                }
            });
        });
    }

    function initPasswordToggles() {
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                const field = button.closest('.login-page__field');
                const input = field ? field.querySelector('input[type="password"], input[type="text"]') : null;
                if (!input) return;

                const shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';
                button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                button.setAttribute('aria-label', shouldShow ? 'Скрыть пароль' : 'Показать пароль');
            });
        });
    }

    function init() {
        ensureNoValidateOnAllForms();

        document.querySelectorAll('.login-page__tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const tabName = this.getAttribute('data-tab');
                activateTab(tabName);
                showForm(tabName + '-form');
            });
        });

        const showResetBtn = document.getElementById('show-reset-form');
        if (showResetBtn) {
            showResetBtn.addEventListener('click', function () {
                activateTab('');
                showForm('reset-form');
            });
        }

        const backToLoginBtn = document.getElementById('back-to-login');
        if (backToLoginBtn) {
            backToLoginBtn.addEventListener('click', function () {
                activateTab('login');
                showForm('login-form');
            });
        }

        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            bindLiveCleanup(registerForm);
            registerForm.addEventListener('submit', function (event) {
                registerForm.classList.add('login-page__form--submitted');
                clearFormErrors(registerForm);
                if (!validateRegisterForm(registerForm)) {
                    event.preventDefault();
                }
            });
        }

        const setPasswordForm = document.getElementById('set-password-form');
        if (setPasswordForm) {
            bindLiveCleanup(setPasswordForm);
            setPasswordForm.addEventListener('submit', function (event) {
                setPasswordForm.classList.add('login-page__form--submitted');
                clearFormErrors(setPasswordForm);
                if (!validateSetPasswordForm(setPasswordForm)) {
                    event.preventDefault();
                }
            });
        }

        initPasswordToggles();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
