(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.querySelector('.scroll-top-btn');
        if (!btn) {
            return;
        }

        var threshold = 300;

        function onScroll() {
            if (window.scrollY > threshold) {
                btn.classList.add('is-visible');
            } else {
                btn.classList.remove('is-visible');
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
})();
