<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Markdown Editor (Multi-Page)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { padding: 1rem; }
        .markdown { border: 1px solid #ddd; padding: 1rem; background: #f9f9f9; min-height: 100px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Edit Document</h1>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination" id="pagination"></ul>
        </nav>

        <!-- Text editor -->
        <div class="mb-3">
            <textarea id="content" class="form-control" rows="15" placeholder="Start typing Markdown..."></textarea>
        </div>

        <!-- Save button and status -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <button id="saveBtn" class="btn btn-primary">Save</button>
            <span id="status" class="fw-bold"></span>
        </div>

        <!-- Preview -->
        <h2>Preview</h2>
        <div id="preview" class="markdown"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_URL = 'api.php';
        const contentArea = document.getElementById('content');
        const previewArea = document.getElementById('preview');
        const statusArea = document.getElementById('status');
        const saveBtn = document.getElementById('saveBtn');

        let currentIndex = 1;
        let contentByIndex = {};

        // Initialize on load
        window.addEventListener('load', async () => {
            try {
                const response = await fetch(API_URL);
                if (!response.ok) throw new Error('Network response was not ok');
                const entries = await response.json();
                // entries is an array of {index, timestamp, content}
                for (const entry of entries) {
                    contentByIndex[entry.index] = entry.content;
                }
            } catch (error) {
                console.error('Could not load existing entries:', error);
            }

            // Ensure all indices 1..10 have a value
            for (let i = 1; i <= 10; i++) {
                if (!(i in contentByIndex)) {
                    contentByIndex[i] = '';
                }
            }

            // Provide a welcome text for the first page if empty
            if (!contentByIndex[1]) {
                contentByIndex[1] = "# New Document\n\nStart typing your Markdown here.";
            }

            renderPagination();
            loadPage(currentIndex);
        });

        // Render pagination items
        function renderPagination() {
            const ul = document.getElementById('pagination');
            ul.innerHTML = '';
            for (let i = 1; i <= 10; i++) {
                const li = document.createElement('li');
                li.className = 'page-item' + (i === currentIndex ? ' active' : '');
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = i;
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchPage(i);
                });
                li.appendChild(a);
                ul.appendChild(li);
            }
        }

        // Switch to a different page (index)
        function switchPage(index) {
            // Save current page content
            contentByIndex[currentIndex] = contentArea.value;
            currentIndex = index;
            loadPage(index);
            renderPagination();
        }

        // Load content for the given page
        function loadPage(index) {
            const content = contentByIndex[index] || '';
            contentArea.value = content;
            updatePreview(content);
        }

        // Update the Markdown preview
        function updatePreview(text) {
            previewArea.innerHTML = marked.parse(text);
        }

        // Save handler
        async function saveContent() {
            const content = contentArea.value;
            contentByIndex[currentIndex] = content;   // update memory
            statusArea.textContent = 'Saving...';
            statusArea.style.color = '#333';

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ index: currentIndex, content: content })
                });

                const message = await response.text();
                if (!response.ok || message.startsWith('Error')) {
                    statusArea.textContent = message;
                    statusArea.style.color = 'red';
                } else {
                    statusArea.textContent = 'Saved successfully!';
                    statusArea.style.color = 'green';
                    setTimeout(() => { statusArea.textContent = ''; }, 3000);
                }
            } catch (error) {
                console.error('Save error:', error);
                statusArea.textContent = 'Error: Could not save.';
                statusArea.style.color = 'red';
            }
        }

        // Wire up events
        contentArea.addEventListener('input', () => {
            const current = contentArea.value;
            contentByIndex[currentIndex] = current;
            updatePreview(current);
        });

        saveBtn.addEventListener('click', saveContent);

        // Perform initial preview
        function initPreview() {
            updatePreview(contentArea.value);
        }
        initPreview();
    </script>
</body>
</html>
