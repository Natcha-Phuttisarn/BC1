<?php
if (!empty($bcConfirmationModalRendered)) {
    return;
}
$bcConfirmationModalRendered = true;
?>

<div
    class="bc-confirm-overlay"
    id="bcConfirmationModal"
    aria-hidden="true"
    hidden
>
    <section
        class="bc-confirm-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bcConfirmationTitle"
        aria-describedby="bcConfirmationMessage"
        tabindex="-1"
    >
        <button
            type="button"
            class="bc-confirm-close"
            data-bc-confirm-close
            aria-label="ปิดหน้าต่างยืนยัน"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>

        <div class="bc-confirm-icon" aria-hidden="true">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h2 id="bcConfirmationTitle">ยืนยันการดำเนินการ</h2>
        <p id="bcConfirmationMessage"></p>

        <div class="bc-confirm-actions">
            <button
                type="button"
                class="bc-confirm-cancel"
                data-bc-confirm-close
            >
                ยกเลิก
            </button>
            <button
                type="button"
                class="bc-confirm-submit"
                id="bcConfirmationSubmit"
            >
                ยืนยัน
            </button>
        </div>
    </section>
</div>

<style>
.bc-confirm-overlay[hidden] {
    display: none !important;
}

.bc-confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 12000;
    padding: 20px;
    display: grid;
    place-items: center;
    background: rgba(8, 20, 39, .56);
    backdrop-filter: blur(2px);
}

.bc-confirm-dialog {
    width: min(100%, 440px);
    position: relative;
    padding: 30px 28px 26px;
    border: 1px solid #e1e7ef;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 24px 65px rgba(8, 28, 57, .24);
    text-align: center;
    outline: none;
    animation: bcConfirmEnter .18s ease-out;
}

.bc-confirm-close {
    width: 34px;
    height: 34px;
    position: absolute;
    top: 12px;
    right: 12px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 8px;
    color: #718096;
    background: transparent;
    font: inherit;
    cursor: pointer;
}

.bc-confirm-close:hover,
.bc-confirm-close:focus-visible {
    color: #1d3658;
    background: #f0f4f8;
}

.bc-confirm-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 17px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #1768cf;
    background: #e9f3ff;
    font-size: 24px;
}

.bc-confirm-dialog h2 {
    margin: 0;
    color: #17365f;
    font-size: 19px;
    font-weight: 600;
    line-height: 1.4;
}

.bc-confirm-dialog p {
    margin: 9px auto 0;
    color: #697b91;
    font-size: 14px;
    line-height: 1.75;
    white-space: pre-line;
}

.bc-confirm-actions {
    margin-top: 23px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.bc-confirm-cancel,
.bc-confirm-submit {
    min-height: 43px;
    padding: 8px 15px;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.bc-confirm-cancel {
    border: 1px solid #dce4ed;
    color: #526780;
    background: #f4f7fa;
}

.bc-confirm-cancel:hover,
.bc-confirm-cancel:focus-visible {
    background: #eaf0f5;
}

.bc-confirm-submit {
    border: 1px solid transparent;
    color: #ffffff;
    background: #1768cf;
}

.bc-confirm-submit:hover,
.bc-confirm-submit:focus-visible {
    background: #1057ad;
}

.bc-confirm-submit:disabled {
    opacity: .62;
    cursor: wait;
}

.bc-confirm-overlay[data-type="delete"] .bc-confirm-icon,
.bc-confirm-overlay[data-type="reject"] .bc-confirm-icon {
    color: #c93646;
    background: #ffebee;
}

.bc-confirm-overlay[data-type="delete"] .bc-confirm-submit,
.bc-confirm-overlay[data-type="reject"] .bc-confirm-submit {
    background: #d83b4b;
}

.bc-confirm-overlay[data-type="delete"] .bc-confirm-submit:hover,
.bc-confirm-overlay[data-type="reject"] .bc-confirm-submit:hover {
    background: #bb2d3c;
}

.bc-confirm-overlay[data-type="archive"] .bc-confirm-icon,
.bc-confirm-overlay[data-type="warning"] .bc-confirm-icon {
    color: #b76a10;
    background: #fff2df;
}

.bc-confirm-overlay[data-type="archive"] .bc-confirm-submit,
.bc-confirm-overlay[data-type="warning"] .bc-confirm-submit {
    background: #c77a1d;
}

.bc-confirm-overlay[data-type="archive"] .bc-confirm-submit:hover,
.bc-confirm-overlay[data-type="warning"] .bc-confirm-submit:hover {
    background: #a96110;
}

.bc-confirm-overlay[data-type="approve"] .bc-confirm-icon {
    color: #16805b;
    background: #e6f7ef;
}

.bc-confirm-overlay[data-type="approve"] .bc-confirm-submit {
    background: #16805b;
}

.bc-confirm-overlay[data-type="approve"] .bc-confirm-submit:hover {
    background: #106a4a;
}

body.bc-confirm-open {
    overflow: hidden;
}

@keyframes bcConfirmEnter {
    from {
        opacity: 0;
        transform: translateY(8px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (max-width: 480px) {
    .bc-confirm-overlay {
        padding: 14px;
    }

    .bc-confirm-dialog {
        padding: 28px 18px 20px;
    }

    .bc-confirm-actions {
        gap: 8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bc-confirm-dialog {
        animation: none;
    }
}
</style>

<script>
(function () {
    'use strict';

    if (window.bcConfirmationModalReady) {
        return;
    }
    window.bcConfirmationModalReady = true;

    const overlay = document.getElementById('bcConfirmationModal');
    const dialog = overlay ? overlay.querySelector('.bc-confirm-dialog') : null;
    const title = document.getElementById('bcConfirmationTitle');
    const message = document.getElementById('bcConfirmationMessage');
    const icon = overlay ? overlay.querySelector('.bc-confirm-icon i') : null;
    const submitButton = document.getElementById('bcConfirmationSubmit');
    const closeButtons = overlay
        ? overlay.querySelectorAll('[data-bc-confirm-close]')
        : [];

    if (!overlay || !dialog || !title || !message || !icon || !submitButton) {
        return;
    }

    const typeIcons = {
        delete: 'fa-trash-can',
        reject: 'fa-circle-xmark',
        archive: 'fa-box-archive',
        warning: 'fa-triangle-exclamation',
        approve: 'fa-circle-check',
        info: 'fa-circle-check'
    };

    let activeForm = null;
    let activeSubmitter = null;
    let returnFocus = null;
    let bypassForm = null;
    let submitting = false;

    function confirmationSource(form, submitter) {
        if (submitter && submitter.matches('[data-bc-confirm]')) {
            return submitter;
        }
        return form.matches('[data-bc-confirm]') ? form : null;
    }

    function closeModal(restoreFocus, keepSubmitting) {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bc-confirm-open');

        if (restoreFocus && returnFocus && document.contains(returnFocus)) {
            returnFocus.focus();
        }

        activeForm = null;
        activeSubmitter = null;
        returnFocus = null;
        if (!keepSubmitting) {
            submitting = false;
            submitButton.disabled = false;
        }
    }

    function openModal(source, form, submitter) {
        const type = source.dataset.bcConfirmType || 'info';
        activeForm = form;
        activeSubmitter = submitter;
        returnFocus = submitter || document.activeElement;
        submitting = false;

        overlay.dataset.type = type;
        title.textContent = source.dataset.bcConfirmTitle || 'ยืนยันการดำเนินการ';
        message.textContent = source.dataset.bcConfirmMessage || '';
        submitButton.textContent = source.dataset.bcConfirmLabel || 'ยืนยัน';
        submitButton.disabled = false;

        icon.className = 'fa-solid ' + (typeIcons[type] || typeIcons.info);
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('bc-confirm-open');

        window.requestAnimationFrame(function () {
            const cancelButton = overlay.querySelector('.bc-confirm-cancel');
            (cancelButton || dialog).focus();
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (bypassForm === form) {
            bypassForm = null;
            return;
        }

        const submitter = event.submitter || null;
        const source = confirmationSource(form, submitter);
        if (!source) {
            return;
        }

        event.preventDefault();
        openModal(source, form, submitter);
    });

    submitButton.addEventListener('click', function () {
        if (submitting || !activeForm) {
            return;
        }

        submitting = true;
        submitButton.disabled = true;

        const form = activeForm;
        const submitter = activeSubmitter;
        bypassForm = form;
        closeModal(false, true);

        window.requestAnimationFrame(function () {
            if (submitter) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }

            window.setTimeout(function () {
                if (bypassForm === form) {
                    bypassForm = null;
                }
                submitting = false;
                submitButton.disabled = false;
            }, 0);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(true);
        });
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal(true);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (overlay.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal(true);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(dialog.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return !element.hidden && element.offsetParent !== null;
        });

        if (!focusable.length) {
            event.preventDefault();
            dialog.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
</script>
