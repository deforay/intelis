(function () {
    let lastActive = null;

    const qs = (id) => document.getElementById(id);

    function lockScroll() { document.body.style.overflow = 'hidden'; }
    function unlockScroll() { document.body.style.overflow = ''; }

    function displayDeforayModal(_, w, h) {
        const modalWrapper = qs('dDiv');
        if (!modalWrapper) return;
        modalWrapper.removeAttribute('hidden');
        modalWrapper.classList.add('is-open');
        lockScroll();

        const modal = modalWrapper.querySelector('.dfy-modal');
        const iframe = qs('dFrame');

        if (w) modal.style.width = Math.min(window.innerWidth * 0.95, parseInt(w, 10)) + 'px';
        if (h) modal.style.height = Math.min(window.innerHeight * 0.9, parseInt(h, 10)) + 'px';

        lastActive = document.activeElement;
        document.addEventListener('keydown', escHandler);
        modalWrapper.addEventListener('mousedown', overlayClick);
    }

    function removeDeforayModal() {
        const modalWrapper = qs('dDiv');
        if (!modalWrapper) return;
        modalWrapper.classList.remove('is-open');
        modalWrapper.setAttribute('hidden', '');
        unlockScroll();

        const iframe = qs('dFrame');
        if (iframe) iframe.src = '';

        document.removeEventListener('keydown', escHandler);
        modalWrapper.removeEventListener('mousedown', overlayClick);

        if (lastActive && typeof lastActive.focus === 'function') {
            lastActive.focus({ preventScroll: true });
        }
    }

    function escHandler(e) {
        if (e.key === 'Escape') removeDeforayModal();
    }

    function overlayClick(e) {
        const modal = e.currentTarget.querySelector('.dfy-modal');
        if (!modal.contains(e.target)) removeDeforayModal();
    }

    window.displayDeforayModal = displayDeforayModal;
    window.removeDeforayModal = removeDeforayModal;

    window.showModal = function (url, w, h) {
        displayDeforayModal('dDiv', w, h);
        const iframe = qs('dFrame');
        const fallback = qs('dfy-modal-fallback');
        if (!iframe) return;

        fallback.hidden = true;
        iframe.onload = () => (fallback.hidden = true);
        iframe.onerror = () => (fallback.hidden = false);
        iframe.src = url;
    };

    window.closeModal = removeDeforayModal;
})();
