document.addEventListener('DOMContentLoaded', () => {
    // Micro-interactions for buttons
    document.querySelectorAll('button, .btn').forEach(button => {
        button.addEventListener('mousedown', () => {
            button.classList.add('scale-95');
        });
        button.addEventListener('mouseup', () => {
            button.classList.remove('scale-95');
        });
        button.addEventListener('mouseleave', () => {
            button.classList.remove('scale-95');
        });
    });

    // Input focus icon color transition
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            const container = input.parentElement;
            if (container) {
                const icon = container.querySelector('.material-symbols-outlined');
                if (icon) {
                    icon.style.color = '#ff9f0d';
                    icon.style.transition = 'color 0.3s ease';
                }
            }
        });

        input.addEventListener('blur', () => {
            const container = input.parentElement;
            if (container) {
                const icon = container.querySelector('.material-symbols-outlined');
                if (icon) {
                    icon.style.color = '';
                }
            }
        });
    });

    // Gentle parallax effect for background images
    window.addEventListener('mousemove', (e) => {
        const moveX = (e.clientX - window.innerWidth / 2) * 0.005;
        const moveY = (e.clientY - window.innerHeight / 2) * 0.005;
        const bg = document.querySelector('.parallax-bg, .bg-cover');
        if (bg) {
            bg.style.transform = `scale(1.05) translate(${moveX}px, ${moveY}px)`;
        }
    });

    // SPA Dynamic Tab Switching Logic (User Home Page)
    const exploreToggleBtns = document.querySelectorAll('[data-tab-target]');
    const tabContainers = {
        'dishes': document.getElementById('tab-dishes'),
        'restaurants': document.getElementById('tab-restaurants'),
        'search-results': document.getElementById('tab-search-results'),
        'no-results': document.getElementById('tab-no-results'),
        'loading': document.getElementById('tab-loading')
    };

    let activeTabId = 'dishes';

    function showContainer(containerId) {
        Object.values(tabContainers).forEach(container => {
            if (container) container.classList.add('d-none');
        });
        if (tabContainers[containerId]) {
            tabContainers[containerId].classList.remove('d-none');
        }
    }

    if (exploreToggleBtns.length > 0) {
        exploreToggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = btn.getAttribute('data-tab-target');
                if (activeTabId === targetId && !tabContainers['search-results']?.classList.contains('d-none') === false && !tabContainers['no-results']?.classList.contains('d-none') === false) {
                    return;
                }
                activeTabId = targetId;

                // Update active pill styling
                exploreToggleBtns.forEach(b => {
                    b.classList.remove('btn-primary-custom', 'active-pill', 'rounded-2');
                    b.classList.add('btn-link', 'text-on-surface-variant');
                });
                btn.classList.remove('btn-link', 'text-on-surface-variant');
                btn.classList.add('btn-primary-custom', 'active-pill', 'rounded-2');

                // Clear search box if any
                const searchBox = document.getElementById('main-search-box');
                if (searchBox) searchBox.value = '';

                // Simulate loading state for 250ms
                showContainer('loading');
                setTimeout(() => {
                    showContainer(targetId);
                }, 250);
            });
        });
    }

    // Live Search Filtering & Empty States Simulation
    const mainSearchBox = document.getElementById('main-search-box');
    if (mainSearchBox) {
        let searchTimeout;
        mainSearchBox.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim().toLowerCase();

            if (query === '') {
                showContainer(activeTabId);
                return;
            }

            showContainer('loading');
            searchTimeout = setTimeout(() => {
                if (query.includes('pizza') || query.includes('artisan') || query.includes('crust')) {
                    showContainer('search-results');
                } else {
                    showContainer('no-results');
                }
            }, 300);
        });
    }
});
