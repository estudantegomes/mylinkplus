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
    const foldersList = document.getElementById('folders-list');
    const folderForm = document.getElementById('new-folder-form');
    
    if (!foldersList || !folderForm) return;

    const folderModalEl = document.getElementById('new-folder-modal');
    const folderModal = bootstrap.Modal.getOrCreateInstance(folderModalEl);
    const breadcrumbsEl = document.getElementById('folder-breadcrumbs');
    
    let breadcrumbPath = [{ id: null, name: 'Home' }];
    
    const getCurrentParentId = () => breadcrumbPath[breadcrumbPath.length - 1].id;

    const renderBreadcrumbs = () => {
        breadcrumbsEl.innerHTML = breadcrumbPath.map((crumb, index) => {
            if (index === breadcrumbPath.length - 1) {
                return `<li class="breadcrumb-item active" aria-current="page">${crumb.name}</li>`;
            }
            return `<li class="breadcrumb-item"><a href="#" class="text-decoration-none crumb-link" data-index="${index}">${crumb.name}</a></li>`;
        }).join('');
    };

    const loadFolders = async () => {
        const parentId = getCurrentParentId();
        const url = parentId ? `/folders?parent_id=${parentId}` : '/folders';
        
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error('Erro ao buscar pastas');
            const folders = await res.json();
            renderFolders(folders);
            renderBreadcrumbs();
        } catch (error) {
            console.error(error);
        }
    };

    const renderFolders = (folders) => {
        if (folders.length === 0) {
            foldersList.innerHTML = '<p class="small text-secondary mb-0 w-100" id="folders-empty-msg">Nenhuma pasta por enquanto.</p>';
            return;
        }

        foldersList.innerHTML = folders.map(folder => `
            <div class="d-flex align-items-center justify-content-between border rounded p-2 folder-item" style="border-left: 4px solid ${folder.color || '#507cf3'} !important; min-width: 220px; cursor: pointer;" data-id="${folder.id}" data-name="${folder.name}">
                <div class="d-flex align-items-center gap-2 flex-grow-1 open-folder">
                    <i class="bi bi-folder-fill" style="color: ${folder.color || '#507cf3'};"></i>
                    <span class="fw-semibold small text-truncate" style="max-width: 120px;">${folder.name}</span>
                </div>
                <div class="d-flex gap-2 ms-3">
                    <button class="btn btn-sm btn-link text-secondary p-0 edit-folder" data-id="${folder.id}" data-name="${folder.name}" data-color="${folder.color}" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-danger p-0 delete-folder" data-id="${folder.id}" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    };

    // Navegar para dentro da pasta
    foldersList.addEventListener('click', (e) => {
        const folderEl = e.target.closest('.open-folder');
        if (!folderEl) return;
        
        const item = folderEl.closest('.folder-item');
        breadcrumbPath.push({ id: item.dataset.id, name: item.dataset.name });
        loadFolders();
    });

    // Navegar no Breadcrumb
    breadcrumbsEl.addEventListener('click', (e) => {
        e.preventDefault();
        if (e.target.classList.contains('crumb-link')) {
            const index = parseInt(e.target.dataset.index, 10);
            breadcrumbPath = breadcrumbPath.slice(0, index + 1);
            loadFolders();
        }
    });

    // Abrir Modal de Nova Pasta
    document.getElementById('btn-new-folder').addEventListener('click', () => {
        folderForm.reset();
        document.getElementById('folder-id').value = '';
        document.getElementById('folder-parent-id').value = getCurrentParentId() || '';
        document.getElementById('new-folder-title').textContent = 'Criar Pasta';
        folderModal.show();
    });

    // Abrir Modal de Edição
    foldersList.addEventListener('click', (e) => {
        const btn = e.target.closest('.edit-folder');
        if (!btn) return;
        
        document.getElementById('folder-id').value = btn.dataset.id;
        document.getElementById('folder-parent-id').value = getCurrentParentId() || '';
        document.getElementById('folder-name').value = btn.dataset.name;
        document.getElementById('folder-color').value = btn.dataset.color;
        document.getElementById('new-folder-title').textContent = 'Editar Pasta';
        
        folderModal.show();
    });

    // Salvar Pasta (Criar ou Atualizar)
    folderForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(folderForm);
        const folderId = document.getElementById('folder-id').value;
        const method = folderId ? 'PUT' : 'POST';
        const url = folderId ? `/folders/${folderId}` : '/folders';

        // Fetch não envia body no PUT com FormData facilmente nativo do PHP, então usamos URLSearchParams
        const data = new URLSearchParams(formData);

        try {
            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            });

            if (res.ok) {
                folderModal.hide();
                loadFolders();
            } else {
                alert('Erro ao salvar pasta. Verifique os dados.');
            }
        } catch (error) {
            console.error(error);
        }
    });

    // Excluir pasta
    foldersList.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-folder');
        if (!btn) return;

        if (confirm('Tem certeza que deseja excluir esta pasta?')) {
            const id = btn.dataset.id;
            try {
                const res = await fetch(`/folders/${id}`, { method: 'DELETE' });
                if (res.ok) {
                    loadFolders();
                } else {
                    alert('Erro ao excluir pasta.');
                }
            } catch (error) {
                console.error(error);
            }
        }
    });

    loadFolders();
})();