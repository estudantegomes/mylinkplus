(() => {
    const form = document.getElementById('new-link-form');
    const nameInput = document.getElementById('link-name');
    const urlInput = document.getElementById('link-url');
    const search = document.getElementById('link-search');
    const tbody = document.getElementById('links-table-body');

    let count = 0;

    function filterLinks() {
        const query = search.value.trim().toLocaleLowerCase('pt-BR');
        let visibleCount = 0;

        for (const row of tbody.rows) {
            const text = row.textContent.toLocaleLowerCase('pt-BR');

            row.hidden = !text.includes(query);

            if (!row.hidden) {
                visibleCount++;
            }
        }

        document.getElementById('links-no-results').hidden =
            count === 0 || visibleCount > 0;
    }

    search.addEventListener('input', filterLinks);

    nameInput.addEventListener('input', () => {
        nameInput.setCustomValidity('');
    });

    urlInput.addEventListener('input', () => {
        urlInput.setCustomValidity('');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const name = nameInput.value.trim();

        if (!name) {
            nameInput.setCustomValidity('Informe um nome.');
            nameInput.reportValidity();
            return;
        }

        let url;

        try {
            url = new URL(urlInput.value);
        } catch {
            urlInput.setCustomValidity('Informe uma URL válida.');
            urlInput.reportValidity();
            return;
        }

        if (!['http:', 'https:'].includes(url.protocol)) {
            urlInput.setCustomValidity('Use http:// ou https://.');
            urlInput.reportValidity();
            return;
        }

        const row = tbody.insertRow(0);

        const nameCell = row.insertCell();
        nameCell.textContent = name;
        nameCell.className = 'ps-4 py-3 fw-medium';

        const destinationCell = row.insertCell();
        const link = document.createElement('a');

        link.href = url.href;
        link.textContent = url.hostname;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';

        destinationCell.appendChild(link);

        const dateCell = row.insertCell();
        dateCell.textContent = new Date().toLocaleDateString('pt-BR');
        dateCell.className = 'pe-4 text-secondary small text-nowrap';

        count++;

        document.getElementById('link-count').textContent = count;
        document.getElementById('links-empty').hidden = true;
        document.getElementById('links-table-wrapper').hidden = false;

        form.reset();
        filterLinks();

        const modal = document.getElementById('new-link-modal');
        bootstrap.Modal.getOrCreateInstance(modal).hide();
    });
})();

(() => {
    const sidebar = document.getElementById('mylink-sidebar');
    const navigation = sidebar.querySelector('nav');
    const feedback = document.getElementById('feature-feedback');

    const features = {
        qrcodes: {
            title: 'QR codes',
            description: 'A geração de QR codes está em desenvolvimento.'
        },
        analises: {
            title: 'Análises',
            description: 'As estatísticas de acesso estarão disponíveis em breve.'
        },
        configuracoes: {
            title: 'Configurações',
            description: 'As configurações da conta estão em desenvolvimento.'
        }
    };

    function activateMenu(item) {
        navigation.querySelectorAll('.nav-link').forEach((link) => {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        });

        item.classList.add('active');
        item.setAttribute('aria-current', 'true');
    }

    function closeSidebar() {
        // No celular, fecha o menu depois da escolha.
        const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);

        if (offcanvas) {
            offcanvas.hide();
        }
    }

    document.getElementById('menu-links').addEventListener('click', (event) => {
        event.preventDefault();

        activateMenu(event.currentTarget);
        feedback.hidden = true;
        closeSidebar();

        document.getElementById('links-title').scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'auto'
                : 'smooth',
            block: 'start'
        });
    });

    navigation.querySelectorAll('[data-feature]').forEach((button) => {
        button.addEventListener('click', () => {
            const feature = features[button.dataset.feature];

            activateMenu(button);

            document.getElementById('feature-title').textContent =
                feature.title;

            document.getElementById('feature-description').textContent =
                feature.description;

            feedback.hidden = false;
            closeSidebar();

            feedback.scrollIntoView({
                behavior: 'auto',
                block: 'nearest'
            });
        });
    });

    // Revalida a sessão ao voltar para uma Home restaurada pelo navegador.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });
})();