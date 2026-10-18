/**
 * FileHub File Explorer Client Application
 */

// Theme management
const htmlElem = document.documentElement;
const themeToggleBtn = document.getElementById('themeToggleBtn');
const themeIconSun = document.getElementById('themeIconSun');
const themeIconMoon = document.getElementById('themeIconMoon');

function setTheme(theme) {
    htmlElem.setAttribute('data-theme', theme);
    localStorage.setItem('filehub_theme', theme);
    if (theme === 'dark') {
        if (themeIconSun) themeIconSun.style.display = 'block';
        if (themeIconMoon) themeIconMoon.style.display = 'none';
    } else {
        if (themeIconSun) themeIconSun.style.display = 'none';
        if (themeIconMoon) themeIconMoon.style.display = 'block';
    }
}

const savedTheme = localStorage.getItem('filehub_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
setTheme(savedTheme);

if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
        const currentTheme = htmlElem.getAttribute('data-theme') || 'light';
        setTheme(currentTheme === 'dark' ? 'light' : 'dark');
    });
}

// View toggle (Grid / List)
const viewGridBtn = document.getElementById('viewGridBtn');
const viewListBtn = document.getElementById('viewListBtn');
const gridContainer = document.getElementById('gridContainer');
const listContainer = document.getElementById('listContainer');

function setView(view) {
    if (view === 'list') {
        if (gridContainer) gridContainer.style.display = 'none';
        if (listContainer) listContainer.style.display = 'block';
        if (viewListBtn) viewListBtn.classList.add('active');
        if (viewGridBtn) viewGridBtn.classList.remove('active');
    } else {
        if (gridContainer) gridContainer.style.display = 'block';
        if (listContainer) listContainer.style.display = 'none';
        if (viewGridBtn) viewGridBtn.classList.add('active');
        if (viewListBtn) viewListBtn.classList.remove('active');
    }
    localStorage.setItem('filehub_view_mode', view);
}

const savedView = localStorage.getItem('filehub_view_mode') || 'grid';
setView(savedView);

if (viewGridBtn) viewGridBtn.addEventListener('click', () => setView('grid'));
if (viewListBtn) viewListBtn.addEventListener('click', () => setView('list'));

// Real-time Search and Type Filter
const searchInput = document.getElementById('searchInput');
const searchClear = document.getElementById('searchClear');
const noSearchResults = document.getElementById('noSearchResults');
const noResultsTitle = document.getElementById('noResultsTitle');
const noResultsDesc = document.getElementById('noResultsDesc');
const resetFilterSearchBtn = document.getElementById('resetFilterSearchBtn');
const typeFilterSelect = document.getElementById('typeFilterSelect');
const filterChips = document.querySelectorAll('.filter-chip');
const filterActiveStatus = document.getElementById('filterActiveStatus');
const filterStatusText = document.getElementById('filterStatusText');
const filterStatusClear = document.getElementById('filterStatusClear');
const foldersSectionHeader = document.getElementById('foldersSectionHeader');
const gridFolders = document.getElementById('gridFolders');
const filesSectionHeader = document.getElementById('filesSectionHeader');
const gridFiles = document.getElementById('gridFiles');

let currentTypeFilter = 'all';

function setTypeFilter(type) {
    currentTypeFilter = type || 'all';

    // Synchronize dropdown
    if (typeFilterSelect && typeFilterSelect.value !== currentTypeFilter) {
        const optionExists = Array.from(typeFilterSelect.options).some(opt => opt.value === currentTypeFilter);
        if (optionExists) {
            typeFilterSelect.value = currentTypeFilter;
        }
    }

    // Synchronize filter chips
    if (filterChips && filterChips.length > 0) {
        filterChips.forEach(chip => {
            if (chip.getAttribute('data-type') === currentTypeFilter) {
                chip.classList.add('active');
            } else {
                chip.classList.remove('active');
            }
        });
    }

    applyFiltersAndSearch();
}

function applyFiltersAndSearch() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const items = document.querySelectorAll('.file-item-searchable');
    let visibleTotal = 0;
    let visibleFolders = 0;
    let visibleFiles = 0;

    items.forEach(el => {
        const itemType = el.getAttribute('data-type') || '';
        const itemExt = (el.getAttribute('data-extension') || '').toLowerCase();
        const itemName = (el.getAttribute('data-name') || '').toLowerCase();

        // 1. Check type filter match
        let matchesType = false;
        if (currentTypeFilter === 'all') {
            matchesType = true;
        } else if (currentTypeFilter === 'folder') {
            matchesType = (itemType === 'folder');
        } else if (currentTypeFilter === 'archive') {
            matchesType = (itemType === 'archive' || ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'].includes(itemExt));
        } else if (currentTypeFilter === 'pdf') {
            matchesType = (itemType === 'pdf' || itemExt === 'pdf');
        } else if (currentTypeFilter === 'image') {
            matchesType = (itemType === 'image' || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif'].includes(itemExt));
        } else if (currentTypeFilter === 'text') {
            matchesType = (itemType === 'text');
        } else if (currentTypeFilter === 'audio') {
            matchesType = (itemType === 'audio');
        } else if (currentTypeFilter === 'video') {
            matchesType = (itemType === 'video');
        } else if (currentTypeFilter === 'file') {
            matchesType = (itemType === 'file');
        } else {
            matchesType = (itemType === currentTypeFilter || itemExt === currentTypeFilter);
        }

        // 2. Check search query match
        const matchesQuery = (query === '' || itemName.includes(query));

        if (matchesType && matchesQuery) {
            el.style.display = '';
            visibleTotal++;
            if (itemType === 'folder') {
                visibleFolders++;
            } else {
                visibleFiles++;
            }
        } else {
            el.style.display = 'none';
        }
    });

    // Toggle Grid Section Headers & Grid Containers
    if (foldersSectionHeader) {
        foldersSectionHeader.style.display = visibleFolders > 0 ? '' : 'none';
    }
    if (gridFolders) {
        gridFolders.style.display = visibleFolders > 0 ? '' : 'none';
    }
    if (filesSectionHeader) {
        filesSectionHeader.style.display = visibleFiles > 0 ? '' : 'none';
    }
    if (gridFiles) {
        gridFiles.style.display = visibleFiles > 0 ? '' : 'none';
    }

    // Toggle Empty State message
    if (noSearchResults) {
        const isFilterOrSearchActive = (currentTypeFilter !== 'all' || query !== '');
        const shouldShowEmpty = (visibleTotal === 0 && items.length > 0 && isFilterOrSearchActive);
        noSearchResults.style.display = shouldShowEmpty ? 'block' : 'none';

        if (shouldShowEmpty) {
            const typeLabelMap = {
                pdf: 'PDF',
                image: 'Image',
                archive: 'Zip / Archive',
                folder: 'Folder',
                text: 'Document / Code',
                audio: 'Audio',
                video: 'Video',
                file: 'Other'
            };
            const currentTypeName = typeLabelMap[currentTypeFilter] || currentTypeFilter;

            if (query !== '' && currentTypeFilter !== 'all') {
                if (noResultsTitle) noResultsTitle.textContent = `No matching ${currentTypeName} files found`;
                if (noResultsDesc) noResultsDesc.textContent = `No ${currentTypeName} files matched "${query}".`;
            } else if (query !== '') {
                if (noResultsTitle) noResultsTitle.textContent = 'No matching files found';
                if (noResultsDesc) noResultsDesc.textContent = `No files found matching "${query}".`;
            } else {
                if (noResultsTitle) noResultsTitle.textContent = `No ${currentTypeName} files found`;
                if (noResultsDesc) noResultsDesc.textContent = `There are no ${currentTypeName.toLowerCase()} files in this folder.`;
            }
        }
    }

    // Update Filter Active Status Bar
    if (filterActiveStatus && filterStatusText) {
        if (currentTypeFilter !== 'all' || query !== '') {
            filterActiveStatus.style.display = 'inline-flex';
            const parts = [];
            if (currentTypeFilter !== 'all') {
                const labelMap = {
                    pdf: 'PDF',
                    image: 'Images',
                    archive: 'Zip/Archives',
                    folder: 'Folders',
                    text: 'Documents & Code',
                    audio: 'Audio',
                    video: 'Video',
                    file: 'Other Files'
                };
                parts.push(`Type: ${labelMap[currentTypeFilter] || currentTypeFilter}`);
            }
            if (query !== '') {
                parts.push(`Search: "${query}"`);
            }
            filterStatusText.textContent = `Showing ${visibleTotal} of ${items.length} (${parts.join(', ')})`;
        } else {
            filterActiveStatus.style.display = 'none';
        }
    }
}

function resetAllFilters() {
    if (searchInput) {
        searchInput.value = '';
    }
    setTypeFilter('all');
}

// Search input events
if (searchInput) searchInput.addEventListener('input', applyFiltersAndSearch);
if (searchClear && searchInput) {
    searchClear.addEventListener('click', () => {
        searchInput.value = '';
        searchInput.focus();
        applyFiltersAndSearch();
    });
}

// Type filter select dropdown event
if (typeFilterSelect) {
    typeFilterSelect.addEventListener('change', () => {
        setTypeFilter(typeFilterSelect.value);
    });
}

// Type filter chips events
if (filterChips && filterChips.length > 0) {
    filterChips.forEach(chip => {
        chip.addEventListener('click', () => {
            const type = chip.getAttribute('data-type') || 'all';
            setTypeFilter(type);
        });
    });
}

// Clear filter buttons
if (filterStatusClear) {
    filterStatusClear.addEventListener('click', resetAllFilters);
}
if (resetFilterSearchBtn) {
    resetFilterSearchBtn.addEventListener('click', resetAllFilters);
}

// Check initial filter from URL params if present
const urlParams = new URLSearchParams(window.location.search);
const initialFilter = urlParams.get('type') || urlParams.get('filter');
if (initialFilter) {
    setTypeFilter(initialFilter);
}

// Sorting Support
const sortSelect = document.getElementById('sortSelect');
if (sortSelect) {
    sortSelect.addEventListener('change', () => {
        const [field, direction] = sortSelect.value.split('-');
        
        // Sort grid files
        const gridFiles = document.getElementById('gridFiles');
        if (gridFiles) {
            const cards = Array.from(gridFiles.querySelectorAll('.item-card'));
            cards.sort((a, b) => compareItems(a, b, field, direction));
            cards.forEach(card => gridFiles.appendChild(card));
        }

        // Sort table rows
        const tableBody = document.getElementById('tableBody');
        if (tableBody) {
            const rows = Array.from(tableBody.querySelectorAll('tr[data-type]'));
            rows.sort((a, b) => {
                const aIsFolder = a.getAttribute('data-type') === 'folder';
                const bIsFolder = b.getAttribute('data-type') === 'folder';
                if (aIsFolder && !bIsFolder) return -1;
                if (!aIsFolder && bIsFolder) return 1;
                return compareItems(a, b, field, direction);
            });
            rows.forEach(row => tableBody.appendChild(row));
        }
    });
}

function compareItems(a, b, field, direction) {
    let res = 0;
    if (field === 'name') {
        const aName = a.getAttribute('data-name') || '';
        const bName = b.getAttribute('data-name') || '';
        res = aName.localeCompare(bName, undefined, { numeric: true, sensitivity: 'base' });
    } else if (field === 'size') {
        const aSize = parseInt(a.getAttribute('data-size') || '0', 10);
        const bSize = parseInt(b.getAttribute('data-size') || '0', 10);
        res = aSize - bSize;
    } else if (field === 'date') {
        const aTime = parseInt(a.getAttribute('data-mtime') || '0', 10);
        const bTime = parseInt(b.getAttribute('data-mtime') || '0', 10);
        res = aTime - bTime;
    }
    return direction === 'desc' ? -res : res;
}

// Keyboard shortcuts
window.addEventListener('keydown', (e) => {
    if (e.key === '/' && document.activeElement !== searchInput) {
        if (searchInput) {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    } else if (e.key === 'Escape') {
        const uploadModal = document.getElementById('uploadModal');
        const previewModal = document.getElementById('previewModal');
        const clipModal = document.getElementById('clipboardModal');
        if (clipModal && clipModal.classList.contains('active')) {
            closeClipboardModal();
        } else if (uploadModal && uploadModal.classList.contains('active')) {
            closeUploadModal();
        } else if (previewModal && previewModal.classList.contains('active')) {
            closePreview();
        } else if (searchInput && document.activeElement === searchInput) {
            searchInput.blur();
        }
    }
});

// Modal Preview Manager
const modal = document.getElementById('previewModal');
const modalBody = document.getElementById('modalBody');
const modalFileName = document.getElementById('modalFileName');
const modalFileSize = document.getElementById('modalFileSize');
const modalTypeBadge = document.getElementById('modalTypeBadge');
const modalExternalLink = document.getElementById('modalExternalLink');
const modalDownloadBtn = document.getElementById('modalDownloadBtn');
const modalCopyBtn = document.getElementById('modalCopyBtn');
const modalCopyText = document.getElementById('modalCopyText');

let currentActiveText = '';

function copyModalContent() {
    if (!currentActiveText) return;
    navigator.clipboard.writeText(currentActiveText).then(() => {
        if (modalCopyText) modalCopyText.textContent = 'Copied!';
        setTimeout(() => {
            if (modalCopyText) modalCopyText.textContent = 'Copy';
        }, 2000);
    });
}

function openPreview(filePath, fileName, category, formattedSize, extension) {
    if (!modal) return;
    modalFileName.textContent = fileName;
    modalFileSize.textContent = formattedSize;
    modalTypeBadge.textContent = (extension || category).toUpperCase();
    modalTypeBadge.className = 'type-badge badge-' + category + '-bg';

    const viewUrl = '?action=view&file=' + encodeURIComponent(filePath);
    const downloadUrl = '?action=download&file=' + encodeURIComponent(filePath);

    if (modalExternalLink) modalExternalLink.href = viewUrl;
    if (modalDownloadBtn) modalDownloadBtn.href = downloadUrl;
    if (modalCopyBtn) modalCopyBtn.style.display = 'none';
    currentActiveText = '';

    modalBody.innerHTML = '<div class="spinner"></div>';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (category === 'image') {
        const img = new Image();
        img.className = 'preview-image';
        img.alt = fileName;
        img.onload = () => {
            modalBody.innerHTML = '';
            modalBody.appendChild(img);
        };
        img.onerror = () => {
            renderFallback(fileName, downloadUrl, 'Failed to load image preview.');
        };
        img.src = viewUrl;
    } else if (category === 'pdf') {
        const iframe = document.createElement('iframe');
        iframe.className = 'preview-iframe';
        iframe.src = viewUrl;
        iframe.title = fileName;
        modalBody.innerHTML = '';
        modalBody.appendChild(iframe);
    } else if (category === 'audio') {
        modalBody.innerHTML = `
            <div style="padding: 3rem; text-align: center;">
                <audio controls autoplay class="preview-media-player" src="${viewUrl}">
                    Your browser does not support audio element.
                </audio>
            </div>
        `;
    } else if (category === 'video') {
        modalBody.innerHTML = `
            <div style="display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; padding: 1.5rem;">
                <video controls autoplay class="preview-media-player" style="max-height: 100%;" src="${viewUrl}">
                    Your browser does not support video element.
                </video>
            </div>
        `;
    } else if (category === 'text') {
        fetch('?action=get_content&file=' + encodeURIComponent(filePath))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    currentActiveText = data.content;
                    if (modalCopyBtn) modalCopyBtn.style.display = 'inline-flex';
                    if (modalCopyText) modalCopyText.textContent = 'Copy';
                    
                    const pre = document.createElement('pre');
                    pre.className = 'preview-code-wrap';
                    const code = document.createElement('code');
                    code.textContent = data.content;
                    pre.appendChild(code);
                    modalBody.innerHTML = '';
                    modalBody.appendChild(pre);
                } else {
                    renderFallback(fileName, downloadUrl, data.error || 'Unable to display text content.');
                }
            })
            .catch(() => {
                renderFallback(fileName, downloadUrl, 'Failed to retrieve file contents.');
            });
    } else {
        renderFallback(fileName, downloadUrl, 'Preview not available for this file type.');
    }
}

function renderFallback(fileName, downloadUrl, message) {
    if (!modalBody) return;
    modalBody.innerHTML = `
        <div class="preview-fallback">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--text-faint); margin-bottom: 1rem;">
                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                <polyline points="13 2 13 9 20 9"></polyline>
            </svg>
            <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-main);">${escapeHtml(fileName)}</h4>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">${message}</p>
            <a href="${downloadUrl}" class="btn-action btn-primary" style="display: inline-flex; width: auto; padding: 0.6rem 1.25rem;">
                Download File
            </a>
        </div>
    `;
}

function closePreview() {
    if (!modal) return;
    modal.classList.remove('active');
    if (modalBody) modalBody.innerHTML = '';
    document.body.style.overflow = '';
}

function handleModalOverlayClick(e) {
    if (e.target === modal) {
        closePreview();
    }
}

// ---------------------------------------------------------------------------
// File Upload & Chunking Logic (Supports files up to 50 MB)
// ---------------------------------------------------------------------------
const MAX_UPLOAD_BYTES = 50 * 1024 * 1024; // 50 MB
const CHUNK_SIZE = 1.5 * 1024 * 1024;      // 1.5 MB per chunk

const uploadModal = document.getElementById('uploadModal');
const openUploadBtn = document.getElementById('openUploadBtn');
const dropzone = document.getElementById('dropzone');
const fileUploadInput = document.getElementById('fileUploadInput');
const uploadQueueContainer = document.getElementById('uploadQueueContainer');
const uploadQueueList = document.getElementById('uploadQueueList');
const uploadQueueSummary = document.getElementById('uploadQueueSummary');
const startUploadBtn = document.getElementById('startUploadBtn');

let uploadQueue = []; // Array of { id, file, status: 'pending'|'uploading'|'completed'|'error', progress: 0, errorMsg: '' }
let isUploading = false;

function openUploadModal() {
    if (!uploadModal) return;
    uploadModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeUploadModal() {
    if (!uploadModal) return;
    if (isUploading) {
        if (!confirm('Uploads are in progress. Do you really want to close?')) {
            return;
        }
    }
    uploadModal.classList.remove('active');
    document.body.style.overflow = '';
}

function handleUploadModalOverlayClick(e) {
    if (e.target === uploadModal) {
        closeUploadModal();
    }
}

if (openUploadBtn) {
    openUploadBtn.addEventListener('click', openUploadModal);
}

if (dropzone && fileUploadInput) {
    dropzone.addEventListener('click', () => {
        fileUploadInput.click();
    });

    fileUploadInput.addEventListener('change', (e) => {
        if (e.target.files && e.target.files.length > 0) {
            handleFilesSelected(Array.from(e.target.files));
            fileUploadInput.value = ''; // Reset input
        }
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('drag-active');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-active');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFilesSelected(Array.from(e.dataTransfer.files));
        }
    });
}

// Global page drag and drop onto explorer
const dropTargetContainer = document.getElementById('dropTargetContainer');
if (dropTargetContainer) {
    ['dragenter', 'dragover'].forEach(eventName => {
        window.addEventListener(eventName, (e) => {
            e.preventDefault();
        });
    });

    window.addEventListener('drop', (e) => {
        if (e.target.closest('#uploadModal')) return; // Handled by dropzone
        e.preventDefault();
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            openUploadModal();
            handleFilesSelected(Array.from(e.dataTransfer.files));
        }
    });
}

function formatBytes(bytes) {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
}

function handleFilesSelected(files) {
    files.forEach(file => {
        const item = {
            id: 'up_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9),
            file: file,
            status: file.size > MAX_UPLOAD_BYTES ? 'error' : 'pending',
            progress: 0,
            errorMsg: file.size > MAX_UPLOAD_BYTES ? 'Exceeds 50 MB limit' : ''
        };
        uploadQueue.push(item);
    });

    renderQueue();
}

function renderQueue() {
    if (!uploadQueueContainer || !uploadQueueList) return;
    
    if (uploadQueue.length === 0) {
        uploadQueueContainer.style.display = 'none';
        if (startUploadBtn) startUploadBtn.disabled = true;
        return;
    }

    uploadQueueContainer.style.display = 'block';
    const pendingCount = uploadQueue.filter(i => i.status === 'pending').length;
    uploadQueueSummary.textContent = `${uploadQueue.length} file(s) selected (${pendingCount} ready)`;
    if (startUploadBtn) {
        startUploadBtn.disabled = isUploading || pendingCount === 0;
    }

    uploadQueueList.innerHTML = '';
    uploadQueue.forEach(item => {
        const div = document.createElement('div');
        div.className = 'upload-queue-item';
        div.id = 'queue_item_' + item.id;

        let statusClass = 'status-pending';
        let statusText = 'Ready';
        if (item.status === 'uploading') {
            statusClass = 'status-uploading';
            statusText = `Uploading (${item.progress}%)`;
        } else if (item.status === 'completed') {
            statusClass = 'status-completed';
            statusText = 'Completed';
        } else if (item.status === 'error') {
            statusClass = 'status-error';
            statusText = item.errorMsg || 'Failed';
        }

        div.innerHTML = `
            <div class="queue-item-meta">
                <span class="queue-item-name" title="${escapeHtml(item.file.name)}">
                    ${escapeHtml(item.file.name)} (${formatBytes(item.file.size)})
                </span>
                <span class="queue-item-status ${statusClass}" id="status_${item.id}">
                    ${statusText}
                </span>
            </div>
            <div class="upload-progress-bar">
                <div class="upload-progress-fill ${item.status === 'completed' ? 'completed' : ''}" id="prog_${item.id}" style="width: ${item.progress}%"></div>
            </div>
        `;
        uploadQueueList.appendChild(div);
    });
}

function clearUploadQueue() {
    if (isUploading) return;
    uploadQueue = [];
    renderQueue();
}

async function startQueuedUploads() {
    if (isUploading) return;
    isUploading = true;
    if (startUploadBtn) startUploadBtn.disabled = true;

    const currentDir = document.body.getAttribute('data-current-dir') || '';
    let anySuccess = false;

    for (const item of uploadQueue) {
        if (item.status !== 'pending') continue;

        item.status = 'uploading';
        updateItemUI(item);

        try {
            await uploadSingleFileInChunks(item, currentDir);
            item.status = 'completed';
            item.progress = 100;
            anySuccess = true;
            updateItemUI(item);
        } catch (err) {
            item.status = 'error';
            item.errorMsg = err.message || 'Upload failed';
            updateItemUI(item);
        }
    }

    isUploading = false;
    if (startUploadBtn) startUploadBtn.disabled = false;

    if (anySuccess) {
        setTimeout(() => {
            // Auto refresh explorer page to show newly uploaded files
            window.location.reload();
        }, 1200);
    }
}

function updateItemUI(item) {
    const statusEl = document.getElementById('status_' + item.id);
    const progEl = document.getElementById('prog_' + item.id);

    if (statusEl) {
        let statusClass = 'status-pending';
        let statusText = 'Ready';
        if (item.status === 'uploading') {
            statusClass = 'status-uploading';
            statusText = `Uploading (${item.progress}%)`;
        } else if (item.status === 'completed') {
            statusClass = 'status-completed';
            statusText = 'Completed';
        } else if (item.status === 'error') {
            statusClass = 'status-error';
            statusText = item.errorMsg || 'Failed';
        }
        statusEl.className = 'queue-item-status ' + statusClass;
        statusEl.textContent = statusText;
    }

    if (progEl) {
        progEl.style.width = item.progress + '%';
        if (item.status === 'completed') {
            progEl.classList.add('completed');
        }
    }
}

async function uploadSingleFileInChunks(item, targetDir) {
    const file = item.file;
    const totalSize = file.size;
    const totalChunks = Math.ceil(totalSize / CHUNK_SIZE);
    const fileId = item.id;

    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        const start = chunkIndex * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, totalSize);
        const chunkBlob = file.slice(start, end);

        const formData = new FormData();
        formData.append('action', 'upload_chunk');
        formData.append('fileId', fileId);
        formData.append('fileName', file.name);
        formData.append('chunkIndex', chunkIndex.toString());
        formData.append('totalChunks', totalChunks.toString());
        formData.append('totalSize', totalSize.toString());
        formData.append('dir', targetDir);
        formData.append('chunk', chunkBlob, 'blob');

        const response = await fetch('index.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Server returned HTTP ${response.status}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Chunk upload failed');
        }

        // Calculate progress percentage
        item.progress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
        updateItemUI(item);
    }
}

function escapeHtml(str) {
    return str.replace(/[&<>"']/g, function(m) {
        switch (m) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
            default: return m;
        }
    });
}

// ====================================================
// Shared Network Clipboard Module
// ====================================================
const clipboardModal = document.getElementById('clipboardModal');
const openClipboardBtn = document.getElementById('openClipboardBtn');
const clipboardRefreshBtn = document.getElementById('clipboardRefreshBtn');
const clipboardTextarea = document.getElementById('clipboardTextarea');
const clipboardStatusPill = document.getElementById('clipboardStatusPill');
const clipCharCount = document.getElementById('clipCharCount');
const clipWordCount = document.getElementById('clipWordCount');
const clipLineCount = document.getElementById('clipLineCount');
const clipCopyBtn = document.getElementById('clipCopyBtn');
const clipCopyBtnText = document.getElementById('clipCopyBtnText');
const clipPasteBtn = document.getElementById('clipPasteBtn');
const clipClearBtn = document.getElementById('clipClearBtn');
const clipSaveBtn = document.getElementById('clipSaveBtn');
const clipSaveBtnText = document.getElementById('clipSaveBtnText');
const clipFontToggleBtn = document.getElementById('clipFontToggleBtn');
const clipFontLabel = document.getElementById('clipFontLabel');
const clipboardSyncNotice = document.getElementById('clipboardSyncNotice');
const loadRemoteClipBtn = document.getElementById('loadRemoteClipBtn');
const clipSaveAsFileBtn = document.getElementById('clipSaveAsFileBtn');
const clipSaveFileName = document.getElementById('clipSaveFileName');
const clipHistoryToggle = document.getElementById('clipHistoryToggle');
const clipHistoryList = document.getElementById('clipHistoryList');
const clipHistoryChevron = document.getElementById('clipHistoryChevron');
const clipHistoryCount = document.getElementById('clipHistoryCount');

let lastKnownRemoteTimestamp = 0;
let lastLoadedContent = '';
let pendingRemoteContent = '';
let clipboardPollTimer = null;
let isClipboardSaving = false;

function updateClipboardCounts() {
    if (!clipboardTextarea) return;
    const text = clipboardTextarea.value;
    const chars = text.length;
    const words = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
    const lines = text === '' ? 0 : text.split('\n').length;

    if (clipCharCount) clipCharCount.textContent = `${chars.toLocaleString()} chars`;
    if (clipWordCount) clipWordCount.textContent = `${words.toLocaleString()} words`;
    if (clipLineCount) clipLineCount.textContent = `${lines.toLocaleString()} lines`;
}

async function fetchRemoteClipboard(forceUpdateText = false) {
    try {
        const res = await fetch('index.php?action=get_clipboard&_t=' + Date.now());
        if (!res.ok) throw new Error('Network error');
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to get clipboard');

        const remoteContent = data.content || '';
        const remoteTime = data.updated_at || 0;

        if (clipboardStatusPill) {
            clipboardStatusPill.textContent = data.updated_formatted ? `Synced (${data.updated_formatted})` : 'Synced';
        }

        renderClipboardHistory(data.history || []);

        // Check if remote content differs
        if (remoteTime > lastKnownRemoteTimestamp) {
            lastKnownRemoteTimestamp = remoteTime;

            const isUserEditing = (clipboardTextarea.value !== lastLoadedContent);

            if (forceUpdateText || !isUserEditing) {
                clipboardTextarea.value = remoteContent;
                lastLoadedContent = remoteContent;
                updateClipboardCounts();
                if (clipboardSyncNotice) clipboardSyncNotice.style.display = 'none';
            } else if (remoteContent !== clipboardTextarea.value) {
                // Show notice so user doesn't lose current typing
                pendingRemoteContent = remoteContent;
                if (clipboardSyncNotice) clipboardSyncNotice.style.display = 'flex';
            }
        }
    } catch (err) {
        if (clipboardStatusPill) {
            clipboardStatusPill.textContent = 'Offline / Error';
        }
    }
}

async function saveSharedClipboard() {
    if (isClipboardSaving || !clipboardTextarea) return;
    isClipboardSaving = true;

    if (clipSaveBtnText) clipSaveBtnText.textContent = 'Saving...';
    if (clipSaveBtn) clipSaveBtn.disabled = true;

    const contentToSave = clipboardTextarea.value;

    try {
        const formData = new FormData();
        formData.append('action', 'save_clipboard');
        formData.append('content', contentToSave);

        const res = await fetch('index.php', {
            method: 'POST',
            body: formData
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Save failed');

        lastLoadedContent = contentToSave;
        lastKnownRemoteTimestamp = data.updated_at;

        if (clipboardStatusPill) {
            clipboardStatusPill.textContent = `Saved (${data.updated_formatted})`;
        }
        if (clipboardSyncNotice) clipboardSyncNotice.style.display = 'none';

        renderClipboardHistory(data.history || []);

        if (clipSaveBtnText) clipSaveBtnText.textContent = '✓ Saved!';
        setTimeout(() => {
            if (clipSaveBtnText) clipSaveBtnText.textContent = 'Save & Sync';
            if (clipSaveBtn) clipSaveBtn.disabled = false;
        }, 1500);
    } catch (err) {
        alert('Failed to save to shared clipboard: ' + err.message);
        if (clipSaveBtnText) clipSaveBtnText.textContent = 'Save & Sync';
        if (clipSaveBtn) clipSaveBtn.disabled = false;
    } finally {
        isClipboardSaving = false;
    }
}

function renderClipboardHistory(history) {
    if (!clipHistoryList || !clipHistoryCount) return;
    clipHistoryCount.textContent = history.length.toString();
    clipHistoryList.innerHTML = '';

    if (history.length === 0) {
        clipHistoryList.innerHTML = '<div style="padding: 0.5rem; color: var(--text-faint); font-size: 0.8rem; text-align: center;">No past clips recorded yet.</div>';
        return;
    }

    history.forEach(item => {
        const row = document.createElement('div');
        row.className = 'history-item';

        const previewDiv = document.createElement('div');
        previewDiv.className = 'history-item-preview';
        previewDiv.title = item.preview;
        previewDiv.textContent = item.preview;

        const metaDiv = document.createElement('div');
        metaDiv.className = 'history-item-meta';
        metaDiv.innerHTML = `<span>${item.sizeFormatted || ''}</span><span>•</span><span>${item.created_formatted || ''}</span>`;

        const btnGroup = document.createElement('div');
        btnGroup.style.display = 'flex';
        btnGroup.style.gap = '0.3rem';

        const loadBtn = document.createElement('button');
        loadBtn.type = 'button';
        loadBtn.className = 'btn-action';
        loadBtn.style.padding = '0.2rem 0.5rem';
        loadBtn.style.fontSize = '0.72rem';
        loadBtn.textContent = 'Load';
        loadBtn.onclick = () => {
            clipboardTextarea.value = item.content;
            lastLoadedContent = item.content;
            updateClipboardCounts();
            if (clipboardSyncNotice) clipboardSyncNotice.style.display = 'none';
            if (clipboardStatusPill) clipboardStatusPill.textContent = 'Loaded past clip';
        };

        const copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'btn-action';
        copyBtn.style.padding = '0.2rem 0.5rem';
        copyBtn.style.fontSize = '0.72rem';
        copyBtn.textContent = 'Copy';
        copyBtn.onclick = async () => {
            try {
                await navigator.clipboard.writeText(item.content);
                copyBtn.textContent = '✓';
                setTimeout(() => copyBtn.textContent = 'Copy', 1500);
            } catch (e) {
                clipboardTextarea.value = item.content;
                clipboardTextarea.select();
                document.execCommand('copy');
            }
        };

        btnGroup.appendChild(loadBtn);
        btnGroup.appendChild(copyBtn);

        row.appendChild(previewDiv);
        row.appendChild(metaDiv);
        row.appendChild(btnGroup);
        clipHistoryList.appendChild(row);
    });
}

function openClipboardModal() {
    if (!clipboardModal) return;
    clipboardModal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Fetch initial clipboard
    fetchRemoteClipboard(true);

    setTimeout(() => {
        if (clipboardTextarea) {
            clipboardTextarea.focus();
        }
    }, 150);

    // Auto polling every 4 seconds while modal is open
    if (!clipboardPollTimer) {
        clipboardPollTimer = setInterval(() => {
            fetchRemoteClipboard(false);
        }, 4000);
    }
}

function closeClipboardModal() {
    if (!clipboardModal) return;
    clipboardModal.classList.remove('active');
    document.body.style.overflow = '';

    if (clipboardPollTimer) {
        clearInterval(clipboardPollTimer);
        clipboardPollTimer = null;
    }
}

function handleClipboardModalOverlayClick(e) {
    if (e.target === clipboardModal) {
        closeClipboardModal();
    }
}

// Global exposure for HTML onclick attributes
window.openClipboardModal = openClipboardModal;
window.closeClipboardModal = closeClipboardModal;
window.handleClipboardModalOverlayClick = handleClipboardModalOverlayClick;

// Event Listeners for Clipboard UI
if (openClipboardBtn) {
    openClipboardBtn.addEventListener('click', openClipboardModal);
}

if (clipboardRefreshBtn) {
    clipboardRefreshBtn.addEventListener('click', () => {
        if (clipboardStatusPill) clipboardStatusPill.textContent = 'Refreshing...';
        fetchRemoteClipboard(true);
    });
}

if (clipboardTextarea) {
    clipboardTextarea.addEventListener('input', () => {
        updateClipboardCounts();
        if (clipboardStatusPill) clipboardStatusPill.textContent = 'Unsaved local edits (Ctrl+S to sync)';
    });

    // Keyboard shortcut: Ctrl+S or Cmd+S to save
    clipboardTextarea.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveSharedClipboard();
        }
    });
}

if (clipSaveBtn) {
    clipSaveBtn.addEventListener('click', saveSharedClipboard);
}

if (clipCopyBtn) {
    clipCopyBtn.addEventListener('click', async () => {
        if (!clipboardTextarea) return;
        const text = clipboardTextarea.value;
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            if (clipCopyBtnText) clipCopyBtnText.textContent = '✓ Copied!';
            setTimeout(() => {
                if (clipCopyBtnText) clipCopyBtnText.textContent = 'Copy All';
            }, 1800);
        } catch (err) {
            clipboardTextarea.select();
            document.execCommand('copy');
            if (clipCopyBtnText) clipCopyBtnText.textContent = '✓ Copied!';
            setTimeout(() => {
                if (clipCopyBtnText) clipCopyBtnText.textContent = 'Copy All';
            }, 1800);
        }
    });
}

if (clipPasteBtn) {
    clipPasteBtn.addEventListener('click', async () => {
        try {
            if (navigator.clipboard && navigator.clipboard.readText) {
                const text = await navigator.clipboard.readText();
                if (clipboardTextarea) {
                    clipboardTextarea.value = text;
                    updateClipboardCounts();
                    clipboardTextarea.focus();
                    if (clipboardStatusPill) clipboardStatusPill.textContent = 'Pasted (Click Save & Sync)';
                }
            } else {
                alert('Please press Ctrl+V or Cmd+V directly in the text area to paste.');
                clipboardTextarea.focus();
            }
        } catch (e) {
            alert('Clipboard read permission was not granted. Please press Ctrl+V or Cmd+V directly in the text area.');
            clipboardTextarea.focus();
        }
    });
}

if (clipClearBtn) {
    clipClearBtn.addEventListener('click', () => {
        if (clipboardTextarea && confirm('Clear the clipboard text area?')) {
            clipboardTextarea.value = '';
            updateClipboardCounts();
            if (clipboardStatusPill) clipboardStatusPill.textContent = 'Cleared locally (Click Save & Sync to sync empty state)';
        }
    });
}

if (clipFontToggleBtn && clipboardTextarea) {
    clipFontToggleBtn.addEventListener('click', () => {
        if (clipboardTextarea.classList.contains('font-mono')) {
            clipboardTextarea.classList.remove('font-mono');
            clipboardTextarea.classList.add('font-sans');
            if (clipFontLabel) clipFontLabel.textContent = 'Sans';
        } else {
            clipboardTextarea.classList.remove('font-sans');
            clipboardTextarea.classList.add('font-mono');
            if (clipFontLabel) clipFontLabel.textContent = 'Mono';
        }
    });
}

if (loadRemoteClipBtn) {
    loadRemoteClipBtn.addEventListener('click', () => {
        if (pendingRemoteContent && clipboardTextarea) {
            clipboardTextarea.value = pendingRemoteContent;
            lastLoadedContent = pendingRemoteContent;
            updateClipboardCounts();
            if (clipboardSyncNotice) clipboardSyncNotice.style.display = 'none';
            if (clipboardStatusPill) clipboardStatusPill.textContent = 'Loaded latest remote clip';
        }
    });
}

if (clipHistoryToggle && clipHistoryList && clipHistoryChevron) {
    clipHistoryToggle.addEventListener('click', () => {
        const isHidden = clipHistoryList.style.display === 'none';
        clipHistoryList.style.display = isHidden ? 'flex' : 'none';
        clipHistoryChevron.textContent = isHidden ? 'Hide ▲' : 'Show ▼';
    });
}

// Save as File directly into current folder
if (clipSaveAsFileBtn && clipSaveFileName) {
    clipSaveAsFileBtn.addEventListener('click', async () => {
        const text = clipboardTextarea ? clipboardTextarea.value : '';
        if (!text.trim()) {
            alert('Clipboard is empty! Paste some text first.');
            return;
        }
        let fileName = clipSaveFileName.value.trim();
        if (!fileName) {
            fileName = 'clipboard_' + new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19) + '.txt';
        }

        const currentDir = document.body.getAttribute('data-current-dir') || '';

        try {
            clipSaveAsFileBtn.disabled = true;
            clipSaveAsFileBtn.textContent = 'Saving...';

            const formData = new FormData();
            formData.append('action', 'save_clip_file');
            formData.append('dir', currentDir);
            formData.append('fileName', fileName);
            formData.append('content', text);

            const res = await fetch('index.php', {
                method: 'POST',
                body: formData
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Save failed');

            alert(`Saved as "${data.fileName}" in current folder!`);
            window.location.reload();
        } catch (err) {
            alert('Failed to save file: ' + err.message);
        } finally {
            clipSaveAsFileBtn.disabled = false;
            clipSaveAsFileBtn.textContent = 'Save File';
        }
    });
}

// Auto open clipboard if ?clipboard=1 or #clipboard in URL
if (urlParams.get('clipboard') === '1' || window.location.hash === '#clipboard') {
    openClipboardModal();
}
